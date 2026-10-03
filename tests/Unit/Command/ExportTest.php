<?php

declare(strict_types=1);

use DOM\ORM\Command\Export;
use Tests\Fixtures\ExcludedFieldEntity;

it('includes non-excluded fields in the export', function (): void {
    // Ensure the fixture class is autoloaded so buildExclusionMap() can discover it.
    new ExcludedFieldEntity('Hello', 9.5, 'test-id-1');

    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <data>
          <item id="test-id-1" type="excluded_field_entity">
            <fragment name="title">Hello</fragment>
            <fragment name="internalScore">9.5</fragment>
          </item>
        </data>
        XML;

    $dom = new \DOMDocument('1.0', 'UTF-8');
    $dom->loadXML($xml);

    $result = Export::buildExportArray($dom);

    expect($result)->toHaveKey('excluded_field_entity');
    expect($result['excluded_field_entity'][0])->toHaveKey('title');
    expect($result['excluded_field_entity'][0]['title'])->toBe('Hello');
});

it('omits fields marked with #[Exclude] from the export', function (): void {
    new ExcludedFieldEntity('Hello', 9.5, 'test-id-1');

    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <data>
          <item id="test-id-1" type="excluded_field_entity">
            <fragment name="title">Hello</fragment>
            <fragment name="internalScore">9.5</fragment>
          </item>
        </data>
        XML;

    $dom = new \DOMDocument('1.0', 'UTF-8');
    $dom->loadXML($xml);

    $result = Export::buildExportArray($dom);

    expect($result['excluded_field_entity'][0])->not->toHaveKey('internalScore');
});

it('skips non-element nodes and items with an empty id or type', function (): void {
    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <data>
          <item id="" type="user"><fragment name="name">NoId</fragment></item>
          <item id="u2" type=""><fragment name="name">NoType</fragment></item>
          <item id="u3" type="user"><fragment name="name">Alice</fragment></item>
        </data>
        XML;
    $dom = new \DOMDocument('1.0', 'UTF-8');
    $dom->loadXML($xml);

    $result = Export::buildExportArray($dom);
    expect($result['user'])->toHaveCount(1);
    expect($result['user'][0]['id'])->toBe('u3');
});

it('writes every enabled export format to disk', function (): void {
    $xml = '<data><item id="u1" type="user"><fragment name="name">Alice</fragment></item></data>';
    $dom = new \DOMDocument('1.0', 'UTF-8');
    $dom->loadXML($xml);
    $base = \sys_get_temp_dir() . '/dom-orm-export-' . \uniqid();

    Export::write($base, true, true, true, true, $dom, $xml);

    foreach (['.json', '.yaml', '.php', '.xml'] as $ext) {
        expect(\file_exists($base . $ext))->toBeTrue();
    }
    expect(\file_get_contents($base . '.xml'))->toBe($xml);

    foreach (['.json', '.yaml', '.php', '.xml'] as $ext) {
        \unlink($base . $ext);
    }
});

it('run exports the configured storage file', function (): void {
    $storageFile = \getcwd() . '/storage/data.xml';
    $backup = $storageFile . '.expbak';
    $hadStorage = \file_exists($storageFile);
    if ($hadStorage) {
        \rename($storageFile, $backup);
    }
    if (!\is_dir(\dirname($storageFile))) {
        \mkdir(\dirname($storageFile), 0755, true);
    }
    \file_put_contents($storageFile, '<data><item id="u1" type="user"><fragment name="name">Alice</fragment></item></data>');

    $base = \sys_get_temp_dir() . '/dom-orm-export-run-' . \uniqid();

    try {
        Export::run($base, true, true, true, true);
        foreach (['.json', '.yaml', '.php', '.xml'] as $ext) {
            expect(\file_exists($base . $ext))->toBeTrue();
        }
    } finally {
        if (\file_exists($storageFile)) {
            \unlink($storageFile);
        }
        if (\file_exists($backup)) {
            \rename($backup, $storageFile);
        }
        foreach (['.json', '.yaml', '.php', '.xml'] as $ext) {
            if (\file_exists($base . $ext)) {
                \unlink($base . $ext);
            }
        }
    }
});
