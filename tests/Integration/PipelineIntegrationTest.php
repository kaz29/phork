<?php

declare(strict_types=1);

namespace Phork\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Phork\JUnitLogParser;
use Phork\TestSplitter;
use Symfony\Component\Process\Process;

class PipelineIntegrationTest extends TestCase
{
    private string $junitXmlPath;

    protected function setUp(): void
    {
        $this->junitXmlPath = tempnam(sys_get_temp_dir(), 'phork-pipeline-') . '.xml';

        $process = new Process([
            'vendor/bin/phpunit',
            '--log-junit', $this->junitXmlPath,
            'tests/Fixtures/SampleApp/',
        ]);
        $process->setTimeout(60);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), 'Fixture tests should pass: ' . $process->getErrorOutput());
    }

    protected function tearDown(): void
    {
        if (file_exists($this->junitXmlPath)) {
            @unlink($this->junitXmlPath);
        }
    }

    public function testFullPipeline(): void
    {
        $parser = new JUnitLogParser();
        $runtimeMap = $parser->parse($this->junitXmlPath);

        $testFiles = glob('tests/Fixtures/SampleApp/*Test.php');
        $this->assertCount(6, $testFiles);

        $splitter = new TestSplitter([
            'Phork\\Tests\\' => 'tests/',
        ]);

        $buckets = $splitter->split($testFiles, $runtimeMap, 2);

        $this->assertCount(2, $buckets);

        // All files distributed
        $allFiles = array_merge(...$buckets);
        sort($allFiles);
        $expected = $testFiles;
        sort($expected);
        $this->assertSame($expected, $allFiles);

        // No duplicates
        $this->assertCount(6, array_unique($allFiles));

        // Slowest two tests should be in different buckets (greedy bin packing)
        $bucket0 = $buckets[0];
        $bucket1 = $buckets[1];

        $slowFile = 'tests/Fixtures/SampleApp/SlowFeatureTest.php';
        $anotherSlowFile = 'tests/Fixtures/SampleApp/AnotherSlowTest.php';

        if (in_array($slowFile, $bucket0, true)) {
            $this->assertContains($anotherSlowFile, $bucket1, 'Two slowest tests should be in different buckets');
        } else {
            $this->assertContains($slowFile, $bucket1);
            $this->assertContains($anotherSlowFile, $bucket0, 'Two slowest tests should be in different buckets');
        }
    }

    public function testBucketTimeBalance(): void
    {
        $parser = new JUnitLogParser();
        $runtimeMap = $parser->parse($this->junitXmlPath);

        $testFiles = glob('tests/Fixtures/SampleApp/*Test.php');

        $splitter = new TestSplitter([
            'Phork\\Tests\\' => 'tests/',
        ]);

        $buckets = $splitter->split($testFiles, $runtimeMap, 2);

        // Calculate total time per bucket
        $bucketTimes = [];
        foreach ($buckets as $i => $files) {
            $total = 0.0;
            foreach ($files as $file) {
                $classname = 'Phork\\Tests\\' . str_replace('/', '\\', preg_replace('/\.php$/', '', substr($file, strlen('tests/'))));
                $total += $runtimeMap[$classname] ?? 0.0;
            }
            $bucketTimes[$i] = $total;
        }

        $totalTime = array_sum($bucketTimes);
        $diff = abs($bucketTimes[0] - $bucketTimes[1]);

        // Difference should be less than 50% of total
        $this->assertLessThan(
            $totalTime * 0.5,
            $diff,
            sprintf('Buckets should be reasonably balanced: %.3fs vs %.3fs', $bucketTimes[0], $bucketTimes[1]),
        );
    }
}
