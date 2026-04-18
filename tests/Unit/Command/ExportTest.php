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
