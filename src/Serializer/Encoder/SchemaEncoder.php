<?php

declare(strict_types=1);

namespace DOM\ORM\Serializer\Encoder;

use Symfony\Component\Serializer\Encoder\DecoderInterface;
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
     * Encodes an array of data into a XML string.
     * @param mixed  $data    Data to encode
     * @param string $format  Format name
     * @param array  $context Options that normalizers/encoders have access to
     * @throws \InvalidArgumentException
     */
    public function encode(mixed $data, string $format = null, array $context = []): string
    {
        // we only support arrays here
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Only arrays are supported.');
        }
        // Node
        $elementName = 'item'; // defaults to item
        $elementKey = array_keys($data)[0];

        // Parent (create root node)
        $node = $this->dom->createElement($elementName);
        $parentNode = ($this->dom->documentElement === null) ? $this->dom : $this->dom->documentElement;

        if (isset($context['parentNode']) && $context['parentNode'] instanceof \DOMNode) {
            $parentNode = $context['parentNode'];
        }

        // Node attributes
        foreach ($data[$elementKey] as $key => $value) {
            if (strpos($key, '@') === 0) {
                /** @todo validate key */
                /** @todo validate value */
                $node->setAttribute(substr($key, 1), $value);
            } else {
                if (is_iterable($value)) {
                    // Groups
                    $group = $this->dom->createElement('group');
                    $group->setAttribute('type', $key);

                    $context['parentNode'] = $group;
                    foreach ($value as $element) {
                        $this->encode($element, $format, $context); // recursion into sub-items
                    }
                    $node->appendChild($group);
                }

                if (is_string($value)) {
                    // Fragments
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
        // append child
        $parentNode->appendChild($node);

        // convert to XML string and return
        return $this->dom->saveXML();
    }

    /**
     * Decodes an entire XML Document or single DOMElement nodes into an array, which servers to de-serialize it back to objects.
     * /**
     * Decodes a string into PHP data.
     *
     * @param string $data    Data to decode
     * @param string $format  Format name
     * @param array  $context Options that decoders have access to
     *
     * @throws \InvalidArgumentException
     */
    public function decode(string|\DOMDocument|\DOMElement $data, string $format, array $context = []): mixed
    {
        $return = null;

        // if a XML string is passed, load it into an empty DOM
        if (\is_string($data)) {
            $isXml = (\simplexml_load_string($data) !== false);
            if (!$isXml) {
                throw new \UnexpectedValueException('Only XML strings are supported or DOMDocument is supported.');
            }
            $xml = $data;
            $data = new \DOMDocument();
            $data->loadXML($xml);
        }

        // Make sure that $data is DOMDocument or DOMElement
        if (!$data instanceof \DOMDocument && !$data instanceof \DOMElement) {
            throw new \InvalidArgumentException('Only an XML string, a DOMElement or a DOMDocument is supported.');
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
