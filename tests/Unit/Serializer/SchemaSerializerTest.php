<?php

declare(strict_types=1);

use DOM\ORM\Serializer\Encoder\SchemaDecoder;
use DOM\ORM\Serializer\Encoder\SchemaEncoder;
use DOM\ORM\Serializer\Normalizer\SchemaDenormalizer;
use DOM\ORM\Serializer\Normalizer\SchemaNormalizer;
use DOM\ORM\Serializer\SchemaSerializer;

it('delegates normalize denormalize encode and decode to inner components', function (): void {
    $normalizer = $this->createMock(SchemaNormalizer::class);
    $denormalizer = $this->createMock(SchemaDenormalizer::class);
    $encoder = $this->createMock(SchemaEncoder::class);
    $decoder = $this->createMock(SchemaDecoder::class);

    $serializer = new SchemaSerializer($normalizer, $denormalizer, $encoder, $decoder);

    $input = [
        'id' => 'article-1',
    ];
    $context = [
        'trace' => 'unit',
    ];

    $normalizer->expects($this->once())
        ->method('normalize')
        ->with($input, 'dom_orm_schema', $context)
        ->willReturn([
            'normalized' => true,
        ]);

    $denormalizer->expects($this->once())
        ->method('denormalize')
        ->with([
            'data' => [],
        ], 'array', 'dom_orm_schema', $context)
        ->willReturn([
            'denormalized' => true,
        ]);

    $encoder->expects($this->once())
        ->method('encode')
        ->with([
            'data' => [],
        ], 'dom_orm_schema', $context)
        ->willReturn('<data />');

    $decoder->expects($this->once())
        ->method('decode')
        ->with('<data />', 'dom_orm_schema', $context)
        ->willReturn([
            'data' => [],
        ]);

    expect($serializer->normalize($input, 'dom_orm_schema', $context))->toBe([
        'normalized' => true,
    ])
        ->and($serializer->denormalize([
            'data' => [],
        ], 'array', 'dom_orm_schema', $context))->toBe([
            'denormalized' => true,
        ])
        ->and($serializer->encode([
            'data' => [],
        ], 'dom_orm_schema', $context))->toBe('<data />')
        ->and($serializer->decode('<data />', 'dom_orm_schema', $context))->toBe([
            'data' => [],
        ]);
});

it('reports broad support and wildcard supported types contract', function (): void {
    $serializer = new SchemaSerializer(
        $this->createMock(SchemaNormalizer::class),
        $this->createMock(SchemaDenormalizer::class),
        $this->createMock(SchemaEncoder::class),
        $this->createMock(SchemaDecoder::class),
    );

    expect($serializer->supportsNormalization(new stdClass(), 'dom_orm_schema'))->toBeTrue()
        ->and($serializer->supportsDenormalization([], 'array', 'dom_orm_schema'))->toBeTrue()
        ->and($serializer->getSupportedTypes('dom_orm_schema'))->toBe([
            '*' => false,
        ]);
});
