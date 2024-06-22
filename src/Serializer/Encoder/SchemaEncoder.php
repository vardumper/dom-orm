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

    public function encode($data, string $format = null, array $context = []): string
    {
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
}
