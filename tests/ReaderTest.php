<?php

use Logifire\XML\Reader;
use PHPUnit\Framework\TestCase;

class ReaderTest extends TestCase {

    public const NAMESPACE = 'http://www.example.org/book';
    
    public const BOOKSTORE = '/t:bookstore';
    public const BOOK = self::BOOKSTORE . '/t:book';
    public const FIRST_BOOK_CATEGORY = self::BOOK . '[1]/@category';
    public const FIRST_BOOK_YEAR = self::BOOK . '[1]/t:year';

    public function testReader() {
        $xml = file_get_contents(__DIR__ . '/data/sample.xml');
        $reader = Reader::create($xml, self::NAMESPACE, 't');
        $this->assertTrue($reader->hasNode(self::BOOKSTORE));

        $collection = $reader->getCollection(self::BOOK);
        $this->assertCount(4, $collection, 'Three books found');

        /* @var $book Reader */
        $book = $collection[0];
        $this->assertSame('cooking', $book->getString('@category'), 'Get attribute via relative path from collection');
        $this->assertSame('cooking', $reader->getString(self::FIRST_BOOK_CATEGORY), 'Get attribute');

        $this->assertSame(2005, $reader->getInt(self::FIRST_BOOK_YEAR));
    }

}
