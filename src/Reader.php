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
            throw new ReaderException($namespace . ' is not declared in the document.', ReaderException::INVALID_NAMESPACE);
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

    public function hasNamespace(string $namespace): bool
    {
        $namespaces = $this->xml->getNamespaces(true);
        return in_array($namespace, $namespaces);
    }

    /**
     * You should call the hasNamespace method before calling this.
     * @see self::hasNamespace()
     */
    public function registerNamespace(string $namespace, string $prefix): void
    {
        if (!$this->hasNamespace($namespace)) {
            throw new ReaderException($namespace . ' is not declared in the document.', ReaderException::INVALID_NAMESPACE);
        }

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
            $code = $xpath ? ReaderException::PATH_NOT_FOUND : ReaderException::NOT_A_VALUE;
            throw new ReaderException($msg, $code);
        }

        if (count($nodes) > 1) {
            throw new ReaderException("Path: \"{$xpath}\" is ambiguous. Multiple nodes exists.", ReaderException::AMBIGUOUS_PATH);
        }

        $node = $nodes[0];

        $has_children = $this->hasChildren($node);

        if ($has_children) {
            $msg = $xpath ? "Path: \"{$xpath}\" is not a leaf node." : 'This is not a leaf node';
            throw new ReaderException($msg, ReaderException::NOT_A_LEAF_NODE);
        }

        return $node;
    }

    /**
     * 2019-03-11
     * According to the PHP documentation http://php.net/manual/en/simplexmlelement.children.php, this cannot be null.
     * But if you use xpath, and select an attribute, this will return null
     */
    private function hasChildren(SimpleXMLElement $node): bool
    {

        $has_children = false;
        $namespaces = $node->getNamespaces() ?: $node->getDocNamespaces();
        $namespaces[] = null; // If no namespace is present

        foreach ($namespaces as $namespace) {
            $children = $node->children($namespace);
            if ($children === null) {
                // Not a node, can't have children
                $has_children = false;
                break;
            }
            if (count($children) > 0) {
                $has_children = true;
                break;
            }
        }

        return $has_children;
    }

    private function trim(string $text): string
    {
        $text = preg_replace('/^\s+/u', '', $text);
        $text = preg_replace('/\s+$/u', '', $text);

        return $text;
    }
}
