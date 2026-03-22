<?php

declare(strict_types=1);

namespace Phork\Tests\Fixtures\SampleApp;

use PHPUnit\Framework\TestCase;

class AnotherSlowTest extends TestCase
{
    public function testHeavyCalculation(): void
    {
        usleep(125_000);
        $this->assertTrue(true);
    }

    public function testComplexQuery(): void
    {
        usleep(125_000);
        $this->assertTrue(true);
    }
}
