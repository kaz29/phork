<?php

declare(strict_types=1);

namespace Phork\Tests\Fixtures\SampleApp;

use PHPUnit\Framework\TestCase;

class SlowFeatureTest extends TestCase
{
    public function testSlowOperation(): void
    {
        usleep(150_000);
        $this->assertTrue(true);
    }

    public function testAnotherSlowOperation(): void
    {
        usleep(150_000);
        $this->assertTrue(true);
    }
}
