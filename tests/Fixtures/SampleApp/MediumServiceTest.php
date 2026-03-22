<?php

declare(strict_types=1);

namespace Phork\Tests\Fixtures\SampleApp;

use PHPUnit\Framework\TestCase;

class MediumServiceTest extends TestCase
{
    public function testServiceCall(): void
    {
        usleep(75_000);
        $this->assertTrue(true);
    }

    public function testServiceResponse(): void
    {
        usleep(75_000);
        $this->assertTrue(true);
    }
}
