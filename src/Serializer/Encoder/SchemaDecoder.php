<?php

declare(strict_types=1);

namespace DOM\ORM\Serializer\Encoder;

use Symfony\Component\Serializer\Encoder\DecoderInterface;

class SchemaDecoder implements DecoderInterface
{
    private const FORMAT = 'dom_orm_schema';

    protected \DOMDocument $dom;

    public function __construct()
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $this->dom = $dom;
    }

    public function supportsDecoding(string $format): bool
    {
        return $format === self::FORMAT;
    }

    public function decode(string|\DOMDocument|\DOMNodeList|\DOMElement|\DOMNode $data, string $format, array $context = []): ?array
    {
        $return = null;

        if (\is_string($data)) {
            $isXml = (\simplexml_load_string($data) !== false);
            if (!$isXml) {
                throw new \InvalidArgumentException('If you pass a string, it must be XML.');
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

        if ($data instanceof \DOMElement || $data instanceof \DOMNode) {
            $xml = $data;
            $data = new \DOMDocument();
            $importedNode = $data->importNode($xml, true);
            $data->appendChild($importedNode);
        }

        if ($data instanceof \DOMNodeList) {
            $nodes = $data;
            $data = new \DOMDocument();
            $data->loadXML('<data/>');
            foreach ($nodes as $node) {
                $importedNode = $data->importNode($node, true);
                $data->documentElement->appendChild($importedNode);
            }
        }

        $rootNodeName = $data->documentElement->nodeName;
        $return = match ($rootNodeName) {
            'data' => $this->decodeData($data, $format, $context),
            'group' => $this->decodeGroup($data, $format, $context),
            'item' => $this->decodeItem($data, $format, $context),
            'fragment' => $this->decodeFragment($data, $format, $context),
            default => throw new \InvalidArgumentException(\sprintf('Unsopperted element %s given. Supported elements are data, group, item and fragment.', $rootNodeName)),
        };

        return $return;
    }

    private function decodeData(\DOMDocument $data, string $format, array $context): ?array
    {
        $tmp = [];
        foreach ($data->documentElement->childNodes as $child) {
            $tmp[] = $this->decode($child, $format, $context);
        }

        return [
            'data' => $tmp,
        ];
    }

    private function decodeGroup(\DOMDocument $data, string $format, array $context): ?array
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

    private function decodeItem(\DOMDocument $data, string $format, array $context): ?array
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

    private function decodeFragment(\DOMDocument $data, string $format, array $context): ?array
    {
        $name = $data->documentElement->getAttribute('name');
        $value = $data->documentElement->nodeValue;

        return [
            $name => $value,
        ];
    }
}
