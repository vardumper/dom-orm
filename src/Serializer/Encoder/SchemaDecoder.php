<?php

declare(strict_types=1);

namespace DOM\ORM\Serializer\Encoder;

use Symfony\Component\Serializer\Encoder\DecoderInterface;

class SchemaDecoder implements DecoderInterface
{
    public const FORMAT = 'dom_orm_schema';

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

    /**
     * Decodes an entire XML Document or single DOMElement nodes into an array in a format that can be used for serialization.
     * @throws \InvalidArgumentException
     */
    public function decode(string|\DOMDocument|\DOMNodeList|\DOMElement $data, string $format, array $context = []): ?array
    {
        $return = null;

        // if a XML string is passed, load it into an empty DOM
        if (\is_string($data)) {
            $isXml = (\simplexml_load_string($data) !== false);
            if (!$isXml) {
                throw new \InvalidArgumentException('Only XML strings are supported or DOMDocument is supported.');
            }
            $xml = $data;
            $data = new \DOMDocument();
            $data->loadXML($xml);
        }

        // Make sure that $data is DOMDocument, DOMNodeList or DOMElement
        if (!$data instanceof \DOMDocument && !$data instanceof \DOMElement && !$data instanceof \DOMElement) {
            throw new \InvalidArgumentException('Only an XML string, a DOMNodeList, DOMElement or DOMDocument is supported.');
        }

        // If a DOMDocument is passed, validate against XSD
        if ($data instanceof \DOMDocument) {
            // only run schea validation against DOMDocument
            if (!$data->schemaValidate(__DIR__ . '/../../Resources/schema/schema.xsd')) {
                throw new \InvalidArgumentException('The XML document does not comply to the schema.xsd');
            }
        }

        // If a DOMElement is passed, load into empty DOM
        if ($data instanceof \DOMElement) {
            $xml = $data;
            $data = new \DOMDocument();
            $importedNode = $data->importNode($xml, true);
            $data->appendChild($importedNode);
        }

        // If a DOMNodelist is passed, load them into empty DOM
        if ($data instanceof \DOMNodeList) {
            $data = new \DOMDocument();
            $data->loadXML('<data/>');
            foreach ($data as $node) {
                $importedNode = $data->importNode($node, true);
                $data->documentElement->appendChild($importedNode);
            }
        }

        $rootNodeName = $data->documentElement->nodeName;

        match ($rootNodeName) {
            'data' => $return = $this->decodeData($data, $format, $context),
            'group' => $return = $this->decodeGroup($data, $format, $context),
            'item' => $return = $this->decodeItem($data, $format, $context),
            'fragment' => $return = $this->decodeFragment($data, $format, $context),
            default => throw new \InvalidArgumentException(sprintf('Unsopperted element %s given. Supported elements are data, group, item and fragment.', $rootNodeName)),
        };

        return $return;
    }

    private function decodeData($data, $format, $context): ?array
    {
        $tmp = [];
        foreach ($data->documentElement->childNodes as $child) {
            $tmp[] = $this->decode($child, $format, $context);
        }

        return [
            'data' => $tmp,
        ];
    }

    private function decodeGroup($data, $format, $context): ?array
    {
        $groupType = $data->documentElement->getAttribute('type');
        $groupItems = [];
        foreach ($data->documentElement->childNodes as $child) {
            $groupItems = $this->decode($child, $format, $context);
        }

        return [
            $groupType => $groupItems,
        ];
    }

    private function decodeItem($data, $format, $context): ?array
    {
        $id = $data->documentElement->getAttribute('id');

        $itemData = [
            '@id' => $id,
            '@type' => $data->documentElement->getAttribute('type'),
            // '@class' => $data->documentElement->getAttribute('class'), not really needed, we find the class by its attribute
        ];

        // merge each child node into itemData
        foreach ($data->documentElement->childNodes as $child) {
            $itemData = array_merge($itemData, $this->decode($child, $format, $context));
        }

        return [
            'item-' . $id => $itemData,
        ];
    }

    private function decodeFragment($data, $format, $context): ?array
    {
        $name = $data->documentElement->getAttribute('name');
        $value = $data->documentElement->nodeValue;

        return [
            $name => $value,
        ];
    }
}
