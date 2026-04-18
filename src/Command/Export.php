<?php

declare(strict_types=1);

namespace DOM\ORM\Command;

use DOM\ORM\Storage\StorageService;
use Symfony\Component\Yaml\Yaml;
use function DOM\ORM\getConfig;

class Export
{
    /**
     * Export the XML data store to one or more formats.
     *
     * Each output format may be:
     *  - true/false  → write to auto-named file next to the source XML
     *  - a string    → write to the given file path
     *  - false/null  → skip this format
     *
     * Exported shape:
     *   { "user": [ {"id": "…", "type": "user", "name": "…", …}, … ], … }
     *
     * @param string|null $file Base output path (without extension). Defaults to storage dir.
     */
    public static function run(
        ?string $file,
        bool|string $xml,
        bool|string $yaml,
        bool|string $json,
        bool|string $php,
    ): void {
        $storage = StorageService::fromConfig();
        $rawXml = $storage->read();

        $dom = new \DOMDocument('1.0', 'UTF-8');
        if (!$dom->loadXML($rawXml)) {
            throw new \RuntimeException('Failed to parse the XML data file.');
        }

        self::write($file, $xml, $yaml, $json, $php, $dom, $rawXml);
    }

    public static function runConfigured(\DOMDocument $dom, string $rawXml): void
    {
        /** @var array{file:?string, xml:bool|string, yaml:bool|string, json:bool|string, php:bool|string} $options */
        $options = getConfig()->get('dom-orm.export_on_persist');

        if (
            $options['xml'] === false
            && $options['yaml'] === false
            && $options['json'] === false
            && $options['php'] === false
        ) {
            return;
        }

        self::write(
            $options['file'],
            $options['xml'],
            $options['yaml'],
            $options['json'],
            $options['php'],
            $dom,
            $rawXml,
        );
    }

    public static function write(
        ?string $file,
        bool|string $xml,
        bool|string $yaml,
        bool|string $json,
        bool|string $php,
        \DOMDocument $dom,
        string $rawXml,
    ): void {

        $data = self::buildExportArray($dom);

        $basePath = $file ?? self::defaultBasePath();

        if ($json !== false) {
            $dest = \is_string($json) ? $json : $basePath . '.json';
            self::writeFile($dest, \json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));
        }

        if ($yaml !== false) {
            $dest = \is_string($yaml) ? $yaml : $basePath . '.yaml';
            self::writeFile($dest, Yaml::dump($data, 4, 2));
        }

        if ($php !== false) {
            $dest = \is_string($php) ? $php : $basePath . '.php';
            $exported = \var_export($data, true);
            self::writeFile($dest, "<?php\n\nreturn {$exported};\n");
        }

        if ($xml !== false) {
            $dest = \is_string($xml) ? $xml : $basePath . '.xml';
            self::writeFile($dest, $rawXml);
        }
    }

    /**
     * Build a clean export array, grouped by entity type.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function buildExportArray(\DOMDocument $dom): array
    {
        $xpath = new \DOMXPath($dom);
        /** @var \DOMNodeList<\DOMNode> $items */
        $items = $xpath->query('//item') ?: new \DOMNodeList();

        $result = [];
        foreach ($items as $item) {
            if (!$item instanceof \DOMElement) {
                continue;
            }

            $id = $item->getAttribute('id');
            $type = $item->getAttribute('type');

            if ($id === '' || $type === '') {
                continue;
            }

            $row = [
                'id' => $id,
                'type' => $type,
            ];

            foreach ($item->childNodes as $child) {
                if (!$child instanceof \DOMElement || $child->nodeName !== 'fragment') {
                    continue;
                }
                $name = $child->getAttribute('name');
                // Omit internal searchable-hash meta — export only the (possibly encrypted) value.
                $row[$name] = $child->nodeValue;
            }

            $result[$type][] = $row;
        }

        return $result;
    }

    private static function defaultBasePath(): string
    {
        return \getcwd() . '/storage/export';
    }

    private static function writeFile(string $path, string $contents): void
    {
        $dir = \dirname($path);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0755, true);
        }

        \file_put_contents($path, $contents);
    }
}
