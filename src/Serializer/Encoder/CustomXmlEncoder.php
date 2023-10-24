<?php

declare(strict_types=1);

namespace DOM\ORM\Serializer\Encoder;

use DOMDocument;
use DOMNode;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

class GroupItemFragmentXmlEncoder implements EncoderInterface, DecoderInterface
{
    public const FORMAT = 'group_item_fragment_xml';

    protected DOMDocument $dom;

    public function __construct()
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $this->dom = $dom;
    }

    public function supportsDecoding(string $format): bool
    {
        return self::FORMAT === $format;
    }

    public function supportsEncoding(string $format): bool
    {
        return self::FORMAT === $format;
    }

    public function encode($data, string $format, array $context = []): string
    {
        // Node
        $elementName = array_keys($data)[0]; // root key is element name

        // Parent (create root node)
        $node = $this->dom->createElement($elementName);
        $parentNode = ($this->dom->documentElement === null) ? $this->dom : $this->dom->documentElement;
        if (isset($context['parentNode']) && $context['parentNode'] instanceof DOMNode) {
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
        return $this->dom->saveXML($this->dom->documentElement);
    }

    public function decode(string $data, string $format, array $context = []): array
    {
        return $this->parseXml($data);
    }

    private function parseXml(string $data): array
    {
        return [];
    }
}
