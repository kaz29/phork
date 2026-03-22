<?php

declare(strict_types=1);

namespace Phork\Tests\Fixtures\SampleApp;

use PHPUnit\Framework\TestCase;

class FastUnitTest extends TestCase
{
    public function testAddition(): void
    {
        usleep(25_000);
        $this->assertSame(4, 2 + 2);
    }

    public function testSubtraction(): void
    {
        usleep(25_000);
        $this->assertSame(0, 2 - 2);
    }
}
