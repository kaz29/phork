<?php

declare(strict_types=1);

namespace Phork\Tests;

use PHPUnit\Framework\TestCase;
use Phork\TestSplitter;

class TestSplitterTest extends TestCase
{
    private TestSplitter $splitter;

    protected function setUp(): void
    {
        $this->splitter = new TestSplitter();
    }

    public function testSplitWithRuntimeData(): void
    {
        $testFiles = [
            'tests/Unit/FastTest.php',
            'tests/Unit/SlowTest.php',
            'tests/Unit/MediumTest.php',
            'tests/Unit/QuickTest.php',
        ];

        $runtimeMap = [
            'tests\Unit\FastTest' => 1.0,
            'tests\Unit\SlowTest' => 10.0,
            'tests\Unit\MediumTest' => 5.0,
            'tests\Unit\QuickTest' => 2.0,
        ];

        $buckets = $this->splitter->split($testFiles, $runtimeMap, 2);

        $this->assertCount(2, $buckets);

        // SlowTest (10) should be in a different bucket than MediumTest (5) + QuickTest (2) + FastTest (1)
        $bucket0Files = $buckets[0];
        $bucket1Files = $buckets[1];

        $allFiles = array_merge($bucket0Files, $bucket1Files);
        sort($allFiles);
        $expectedFiles = $testFiles;
        sort($expectedFiles);
        $this->assertSame($expectedFiles, $allFiles);

        // Verify balance: slowest test alone vs rest
        $this->assertContains('tests/Unit/SlowTest.php', $bucket0Files);
        $this->assertNotContains('tests/Unit/MediumTest.php', $bucket0Files);
    }

    public function testSplitWithoutRuntimeData(): void
    {
        $testFiles = [
            'tests/ATest.php',
            'tests/BTest.php',
            'tests/CTest.php',
            'tests/DTest.php',
        ];

        $buckets = $this->splitter->split($testFiles, [], 2);

        $this->assertCount(2, $buckets);
        // Round-robin: A,C in bucket 0; B,D in bucket 1
        $this->assertSame(['tests/ATest.php', 'tests/CTest.php'], $buckets[0]);
        $this->assertSame(['tests/BTest.php', 'tests/DTest.php'], $buckets[1]);
    }

    public function testSplitWithNewTests(): void
    {
        $testFiles = [
            'tests/KnownSlowTest.php',
            'tests/KnownFastTest.php',
            'tests/BrandNewTest.php',
        ];

        $runtimeMap = [
            'tests\KnownSlowTest' => 8.0,
            'tests\KnownFastTest' => 2.0,
        ];

        $buckets = $this->splitter->split($testFiles, $runtimeMap, 2);

        $this->assertCount(2, $buckets);

        $allFiles = array_merge(...$buckets);
        $this->assertCount(3, $allFiles);
        $this->assertContains('tests/BrandNewTest.php', $allFiles);
    }

    public function testSplitSingleWorker(): void
    {
        $testFiles = [
            'tests/ATest.php',
            'tests/BTest.php',
            'tests/CTest.php',
        ];

        $buckets = $this->splitter->split($testFiles, [], 1);

        $this->assertCount(1, $buckets);
        $this->assertCount(3, $buckets[0]);
    }

    public function testSplitWithPsr4Mapping(): void
    {
        $splitter = new TestSplitter([
            'App\\Tests\\' => 'tests/',
        ]);

        $testFiles = [
            'tests/Unit/FastTest.php',
            'tests/Unit/SlowTest.php',
            'tests/Unit/MediumTest.php',
        ];

        $runtimeMap = [
            'App\\Tests\\Unit\\SlowTest' => 10.0,
            'App\\Tests\\Unit\\MediumTest' => 5.0,
            'App\\Tests\\Unit\\FastTest' => 1.0,
        ];

        $buckets = $splitter->split($testFiles, $runtimeMap, 2);

        $this->assertCount(2, $buckets);

        $allFiles = array_merge(...$buckets);
        sort($allFiles);
        $expected = $testFiles;
        sort($expected);
        $this->assertSame($expected, $allFiles);

        // SlowTest (10) should be alone, MediumTest (5) + FastTest (1) together
        $this->assertContains('tests/Unit/SlowTest.php', $buckets[0]);
        $this->assertNotContains('tests/Unit/MediumTest.php', $buckets[0]);
    }
}
