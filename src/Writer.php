<?php
namespace Logifire\XML;

use Exception;
use Logifire\XML\Exception\WriterException;
use SimpleXMLElement;

class Writer
{

    /**
     * @var SimpleXMLElement
     */
    private $xml;

    /**
     * @var string|null
     */
    static $namespace;

    /**
     * @var string|null
     */
    static $prefix;

    private function __construct(SimpleXMLElement $xml)
    {
        if (self::$namespace && self::$prefix) {
            $xml->registerXPathNamespace(self::$prefix, self::$namespace);
        }

        $this->xml = $xml;
    }

    public static function create(string $xml, ?string $namespace = null, ?string $prefix = null): Writer
    {
        try {
            $simple_xml_element = new SimpleXMLElement($xml);
        } catch (Exception $e) {
            throw new WriterException($e->getMessage(), WriterException::INVALID_XML);
        }

        if ($namespace && !in_array($namespace, $simple_xml_element->getDocNamespaces())) {
            throw new WriterException(
                $namespace . ' is not declared in the document.',
                WriterException::INVALID_NAMESPACE);
        }

        self::$namespace = $namespace;
        self::$prefix = $prefix;

        return new self($simple_xml_element);
    }

    /**
     * NOTE: By design, this does not support prefixed attributes.
     *
     * @link https://www.w3.org/TR/REC-xml-names/#defaulting
     *      A default namespace declaration applies to all unprefixed
     *      element names within its scope. Default namespace declarations
     *      do not apply directly to attribute names; the interpretation of
     *      unprefixed attributes is determined by the element on which they appear.
     *
     * @param string        $xpath Use 'ncs' as prefix for selection. e.g. /ncs:story
     * @param string        $name  Element name, without prefix
     * @param string[]|null $attr  ['name' => 'value']
     * @param string|null   $value Simple value, text or numeric
     */
    public function addNode(string $xpath, string $name, ?array $attr = null, ?string $value = null): void
    {
        $nodes = $this->getNodes($xpath);

        if (empty($nodes)) {
            throw new WriterException("Path: \"{$xpath}\" not found.");
        }

        foreach ($nodes as $node) {
            $child = $node->addChild($name, $value, self::$namespace);

            if (is_array($attr)) {
                foreach ($attr as $attr_name => $attr_value) {
                    $child->addAttribute($attr_name, $attr_value);
                }
            }
        }
    }

    public function removeNode(string $xpath): void
    {
        $nodes = $this->getNodes($xpath);
        foreach ($nodes as $node) {
            $dom = dom_import_simplexml($node);
            $dom->parentNode->removeChild($dom);
        }
    }

    /**
     * @param string $xpath
     *
     * @return SimpleXMLElement[]
     */
    private function getNodes(string $xpath): array
    {
        $nodes = @$this->xml->xpath($xpath);

        if ($nodes === false) {
            throw new WriterException("Invalid path: \"{$xpath}\".", WriterException::INVALID_PATH);
        }

        return $nodes;
    }

    /**
     * NOTE: Returns the XML with the orginal namespace and prefix.
     *
     * @return string
     */
    public function asXML(): string
    {
        return $this->xml->asXML();
    }
}
