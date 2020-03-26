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

    /**
     * List of namespaces in the document, defaults and prefixed.
     * 
     * @var string[]
     */
    private $document_namespaces;

    /**
     * List of user registered namespaces and prefixes in SimpleXMLElement
     * 
     * @var string[] [Prefix => (string) Namespace]
     */
    private $registered_namespaces;

    private function __construct(SimpleXMLElement $xml, array $document_namespaces)
    {

        $this->xml                   = $xml;
        $this->document_namespaces   = $document_namespaces;
        $this->registered_namespaces = [];
    }

    public static function create(string $xml, ?string $namespace = null, ?string $prefix = null): Reader
    {
        $libxml_error_init_setting = libxml_use_internal_errors(true);

        try {
            $simple_xml_element = new SimpleXMLElement($xml);
        } catch (Exception $e) {
            $errors = libxml_get_errors();

            if (!empty($errors)) {
                foreach ($errors as $libxml_error) {
                    $error_messages[] = $libxml_error->message;
                }
                $error_message = implode(', ', $error_messages);
            } else {
                $error_message = $e->getMessage();
            }
            throw new ReaderException($error_message, ReaderException::INVALID_XML, $e);
        } finally {
            libxml_use_internal_errors($libxml_error_init_setting);
        }

        if ($namespace && !$prefix) {
            throw new ReaderException("Missing prefix for {$namespace}");
        }

        $namespaces = self::getDeclaredNamespaces($xml);
        $reader     = new self($simple_xml_element, $namespaces);

        if ($namespace && $prefix) {
            $reader->registerNamespace($namespace, $prefix);
        }

        return $reader;
    }

    /**
     * Added because of issues with SimpleXMLElements recursive namespace search.
     * Gets defaults and prefixed namespaces.
     * This is based on the XML 1.1 standard definition, see link.
     * 
     * @link https://www.w3.org/TR/xml-names11/#ns-decl
     * @return array
     */
    private static function getDeclaredNamespaces(string $xml): array
    {
        $pattern = '/xmlns(?::\w+)?=["\'](?<namespace>[^"\']+)/';

        preg_match_all($pattern, $xml, $matches);

        $namespaces = $matches['namespace'] ?? [];

        $namespaces = array_flip($namespaces);

        return $namespaces;
    }

    /**
     * Checks if a namespace is recursively present in the main document
     * 
     * @param string $namespace
     * @return bool
     */
    public function hasNamespace(string $namespace): bool
    {
        return array_key_exists($namespace, $this->document_namespaces);
    }

    /**
     * Gets the name of the actual node
     * 
     * @see self::getCollection()
     */
    public function getName(): string
    {
        return $this->xml->getName();
    }

    /**
     * The actually node as XML, node tag inclusive.
     * 
     * @return string
     */
    public function asXml(): string
    {
        return $this->xml->asXML();
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

        $is_registered = $this->xml->registerXPathNamespace($prefix, $namespace);

        if ($is_registered) {
            $this->registered_namespaces[$prefix] = $namespace;
        }
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
        $readers  = [];
        $children = @$this->xml->xpath($xpath);

        if (empty($children)) {
            throw new ReaderException("Path: \"{$xpath}\" not found.");
        }

        foreach ($children as $child) {
            $reader = new self($child, $this->document_namespaces);

            // Child SimpleXMLElements do not inherit registered namespaces
            foreach ($this->registered_namespaces as $prefix => $namespace) {
                $reader->registerNamespace($namespace, $prefix);
            }

            $readers[] = $reader;
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
            $msg  = $xpath ? "Path: \"{$xpath}\" not found." : 'Node does not contain a simple value.';
            $code = $xpath ? ReaderException::PATH_NOT_FOUND : ReaderException::NOT_A_VALUE;
            throw new ReaderException($msg, $code);
        }

        if (count($nodes) > 1) {
            throw new ReaderException("Path: \"{$xpath}\" is ambiguous. Multiple nodes exists.",
                ReaderException::AMBIGUOUS_PATH);
        }

        $node = $nodes[0];

        $has_children = $this->hasChildren($node);

        if ($has_children) {
            $msg = $xpath ? "Path: \"{$xpath}\" is not a leaf node." : 'This is not a leaf node';
            $msg .= "\n\n" . mb_substr($node->asXML(), 0, 1024);
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
        $namespaces   = $node->getNamespaces() ?: $node->getDocNamespaces();
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