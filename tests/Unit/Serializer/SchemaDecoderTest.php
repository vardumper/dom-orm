<?php

declare(strict_types=1);

use DOM\ORM\Serializer\Encoder\SchemaDecoder;

it('supportsDecoding returns true only for the dom_orm_schema format', function (): void {
    $decoder = new SchemaDecoder();
    expect($decoder->supportsDecoding('dom_orm_schema'))->toBeTrue()
        ->and($decoder->supportsDecoding('json'))->toBeFalse();
});

it('decode with a valid XML string returns a structured array', function (): void {
    $decoder = new SchemaDecoder();
    $xml = '<data><item type="tag" id="abc123"><fragment name="name"><![CDATA[TestTag]]></fragment></item></data>';
    $result = $decoder->decode($xml, 'dom_orm_schema');
    expect($result)->toBeArray()->toHaveKey('data');
    expect($result['data'])->toHaveLength(1);
});

it('decode throws for a non-XML string', function (): void {
    $decoder = new SchemaDecoder();

    \set_error_handler(static fn () => true, E_WARNING);

    try {
        expect(fn () => $decoder->decode('not xml at all', 'dom_orm_schema'))
            ->toThrow(\InvalidArgumentException::class);
    } finally {
        \restore_error_handler();
    }
});

it('decode throws when the XML does not comply with the schema', function (): void {
    $decoder = new SchemaDecoder();
    $xml = '<data><item></item></data>';

    \set_error_handler(static fn () => true, E_WARNING);

    try {
        expect(fn () => $decoder->decode($xml, 'dom_orm_schema'))
            ->toThrow(\InvalidArgumentException::class);
    } finally {
        \restore_error_handler();
    }
});

it('decode skips non-element children in a data document', function (): void {
    $decoder = new SchemaDecoder();
    $xml = '<data><!-- comment --><item type="tag" id="abc123"><fragment name="name"><![CDATA[TestTag]]></fragment></item></data>';
    $result = $decoder->decode($xml, 'dom_orm_schema');
    expect($result)->toBeArray()->toHaveKey('data');
    expect($result['data'])->toHaveLength(1);
});

it('decode skips non-element children in a group document', function (): void {
    $decoder = new SchemaDecoder();
    $xml = '<group type="comments"><!-- comment --><item type="comment" id="c1"><fragment name="body"><![CDATA[Hi]]></fragment></item></group>';
    $result = $decoder->decode($xml, 'dom_orm_schema');
    expect($result)->toBeArray()->toHaveKey('comments');
    expect($result['comments'])->toHaveLength(1);
});
