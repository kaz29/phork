<?php

declare(strict_types=1);

namespace Phork\Tests\Fixtures\SampleApp;

use PHPUnit\Framework\TestCase;

class QuickValidationTest extends TestCase
{
    public function testNotEmpty(): void
    {
        usleep(15_000);
        $this->assertNotEmpty('hello');
    }

    public function testIsNumeric(): void
    {
        usleep(15_000);
        $this->assertIsNumeric(42);
    }
}
