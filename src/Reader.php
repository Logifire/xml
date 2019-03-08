<?php
declare(strict_types=1);
namespace Logifire\XML;

use Exception;
use Logifire\XML\Exception\ReaderException;
use SimpleXMLElement;

final class Reader
{

    /**
     * @var SimpleXMLElement
     */
    private $xml;

    private function __construct(SimpleXMLElement $xml)
    {

        $this->xml = $xml;
    }

    public static function create(string $xml, ?string $namespace = null, ?string $prefix = null): Reader
    {
        try {
            $simple_xml_element = new SimpleXMLElement($xml);
        } catch (Exception $e) {
            throw new ReaderException($e->getMessage());
        }

        if ($namespace && !in_array($namespace, $simple_xml_element->getDocNamespaces())) {
            throw new ReaderException($namespace . ' is not declared in the document.');
        }

        if ($namespace && !$prefix) {
            throw new ReaderException("Missing prefix for {$namespace}");
        }

        if ($namespace && $prefix) {
            $simple_xml_element->registerXPathNamespace($prefix, $namespace);
        }

        $reader = new self($simple_xml_element);
        return $reader;
    }

    public function registerNamespace(string $namespace, string $prefix): void
    {

        $this->xml->registerXPathNamespace($prefix, $namespace);
    }

    public function hasNode(string $xpath): bool
    {
        $nodes = @$this->xml->xpath($xpath);

        if ($nodes === false) {
            throw new ReaderException("Invalid path (syntax): \"{$xpath}\"", ReaderException::INVALID_PATH);
        }

        return !empty($nodes);
    }

    public function getString(?string $xpath = null): string
    {
        $value = (string) $this->getLeafNode($xpath);

        return $this->trim($value);
    }

    public function getInt(?string $xpath = null): int
    {
        $value = (string) $this->getLeafNode($xpath);

        if (strlen($value) === 0) {
            throw new ReaderException("Path: \"{$xpath}\" is empty.");
        }

        if (!is_numeric($value)) {
            throw new ReaderException("Path: \"{$xpath}\" is not a numeric value.");
        }

        return (int) $value;
    }

    private function getLeafNode(?string $xpath): SimpleXMLElement
    {
        if ($xpath !== null) {
            $nodes = @$this->xml->xpath($xpath);
        } else {
            $nodes = $this->xml;
        }

        if ($nodes === false) {
            throw new ReaderException("Invalid path (syntax): \"{$xpath}\"", ReaderException::INVALID_PATH);
        }

        if (empty($nodes)) {
            $msg = $xpath ? "Path: \"{$xpath}\" not found." : 'Node does not contain a simple value.';
            throw new ReaderException($msg, ReaderException::PATH_NOT_FOUND);
        }

        if (count($nodes) > 1) {
            throw new ReaderException("Path: \"{$xpath}\" is ambiguous. Multiple nodes exists.", ReaderException::AMBIGUOUS_PATH);
        }

        $node = $nodes[0];

        // TODO: Namespace match 
        if ($node->children() !== null && count($node->children()) > 0) {
            $msg = $xpath ? "Path: \"{$xpath}\" is not a leaf node." : 'This is not a leaf node';
            throw new ReaderException($msg, ReaderException::NOT_A_LEAF_NODE);
        }

        return $node;
    }

    private function trim(string $text): string
    {
        $text = preg_replace('/^\s+/u', '', $text);
        $text = preg_replace('/\s+$/u', '', $text);

        return $text;
    }

    /**
     * @param string $xpath
     * @return Reader[]
     * @throws ReaderException
     */
    public function getCollection(string $xpath): array
    {
        $readers = [];
        $children = @$this->xml->xpath($xpath);

        if (empty($children)) {
            throw new ReaderException("Path: \"{$xpath}\" not found.");
        }

        foreach ($children as $child) {
            $readers[] = new self($child);
        }

        return $readers;
    }
}
