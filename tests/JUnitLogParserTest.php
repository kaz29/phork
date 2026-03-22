<?php

declare(strict_types=1);

namespace Phork\Tests;

use PHPUnit\Framework\TestCase;
use Phork\JUnitLogParser;

class JUnitLogParserTest extends TestCase
{
    private JUnitLogParser $parser;
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->parser = new JUnitLogParser();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    private function createTempXml(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'phork_test_');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;
        return $path;
    }

    public function testParseValidXml(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="Suite 1">
    <testcase name="testA" classname="Tests\Unit\FooTest" time="1.5" />
    <testcase name="testB" classname="Tests\Unit\FooTest" time="0.5" />
    <testcase name="testC" classname="Tests\Unit\BarTest" time="2.0" />
  </testsuite>
  <testsuite name="Suite 2">
    <testcase name="testD" classname="Tests\Feature\BazTest" time="3.0" />
  </testsuite>
</testsuites>
XML;

        $result = $this->parser->parse($this->createTempXml($xml));

        $this->assertCount(3, $result);
        $this->assertEqualsWithDelta(2.0, $result['Tests\Unit\FooTest'], 0.001);
        $this->assertEqualsWithDelta(2.0, $result['Tests\Unit\BarTest'], 0.001);
        $this->assertEqualsWithDelta(3.0, $result['Tests\Feature\BazTest'], 0.001);
    }

    public function testParseNonExistentFile(): void
    {
        $result = $this->parser->parse('/tmp/non_existent_file_phork.xml');

        $this->assertSame([], $result);
    }

    public function testParseEmptyXml(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="Empty">
  </testsuite>
</testsuites>
XML;

        $result = $this->parser->parse($this->createTempXml($xml));

        $this->assertSame([], $result);
    }
}
