# XML

Example:

```php
$xml = <<<XML
<root xmlns="https://example.org">
  <string>Hello World</string>
  <int>100</int>
</root>
XML;

$namespace = 'https://example.org';
$prefix = 'p';

$reader = Reader::create($xml, $namespace, $prefix);

$reader->hasNode('/p:root'); // true

$reader->getString('/p:root/p:string'); // Hello World

$reader->getInt('/p:root/p:int'); // 100
```
