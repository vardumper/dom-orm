#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Merge multiple Clover XML files into one combined report.
 *
 * Usage:
 *   php bin/merge-clover.php <output.xml> <input1.xml> <input2.xml> [...]
 *
 * Example:
 *   php bin/merge-clover.php clover.xml clover.unit.xml clover.integration.xml
 */

if ($argc < 4) {
    fwrite(STDERR, "Usage: php bin/merge-clover.php <output.xml> <input1.xml> <input2.xml> [... ]\n");
    exit(1);
}

$output = $argv[1];
$inputs = array_slice($argv, 2);

$basePath = array_shift($inputs);
$base = new DOMDocument();
$base->preserveWhiteSpace = false;
$base->formatOutput = true;

if (!$base->load($basePath)) {
    fwrite(STDERR, sprintf("Failed to load base Clover XML: %s\n", $basePath));
    exit(1);
}

$baseXpath = new DOMXPath($base);
$project = $baseXpath->query('/coverage/project')->item(0);
if (!$project instanceof DOMElement) {
    fwrite(STDERR, "Invalid Clover XML: missing /coverage/project in base file.\n");
    exit(1);
}

foreach ($inputs as $inputPath) {
    $incoming = new DOMDocument();
    $incoming->preserveWhiteSpace = false;
    $incoming->formatOutput = false;

    if (!$incoming->load($inputPath)) {
        fwrite(STDERR, sprintf("Failed to load Clover XML: %s\n", $inputPath));
        exit(1);
    }

    mergeCloverDocument($base, $incoming);
}

recomputePackageAndProjectMetrics($base);

$coverageNode = $baseXpath->query('/coverage')->item(0);
if ($coverageNode instanceof DOMElement) {
    $coverageNode->setAttribute('generated', (string)time());
}
$project->setAttribute('timestamp', (string)time());

if ($base->save($output) === false) {
    fwrite(STDERR, sprintf("Failed to write merged Clover XML: %s\n", $output));
    exit(1);
}

fwrite(STDOUT, sprintf("Merged Clover written to %s\n", $output));

/**
 * Merge incoming Clover DOM into the base Clover DOM.
 */
function mergeCloverDocument(DOMDocument $base, DOMDocument $incoming): void
{
    $baseXpath = new DOMXPath($base);
    $incomingXpath = new DOMXPath($incoming);

    $incomingFiles = $incomingXpath->query('/coverage/project/package/file');
    if ($incomingFiles === false) {
        return;
    }

    foreach ($incomingFiles as $incomingFile) {
        if (!$incomingFile instanceof DOMElement) {
            continue;
        }

        $fileName = $incomingFile->getAttribute('name');
        if ($fileName === '') {
            continue;
        }

        $incomingPackageNode = $incomingFile->parentNode;
        if (!$incomingPackageNode instanceof DOMElement) {
            continue;
        }

        $packageName = $incomingPackageNode->getAttribute('name');

        $baseFile = findBaseFileByName($baseXpath, $fileName);

        if (!$baseFile instanceof DOMElement) {
            $targetPackage = findOrCreatePackage($base, $baseXpath, $packageName);
            $targetPackage->appendChild($base->importNode($incomingFile, true));

            continue;
        }

        mergeFileLines($base, $baseFile, $incomingFile);
        recomputeFileMetrics($baseFile);
    }
}

function findBaseFileByName(DOMXPath $baseXpath, string $fileName): ?DOMElement
{
    $escaped = xpathStringLiteral($fileName);
    $node = $baseXpath->query('/coverage/project/package/file[@name=' . $escaped . ']')->item(0);

    return $node instanceof DOMElement ? $node : null;
}

function findOrCreatePackage(DOMDocument $base, DOMXPath $baseXpath, string $packageName): DOMElement
{
    $escaped = xpathStringLiteral($packageName);
    $existing = $baseXpath->query('/coverage/project/package[@name=' . $escaped . ']')->item(0);

    if ($existing instanceof DOMElement) {
        return $existing;
    }

    $project = $baseXpath->query('/coverage/project')->item(0);
    if (!$project instanceof DOMElement) {
        throw new RuntimeException('Invalid Clover XML: missing project node.');
    }

    $newPackage = $base->createElement('package');
    $newPackage->setAttribute('name', $packageName);
    $project->appendChild($newPackage);

    return $newPackage;
}

function mergeFileLines(DOMDocument $base, DOMElement $baseFile, DOMElement $incomingFile): void
{
    $lineIndex = [];
    foreach ($baseFile->getElementsByTagName('line') as $line) {
        if (!$line instanceof DOMElement) {
            continue;
        }

        $key = lineKey($line);
        $lineIndex[$key] = $line;
    }

    $incomingLines = $incomingFile->getElementsByTagName('line');
    foreach ($incomingLines as $incomingLine) {
        if (!$incomingLine instanceof DOMElement) {
            continue;
        }

        $key = lineKey($incomingLine);

        if (!isset($lineIndex[$key])) {
            $imported = $base->importNode($incomingLine, true);
            $insertBefore = findFirstChildByTagName($baseFile, 'metrics');
            $baseFile->insertBefore($imported, $insertBefore);
            $lineIndex[$key] = $imported;

            continue;
        }

        $baseLine = $lineIndex[$key];
        $baseCount = (int)$baseLine->getAttribute('count');
        $incomingCount = (int)$incomingLine->getAttribute('count');
        $baseLine->setAttribute('count', (string)($baseCount + $incomingCount));
    }
}

