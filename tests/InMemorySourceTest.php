<?php

use Graefe\Net\Http\BinaryResponse\InMemorySource;

class InMemorySourceTest extends PHPUnit_Framework_TestCase
{
    public function testConstruction()
    {
        $source = new InMemorySource('foo', 'foo.txt');
        $this->assertEquals('foo.txt', $source->getName());
        $this->assertNotNull($source->getETag());
        $this->assertNotNull($source->getDateModified());
        $this->assertLessThanOrEqual(time(), $source->getDateModified()->getTimestamp());
    }

    public function testReadAll()
    {
        $source = new InMemorySource('foobar', 'foo.txt');
        $this->assertEquals('foobar', $source->read(9999999));
    }

    public function testReadSubsequent()
    {
        $source = new InMemorySource('foobar', 'foo.txt');
        $this->assertEquals('foo', $source->read(3));
        $this->assertEquals('bar', $source->read(3));
    }

    public function testSeek()
    {
        $source = new InMemorySource('foobar', 'foo.txt');
        $source->seek(3);
        $this->assertEquals('bar', $source->read(3));
    }

    public function testSize()
    {
        $source = new InMemorySource('foobar', 'foo.txt');
        $this->assertEquals(6, $source->getSize());
    }

}
