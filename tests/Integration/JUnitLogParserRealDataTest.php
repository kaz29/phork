<?php

declare(strict_types=1);

namespace Phork\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Phork\JUnitLogParser;
use Symfony\Component\Process\Process;

class JUnitLogParserRealDataTest extends TestCase
{
    private string $junitXmlPath;

    protected function setUp(): void
    {
        $this->junitXmlPath = tempnam(sys_get_temp_dir(), 'phork-integration-') . '.xml';

        $process = new Process([
            'vendor/bin/phpunit',
            '--log-junit', $this->junitXmlPath,
            'tests/Fixtures/SampleApp/',
        ]);
        $process->setTimeout(60);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), 'Fixture tests should pass: ' . $process->getErrorOutput());
        $this->assertFileExists($this->junitXmlPath, 'JUnit XML should be generated');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->junitXmlPath)) {
            @unlink($this->junitXmlPath);
        }
    }

    public function testParseRealJunitXml(): void
    {
        $parser = new JUnitLogParser();
        $runtimeMap = $parser->parse($this->junitXmlPath);

        $this->assertCount(6, $runtimeMap, 'Should have 6 test classes');

        $expectedClasses = [
            'Phork\Tests\Fixtures\SampleApp\SlowFeatureTest',
            'Phork\Tests\Fixtures\SampleApp\AnotherSlowTest',
            'Phork\Tests\Fixtures\SampleApp\MediumServiceTest',
            'Phork\Tests\Fixtures\SampleApp\FastUnitTest',
            'Phork\Tests\Fixtures\SampleApp\QuickValidationTest',
            'Phork\Tests\Fixtures\SampleApp\TinyHelperTest',
        ];

        foreach ($expectedClasses as $class) {
            $this->assertArrayHasKey($class, $runtimeMap, "Should contain {$class}");
            $this->assertGreaterThan(0.0, $runtimeMap[$class], "{$class} should have positive execution time");
        }
    }

    public function testRelativeTimingOrder(): void
    {
        $parser = new JUnitLogParser();
        $runtimeMap = $parser->parse($this->junitXmlPath);

        $slowTime = $runtimeMap['Phork\Tests\Fixtures\SampleApp\SlowFeatureTest'];
        $tinyTime = $runtimeMap['Phork\Tests\Fixtures\SampleApp\TinyHelperTest'];

        $this->assertGreaterThan($tinyTime, $slowTime, 'SlowFeatureTest should be slower than TinyHelperTest');
    }
}