function recomputePackageAndProjectMetrics(DOMDocument $doc): void
{
    $xpath = new DOMXPath($doc);

    $project = $xpath->query('/coverage/project')->item(0);
    if (!$project instanceof DOMElement) {
        return;
    }

    $projectTotals = zeroMetrics();

    foreach ($xpath->query('/coverage/project/package') as $packageNode) {
        if (!$packageNode instanceof DOMElement) {
            continue;
        }

        $packageTotals = zeroMetrics();

        foreach ($packageNode->getElementsByTagName('file') as $fileNode) {
            if (!$fileNode instanceof DOMElement) {
                continue;
            }

            recomputeFileMetrics($fileNode);
            $fileMetrics = parseMetrics(getOrCreateMetricsNode($doc, $fileNode));
            sumMetrics($packageTotals, $fileMetrics);
        }

        writeMetrics(getOrCreateMetricsNode($doc, $packageNode), $packageTotals);
        sumMetrics($projectTotals, $packageTotals);
    }

    writeMetrics(getOrCreateMetricsNode($doc, $project), $projectTotals);
}

function recomputeFileMetrics(DOMElement $fileNode): void
{
    $totals = zeroMetrics();

    $maxLine = 0;
    $lineNodes = $fileNode->getElementsByTagName('line');
    foreach ($lineNodes as $line) {
        if (!$line instanceof DOMElement) {
            continue;
        }

        $lineNumber = (int)$line->getAttribute('num');
        if ($lineNumber > $maxLine) {
            $maxLine = $lineNumber;
        }

        $type = $line->getAttribute('type');
        $count = (int)$line->getAttribute('count');

        if ($type === 'method') {
            $totals['methods']++;
            if ($count > 0) {
                $totals['coveredmethods']++;
            }
            $totals['complexity'] += max(1, (int)$line->getAttribute('complexity'));
        }

        if ($type === 'stmt') {
            $totals['statements']++;
            if ($count > 0) {
                $totals['coveredstatements']++;
            }
        }
    }

    $totals['elements'] = $totals['methods'] + $totals['statements'];
    $totals['coveredelements'] = $totals['coveredmethods'] + $totals['coveredstatements'];
    $totals['conditionals'] = 0;
    $totals['coveredconditionals'] = 0;
    $totals['classes'] = max(1, $fileNode->getElementsByTagName('class')->length);
    $totals['loc'] = $maxLine;
    $totals['ncloc'] = $maxLine;

    $metricsNode = getOrCreateMetricsNode($fileNode->ownerDocument, $fileNode);
    writeMetrics($metricsNode, $totals);
}

function lineKey(DOMElement $line): string
{
    return implode('|', [
        $line->getAttribute('num'),
        $line->getAttribute('type'),
        $line->getAttribute('name'),
        $line->getAttribute('visibility'),
    ]);
}

function findFirstChildByTagName(DOMElement $parent, string $tagName): ?DOMNode
{
    foreach ($parent->childNodes as $child) {
        if ($child instanceof DOMElement && $child->tagName === $tagName) {
            return $child;
        }
    }

    return null;
}

/**
 * @return array<string, int>
 */
function zeroMetrics(): array
{
    return [
        'complexity' => 0,
        'methods' => 0,
        'coveredmethods' => 0,
        'conditionals' => 0,
        'coveredconditionals' => 0,
        'statements' => 0,
        'coveredstatements' => 0,
        'elements' => 0,
        'coveredelements' => 0,
        'classes' => 0,
        'loc' => 0,
        'ncloc' => 0,
    ];
}

/**
 * @param array<string, int> $target
 * @param array<string, int> $source
 */
function sumMetrics(array &$target, array $source): void
{
    foreach ($target as $key => $_) {
        $target[$key] += $source[$key] ?? 0;
    }
}

/**
 * @return array<string, int>
 */
function parseMetrics(DOMElement $metricsNode): array
{
    $ret = zeroMetrics();

    foreach (array_keys($ret) as $key) {
        if ($metricsNode->hasAttribute($key)) {
            $ret[$key] = (int)$metricsNode->getAttribute($key);
        }
    }

    return $ret;
}

/**
 * @param array<string, int> $metrics
 */
function writeMetrics(DOMElement $metricsNode, array $metrics): void
{
    foreach ($metrics as $key => $value) {
        $metricsNode->setAttribute($key, (string)$value);
    }
}

function getOrCreateMetricsNode(DOMDocument $doc, DOMElement $parent): DOMElement
{
    foreach ($parent->getElementsByTagName('metrics') as $metrics) {
        if ($metrics->parentNode === $parent) {
            return $metrics;
        }
    }

    $node = $doc->createElement('metrics');
    $parent->appendChild($node);

    return $node;
}

function xpathStringLiteral(string $value): string
{
    if (!str_contains($value, "'")) {
        return "'" . $value . "'";
    }

    if (!str_contains($value, '"')) {
        return '"' . $value . '"';
    }

    $parts = explode("'", $value);

    return "concat('" . implode("',\"'\",'", $parts) . "')";
}
