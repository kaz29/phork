<?php

declare(strict_types=1);

namespace Phork;

class JUnitLogParser
{
    /**
     * @return array<string, float> classname => total execution time in seconds
     */
    public function parse(string $xmlPath): array
    {
        if (!file_exists($xmlPath)) {
            return [];
        }

        $xml = @simplexml_load_file($xmlPath);
        if ($xml === false) {
            return [];
        }

        $runtimeMap = [];
        $testcases = $xml->xpath('//testcase');

        if ($testcases === false || count($testcases) === 0) {
            return [];
        }

        foreach ($testcases as $testcase) {
            $classname = (string) ($testcase['classname'] ?? '');
            $time = (float) ($testcase['time'] ?? 0.0);

            if ($classname === '') {
                continue;
            }

            // PHPUnit JUnit XML uses dot-separated classnames (e.g. "App.Tests.FooTest")
            // Convert to backslash-separated PHP namespace format
            $classname = str_replace('.', '\\', $classname);

            $runtimeMap[$classname] = ($runtimeMap[$classname] ?? 0.0) + $time;
        }

        return $runtimeMap;
    }
}
