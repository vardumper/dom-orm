<?php

declare(strict_types=1);

namespace DOM\ORM\Serializer\Encoder;

use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

class SchemaEncoder implements EncoderInterface, DecoderInterface
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

    public function supportsEncoding(string $format): bool
    {
        return $format === self::FORMAT;
    }

    public function encode($data, string $format = null, array $context = []): string
    {
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Only arrays are supported.');
        }
        // Node
        $elementName = array_keys($data)[0]; // root key is element name

        // Parent (create root node)
        $node = $this->dom->createElement($elementName);
        $parentNode = ($this->dom->documentElement === null) ? $this->dom : $this->dom->documentElement;
        if (isset($context['parentNode']) && $context['parentNode'] instanceof \DOMNode) {
            $parentNode = $context['parentNode'];
        }

        // Node attributes
        foreach ($data[$elementName] as $key => $value) {
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
                        // recursion into sub-items
                        $this->encode($element, $format, $context);
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
     * Decodes an entire XML Document or single DOMElement nodes into an array in a format that can be used for serialization.
     * @throws \InvalidArgumentException
     */
    public function decode(string|\DOMDocument|\DOMElement $data, string $format, array $context = []): array
    {
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

        // Make sure that $data is DOMDocument ot DOMElement
        if (!$data instanceof \DOMDocument && !$data instanceof \DOMElement) {
            throw new \InvalidArgumentException('Neither an XML string, DOMElement nor DOMDocument was passed.');
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

        $return = [];
        $tmp = [];

        if ($data->documentElement->hasAttribute('id')) {
            $tmp['@id'] = $data->documentElement->getAttribute('id');
        }
        if ($data->documentElement->hasAttribute('type')) {
            $tmp['@type'] = $data->documentElement->getAttribute('type');
        }

        foreach ($data->documentElement->childNodes as $child) {
            if ($child->nodeName === 'group') {
                $tmp[$child->getAttribute('type')] = [];
                foreach ($child->childNodes as $subChild) {
                    $tmp[$child->getAttribute('type')][] = $this->decode($subChild, $format, $context);
                }
            }
            if ($child->nodeName === 'item') {
                $children = [];
                foreach ($child->childNodes as $subChild) {
                    if ($subChild->nodeName === 'fragment') {
                        $children[$subChild->getAttribute('name')] = $subChild->nodeValue;

                        continue;
                    }
                    $children[] = $this->decode($subChild, $format, $context);
                }
                $tmp[$child->nodeName . '-' . $child->getAttribute('id')] = [
                    '@id' => $child->getAttribute('id'),
                    '@type' => $child->getAttribute('type'),
                ];
                $tmp = array_merge($tmp[$child->nodeName . '-' . $child->getAttribute('id')], $children);
            }
            $return[$child->nodeName . '-' . $child->getAttribute('id')] = $tmp;
        }

        return $return;
    }
}
