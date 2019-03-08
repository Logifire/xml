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

    private function getXML(): string
    {
        return file_get_contents(__DIR__ . '/data/sample.xml');
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

        $this->assertTrue($reader->hasNode(self::BOOKSTORE . '/identification/n:int'));

        $collection = $reader->getCollection(self::BOOKSTORE . '/identification/n:int');

        $numbers = [];

        for ($i = 1; $i <= count($collection); $i++) {
            /* @var $node Reader */
            $numbers[] = $reader->getInt(self::BOOKSTORE . "/identification/n:int[{$i}]");
        }

        $expected = [1, 2];
        $this->assertSame($expected, $numbers);
    }

    public function testRelativeNode()
    {
        $xml = $this->getXML();
        $reader = Reader::create($xml, self::NAMESPACE, self::PREFIX);
        $reader->registerNamespace('http://www.example.org/identification', 'n');
        $collection = $reader->getCollection(self::BOOKSTORE . '/identification/n:int');

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
        $this->expectExceptionCode(ReaderException::PATH_NOT_FOUND);
        $xml = $this->getXML();
        $reader = Reader::create($xml, self::NAMESPACE, self::PREFIX);
        $reader->registerNamespace('http://www.example.org/identification', 'n');
        $reader = $reader->getCollection(self::BOOKSTORE . '/identification')[0];
        $reader->getInt();
    }

    public function testRelativeNodeNotALeaf()
    {
        $this->expectExceptionCode(ReaderException::NOT_A_LEAF_NODE);
        $xml = $this->getXML();
        $reader = Reader::create($xml, self::NAMESPACE, self::PREFIX);
        $reader = $reader->getCollection(self::BOOKSTORE)[0];
        $reader->getString();
    }
}
