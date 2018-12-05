<?php

use Logifire\XML\Exception\WriterException;
use Logifire\XML\Reader;
use Logifire\XML\Writer;
use PHPUnit\Framework\TestCase;

class WriterTest extends TestCase
{

    public const NAMESPACE = 'http://www.example.org/book';
    public const BOOKSTORE = '/t:bookstore';
    public const PREFIX = 't';

    private function getXML(): string
    {
        return file_get_contents(__DIR__ . '/data/sample.xml');
    }

    public function testWrongXML()
    {
        $this->expectExceptionCode(WriterException::INVALID_XML);
        Writer::create('wrong-xml');
    }

    public function testWrongNamespace()
    {
        $this->expectExceptionCode(WriterException::INVALID_NAMESPACE);
        Writer::create('<b:bookstore xmlns:b="wrong-namespace"/>', self::NAMESPACE);
    }

    public function testAddNode()
    {
        $xml = $this->getXML();
        $writer = Writer::create($xml, self::NAMESPACE, self::PREFIX);

        // Add single node
        $node_book = 'book';
        $book_attr = [
            'category' => 'programming',
        ];

        $node_title = 'title';
        $title_attr = [
            'lang' => 'en',
        ];
        $title = 'Test matters';

        $writer->addNode(self::BOOKSTORE, $node_book, $book_attr);

        $node_book_path = self::BOOKSTORE . "/t:book[@category='programming']";

        $node_title_path = $node_book_path . "/t:{$node_title}";
        $writer->addNode($node_book_path, $node_title, $title_attr, $title);
        $new_xml = $writer->asXML();

        $reader = Reader::create($new_xml, self::NAMESPACE, self::PREFIX);
        $this->assertTrue($reader->hasNode($node_book_path), 'Has written the node, with correct attribute');
        $this->assertSame($title, $reader->getString($node_title_path), 'Is correct value');
    }

    public function testRemoveNode()
    {
        $xml = $this->getXML();
        $writer = Writer::create($xml, self::NAMESPACE, self::PREFIX);

        $book_cooking = self::BOOKSTORE . "/t:book[@category='cooking']";

        $reader = Reader::create($xml, self::NAMESPACE, self::PREFIX);

        $this->assertTrue($reader->hasNode($book_cooking), 'Node exists');

        $writer->removeNode($book_cooking);

        $new_xml = $writer->asXML();

        $reader = Reader::create($new_xml, self::NAMESPACE, self::PREFIX);

        $this->assertFalse($reader->hasNode($book_cooking), 'Node is removed');
    }
}
