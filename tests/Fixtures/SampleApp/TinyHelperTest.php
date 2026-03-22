<?php

declare(strict_types=1);

namespace Phork\Tests\Fixtures\SampleApp;

use PHPUnit\Framework\TestCase;

class TinyHelperTest extends TestCase
{
    public function testTrim(): void
    {
        usleep(10_000);
        $this->assertSame('hello', trim('  hello  '));
    }

    public function testStrtolower(): void
    {
        usleep(10_000);
        $this->assertSame('hello', strtolower('HELLO'));
    }
}
