<?php

declare(strict_types=1);

namespace Phork;

use Symfony\Component\Process\Process;

class Runner
{
    public function __construct(
        private JUnitLogParser $parser,
        private TestSplitter $splitter,
    ) {}

    public function run(
        int $workerCount,
        ?string $logPath,
        string $testDir,
        ?string $outputPath,
    ): int {
        $runtimeMap = $logPath !== null ? $this->parser->parse($logPath) : [];

        $testFiles = $this->scanTestFiles($testDir);
        if (count($testFiles) === 0) {
            fwrite(STDERR, "No test files found in {$testDir}\n");
            return 1;
        }

        $buckets = $this->splitter->split($testFiles, $runtimeMap, $workerCount);

        $tempFiles = [];
        $processes = [];

        try {
            foreach ($buckets as $i => $files) {
                if (count($files) === 0) {
                    continue;
                }

                $configPath = tempnam(sys_get_temp_dir(), "phork-worker-{$i}-") . '.xml';
                $junitPath = tempnam(sys_get_temp_dir(), "phork-junit-{$i}-") . '.xml';
                $tempFiles[] = $configPath;
                $tempFiles[] = $junitPath;

                $this->writeWorkerConfig($configPath, $files);

                $process = new Process([
                    'vendor/bin/paratest',
                    '--configuration', $configPath,
                    '--log-junit', $junitPath,
                ]);
                $process->setTimeout(null);
                $process->start();

                $processes[] = [
                    'process' => $process,
                    'junitPath' => $junitPath,
                    'workerIndex' => $i,
                ];
            }

            $exitCode = 0;
            foreach ($processes as $entry) {
                /** @var Process $process */
                $process = $entry['process'];
                $process->wait();

                echo $process->getOutput();
                $errOutput = $process->getErrorOutput();
                if ($errOutput !== '') {
                    fwrite(STDERR, $errOutput);
                }

                if ($process->getExitCode() !== 0) {
                    $exitCode = 1;
                }
            }

            if ($outputPath !== null) {
                $junitPaths = array_filter(
                    array_map(fn($e) => $e['junitPath'], $processes),
                    fn($p) => file_exists($p),
                );
                $this->mergeJunitXml($junitPaths, $outputPath);
            }

            return $exitCode;
        } finally {
            foreach ($tempFiles as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function scanTestFiles(string $testDir): array
    {
        $testDir = rtrim($testDir, '/');
        if (!is_dir($testDir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($testDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/Test\.php$/', $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);
        return $files;
    }

    /**
     * @param list<string> $testFiles
     */
    private function writeWorkerConfig(string $configPath, array $testFiles): void
    {
        $fileEntries = '';
        foreach ($testFiles as $file) {
            $escaped = htmlspecialchars($file, ENT_XML1);
            $fileEntries .= "            <file>{$escaped}</file>\n";
        }

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="Worker">
{$fileEntries}        </testsuite>
    </testsuites>
</phpunit>
XML;

        file_put_contents($configPath, $xml);
    }

    /**
     * @param list<string> $junitPaths
     */
    private function mergeJunitXml(array $junitPaths, string $outputPath): void
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $root = $doc->createElement('testsuites');
        $doc->appendChild($root);

        foreach ($junitPaths as $path) {
            $workerDoc = new \DOMDocument();
            if (!@$workerDoc->load($path)) {
                continue;
            }

            $testsuites = $workerDoc->getElementsByTagName('testsuite');
            foreach ($testsuites as $suite) {
                $imported = $doc->importNode($suite, true);
                $root->appendChild($imported);
            }
        }

        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $doc->save($outputPath);
    }
}
