<?php

declare(strict_types=1);

namespace DOM\ORM\Serializer\Encoder;

use Symfony\Component\Serializer\Encoder\EncoderInterface;

class SchemaEncoder implements EncoderInterface
{
    public const FORMAT = 'dom_orm_schema';

    protected \DOMDocument $dom;

    public function __construct()
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $this->dom = $dom;
    }

    public function supportsEncoding(string $format): bool
    {
        return $format === self::FORMAT;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function encode(mixed $data, ?string $format = null, array $context = []): string
    {
        if (!\is_array($data)) {
            throw new \InvalidArgumentException('Only arrays are supported.');
        }
        $elementName = 'item';
        $elementKey = \array_keys($data)[0];

        $node = $this->dom->createElement($elementName);
        $parentNode = ($this->dom->documentElement === null) ? $this->dom : $this->dom->documentElement;

        if (isset($context['parentNode']) && $context['parentNode'] instanceof \DOMNode) {
            $parentNode = $context['parentNode'];
        }

        foreach ($data[$elementKey] as $key => $value) {
            if (\strpos($key, '@') === 0) {
                $node->setAttribute(\substr($key, 1), $value);
            } else {
                if (\is_iterable($value)) {
                    $group = $this->dom->createElement('group');
                    $group->setAttribute('type', $key);

                    $context['parentNode'] = $group;
                    foreach ($value as $element) {
                        $this->encode($element, $format, $context);
                    }
                    $node->appendChild($group);
                }

                if (\is_string($value)) {
                    $cdataSection = $this->dom->createCDATASection($value);
                    $fragment = $this->dom->createElement('fragment');
                    $fragment->appendChild(
                        $cdataSection
                    );
                    $fragment->setAttribute('name', $key);
                    $node->appendChild($fragment);
                }
            }
        }
        $parentNode->appendChild($node);

        return $this->dom->saveXML();
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function decode(string|\DOMDocument|\DOMElement|\DOMNode $data, string $format, array $context = []): mixed
    {
        $return = null;

        if (\is_string($data)) {
            $isXml = (\simplexml_load_string($data) !== false);
            if (!$isXml) {
                throw new \UnexpectedValueException('Only XML strings are supported or DOMDocument is supported.');
            }
            $xml = $data;
            $data = new \DOMDocument();
            $data->loadXML($xml);
        }

        if ($data instanceof \DOMDocument) {
            if (!$data->schemaValidate(__DIR__ . '/../../Resources/schema/schema.xsd')) {
                throw new \InvalidArgumentException('The XML document does not comply to the schema.xsd');
            }
        }

        if ($data instanceof \DOMElement || ($data instanceof \DOMNode && !$data instanceof \DOMDocument)) {
            $xml = $data;
            $data = new \DOMDocument();
            $importedNode = $data->importNode($xml, true);
            $data->appendChild($importedNode);
        }

        $rootNodeName = $data->documentElement->nodeName;

        match ($rootNodeName) {
            'data' => $return = $this->decodeData($data, $format, $context),
            'group' => $return = $this->decodeGroup($data, $format, $context),
            'item' => $return = $this->decodeItem($data, $format, $context),
            'fragment' => $return = $this->decodeFragment($data, $format, $context),
            default => throw new \InvalidArgumentException(\sprintf('Unsopperted element %s given. Supported elements are data, group, item and fragment.', $rootNodeName)),
        };

        return $return;
    }

    /**
     * @param array<string, mixed> $context
     * @return array{data: list<array<string, mixed>|null>}
     */
    private function decodeData(\DOMDocument $data, string $format, array $context): array
    {
        $tmp = [];
        foreach ($data->documentElement->childNodes as $child) {
            $tmp[] = $this->decode($child, $format, $context);
        }

        return [
            'data' => $tmp,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, list<array<string, mixed>>>
     */
    private function decodeGroup(\DOMDocument $data, string $format, array $context): array
    {
        $groupType = $data->documentElement->getAttribute('type');
        $groupItems = [];
        foreach ($data->documentElement->childNodes as $child) {
            $decoded = $this->decode($child, $format, $context);
            if (\is_array($decoded)) {
                $groupItems[] = $decoded;
            }
        }

        return [
            $groupType => $groupItems,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, array<string, mixed>>
     */
    private function decodeItem(\DOMDocument $data, string $format, array $context): array
    {
        $id = $data->documentElement->getAttribute('id');

        $itemData = [
            '@id' => $id,
            '@type' => $data->documentElement->getAttribute('type'),
        ];

        foreach ($data->documentElement->childNodes as $child) {
            $decoded = $this->decode($child, $format, $context);
            if (\is_array($decoded)) {
                $itemData = \array_merge($itemData, $decoded);
            }
        }

        return [
            'item-' . $id => $itemData,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    private function decodeFragment(\DOMDocument $data, string $format, array $context): array
    {
        $name = $data->documentElement->getAttribute('name');
        $value = $data->documentElement->nodeValue;

        return [
            $name => $value,
        ];
    }
}
