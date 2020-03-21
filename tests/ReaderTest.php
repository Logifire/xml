<?php

use Logifire\XML\Exception\ReaderException;
use Logifire\XML\Reader;
use PHPUnit\Framework\TestCase;

class ReaderTest extends TestCase
{

    public const NAMESPACE = 'http://www.example.org/book';
    public const BOOKSTORE = '/t:bookstore';
    public const BOOK = self::BOOKSTORE . '/t:book';
    public const FIRST_BOOK_CATEGORY = self::BOOK . '[1]/@category';
    public const FIRST_BOOK_YEAR = self::BOOK . '[1]/t:year';
    public const AUTHOUR = self::BOOK . '/t:author';
    public const PREFIX = 't';

    private function getXML(string $file_path = '/data/sample-prefixed.xml'): string
    {
        return file_get_contents(__DIR__ . $file_path);
    }

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/SVG/Namespaces_Crash_Course#Redeclaring_the_default_namespace
     */
    public function testDefaultNamespaceOverwrite()
    {
        // XPath 1.0 does not include any concept of a "default" namespace
        $xml = $this->getXML('/data/sample-default-namespace.xml');
        $reader = Reader::create($xml, 'http://www.example.org/bookstore', 'b');

        // New default
        $reader->registerNamespace('http://www.example.org/extension', 'e');

        // Prefixed nodes
        $reader->registerNamespace('http://www.example.org/second-extension', 'se');

        $this->assertTrue($reader->hasNode('/b:bookstore'));
        $this->assertTrue($reader->hasNode('/b:bookstore/e:extension/e:int'));
        $this->assertTrue($reader->hasNode('/b:bookstore/se:extension/se:int'));

        $this->assertSame(1, $reader->getInt('/b:bookstore/e:extension/e:int', 'Absolute path'));
        $this->assertSame(1, $reader->getInt('e:extension/e:int', 'Relative path from bookstore node'));
        $this->assertSame(2, $reader->getInt('/b:bookstore/se:extension/se:int'));

        // Working with child nodes

        $collection = $reader->getCollection('/b:bookstore/b:book');

        /** @var Reader $bookstore_reader */
        $bookstore_reader = $collection[0];
        $this->assertSame('book', $bookstore_reader->getName());

        $this->assertTrue($bookstore_reader->hasNamespace('http://www.example.org/extension'), 'Document namespaces are inherited');
        $this->assertTrue($bookstore_reader->hasNode('b:title'), 'Registered namespaces are inherited');
    }

    public function testReader()
    {
        $xml = $this->getXML();
        $reader = Reader::create($xml, self::NAMESPACE, self::PREFIX);
        $this->assertTrue($reader->hasNode(self::BOOKSTORE));
        $this->assertFalse($reader->hasNode(self::BOOKSTORE . '/invalid'));

        $collection = $reader->getCollection(self::BOOK);
        $this->assertCount(4, $collection, 'Three books found');

        /* @var $book Reader */
        $book = $collection[0];
        $this->assertSame(
            'cooking',
            $book->getString('@category'),
            'Get attribute via relative path from collection');
        $this->assertSame(
            'cooking',
            $reader->getString(self::FIRST_BOOK_CATEGORY),
            'Get attribute');

        $this->assertSame(2005, $reader->getInt(self::FIRST_BOOK_YEAR));
    }

    public function testInvalidSyntax()
    {
        $this->expectExceptionCode(ReaderException::INVALID_PATH);
        $xml = $this->getXML();
        $reader = Reader::create($xml, self::NAMESPACE, self::PREFIX);
        $reader->getString(self::BOOKSTORE . '@invalid');
    }

    public function testPathNotFound()
    {
        $this->expectExceptionCode(ReaderException::PATH_NOT_FOUND);
        $xml = $this->getXML();
        $reader = Reader::create($xml, self::NAMESPACE, self::PREFIX);
        $reader->getString(self::BOOKSTORE . '/invalid');
    }

    public function testAmbiguousPath()
    {
        $this->expectExceptionCode(ReaderException::AMBIGUOUS_PATH);
        $xml = $this->getXML();
        $reader = Reader::create($xml, self::NAMESPACE, self::PREFIX);
        $reader->getString(self::AUTHOUR);
    }

    public function testNotALeafNode()
    {
        $this->expectExceptionCode(ReaderException::NOT_A_LEAF_NODE);
        $xml = $this->getXML();
        $reader = Reader::create($xml, self::NAMESPACE, self::PREFIX);
        $reader->getString(self::BOOKSTORE);
    }

    public function testHasNodeInvalidSyntax()
    {
        $this->expectExceptionCode(ReaderException::INVALID_PATH);
        $xml = $this->getXML();
        $reader = Reader::create($xml, self::NAMESPACE, self::PREFIX);
        $reader->hasNode(self::BOOKSTORE . '@invalid');
    }

    public function testMultipleNamespaces()
    {
        $xml = $this->getXML();
        $reader = Reader::create($xml, self::NAMESPACE, self::PREFIX);
        $reader->registerNamespace('http://www.example.org/identification', 'n');

        $this->assertTrue($reader->hasNode(self::BOOKSTORE . '/n:identification/n:int'));

        $collection = $reader->getCollection(self::BOOKSTORE . '/n:identification/n:int');

        $numbers = [];

        for ($i = 1; $i <= count($collection); $i++) {
            /* @var $node Reader */
            $numbers[] = $reader->getInt(self::BOOKSTORE . "/n:identification/n:int[{$i}]");
        }

        $expected = [1, 2];
        $this->assertSame($expected, $numbers);
    }

    public function testRelativeNode()
    {
        $xml = $this->getXML();
        $reader = Reader::create($xml, self::NAMESPACE, self::PREFIX);
        $reader->registerNamespace('http://www.example.org/identification', 'n');
        $collection = $reader->getCollection(self::BOOKSTORE . '/n:identification/n:int');

        $numbers = [];

        /* @var $node Reader */
        foreach ($collection as $node) {
            $numbers[] = $node->getInt();
        }

        $expected = [1, 2];
        $this->assertSame($expected, $numbers);
    }

    public function testRelativeNodeNotALeafSimpleValue()
    {
        $this->expectExceptionCode(ReaderException::NOT_A_VALUE);
        $xml = $this->getXML();
        $reader = Reader::create($xml, self::NAMESPACE, self::PREFIX);
        $reader->registerNamespace('http://www.example.org/identification', 'n');
        $reader = $reader->getCollection(self::BOOKSTORE . '/n:identification')[0];
        $reader->getInt();
    }

    public function testRelativeNodeNotALeaf()
    {
        $this->expectExceptionCode(ReaderException::NOT_A_VALUE);
        $xml = $this->getXML();
        $reader = Reader::create($xml, self::NAMESPACE, self::PREFIX);
        $reader = $reader->getCollection(self::BOOKSTORE)[0];
        $reader->getString();
    }

    public function testInvalidXml()
    {
        $this->expectExceptionCode(ReaderException::INVALID_XML);

        $xml = '<broken>';

        Reader::create($xml);
    }
}
