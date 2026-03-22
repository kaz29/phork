<?php

declare(strict_types=1);

namespace Phork;

class TestSplitter
{
    /** @var array<string, string> namespace prefix => directory prefix */
    private array $psr4Map;

    /**
     * @param array<string, string> $psr4Map PSR-4 mapping (namespace prefix => directory prefix)
     */
    public function __construct(array $psr4Map = [])
    {
        $this->psr4Map = $psr4Map;
    }

    /**
     * @param list<string> $testFiles          Test file paths
     * @param array<string, float> $runtimeMap classname => seconds
     * @param int $workerCount                 Number of workers
     * @return list<list<string>>              Array of worker buckets
     */
    public function split(array $testFiles, array $runtimeMap, int $workerCount): array
    {
        $workerCount = max(1, $workerCount);

        $buckets = array_fill(0, $workerCount, []);
        $bucketTimes = array_fill(0, $workerCount, 0.0);

        if (count($testFiles) === 0) {
            return $buckets;
        }

        if (count($runtimeMap) === 0) {
            return $this->roundRobin($testFiles, $workerCount);
        }

        $averageTime = array_sum($runtimeMap) / count($runtimeMap);

        $fileRuntimes = [];
        foreach ($testFiles as $file) {
            $classname = $this->fileToClassname($file);
            $time = $runtimeMap[$classname] ?? $averageTime;
            $fileRuntimes[] = ['file' => $file, 'time' => $time];
        }

        usort($fileRuntimes, fn($a, $b) => $b['time'] <=> $a['time']);

        foreach ($fileRuntimes as $entry) {
            $minIndex = array_keys($bucketTimes, min($bucketTimes))[0];
            $buckets[$minIndex][] = $entry['file'];
            $bucketTimes[$minIndex] += $entry['time'];
        }

        return $buckets;
    }

    /**
     * @param list<string> $testFiles
     * @return list<list<string>>
     */
    private function roundRobin(array $testFiles, int $workerCount): array
    {
        $buckets = array_fill(0, $workerCount, []);

        foreach ($testFiles as $i => $file) {
            $buckets[$i % $workerCount][] = $file;
        }

        return $buckets;
    }

    private function fileToClassname(string $filePath): string
    {
        $normalizedPath = str_replace('\\', '/', $filePath);
        $normalizedPath = preg_replace('/\.php$/', '', $normalizedPath);

        foreach ($this->psr4Map as $namespace => $dir) {
            $dir = rtrim(str_replace('\\', '/', $dir), '/') . '/';
            if (str_starts_with($normalizedPath, $dir)) {
                $relative = substr($normalizedPath, strlen($dir));
                return rtrim($namespace, '\\') . '\\' . str_replace('/', '\\', $relative);
            }
        }

        // Fallback: path-based conversion
        return str_replace('/', '\\', $normalizedPath);
    }
}
