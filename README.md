# XML

[![Build Status](https://travis-ci.org/Logifire/xml.svg?branch=master)](https://travis-ci.org/Logifire/xml)

Example:

```php
$xml = <<<XML
<root xmlns="https://example.org">
    <string attribute="first">Hello World</string>
    <int>100</int>
    <string attribute="second">Hello Internet</string>
</root>
XML;

$namespace = 'https://example.org';
$prefix = 'p';

$reader = Reader::create($xml, $namespace, $prefix);

// Absolute path
$reader->hasNode('/p:root'); // true

$reader->getString('/p:root/p:string[@attribute="first"]'); // Hello World

$reader->getInt('/p:root/p:int'); // 100

// Relative path
$collection = $reader->getCollection('/p:root/p:string');
$string_reader = $collection[0];

$string_reader->getName(); // string
$string_reader->getString('@attribute'); // first
```
