<?php

use Graefe\Net\Http\BinaryResponse;
use Graefe\Net\Http\BinaryResponse\InMemorySource;

use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Request;

class BinaryResponseTest extends PHPUnit_Framework_TestCase
{

    /**
     * @param $fixtureFile
     * @return BinaryResponse
     */
    static protected function createResponse($fixtureFile)
    {
        $data = file_get_contents($fixtureFile);
        $source = new InMemorySource($data, basename($fixtureFile));
        return BinaryResponse::create($source);
    }

    public function testResponseShouldSendContentOfString()
    {
        $response = new BinaryResponse(new InMemorySource('foobar'));
        $this->expectOutputString('foobar');
        $response->sendContent();
    }

    public function testConstruction()
    {
        $response = new BinaryResponse(new InMemorySource('foo', 'foo.txt'), 404, array('X-Header' => 'Foo'), true, null, true, true);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Foo', $response->headers->get('X-Header'));
        $this->assertTrue($response->headers->has('ETag'));
        $this->assertTrue($response->headers->has('Last-Modified'));
        $this->assertFalse($response->headers->has('Content-Disposition'));

        $response = BinaryResponse::create(new InMemorySource('bar', 'bar.txt'), 404, array(), true, ResponseHeaderBag::DISPOSITION_INLINE);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertFalse($response->headers->has('ETag'));
        $this->assertEquals('inline; filename="bar.txt"', $response->headers->get('Content-Disposition'));
    }

    /**
     * @expectedException \LogicException
     */
    public function testSetContent()
    {
        $response = new BinaryResponse(new InMemorySource('foo'));
        $response->setContent('foo');
    }

    public function testGetContent()
    {
        $response = new BinaryResponse(new InMemorySource('foo'));
        $this->assertFalse($response->getContent());
    }

    /**
     * @dataProvider provideRanges
     */
    public function testRequests($requestRange, $offset, $length, $responseRange)
    {
        $response = self::createResponse(__DIR__.'/Fixtures/test.gif')->setAutoEtag();

        // do a request to get the ETag
        $request = Request::create('/');
        $response->prepare($request);
        $etag = $response->headers->get('ETag');

        // prepare a request for a range of the testing file
        $request = Request::create('/');
        $request->headers->set('If-Range', $etag);
        $request->headers->set('Range', $requestRange);

        $data = file_get_contents(__DIR__.'/Fixtures/test.gif');
        $this->expectOutputString(substr($data, $offset, $length));
        $response = clone $response;
        $response->prepare($request);
        $response->sendContent();

        $this->assertEquals(206, $response->getStatusCode());
        $this->assertEquals($responseRange, $response->headers->get('Content-Range'));
    }

    public function provideRanges()
    {
        return array(
            array('bytes=1-4', 1, 4, 'bytes 1-4/35'),
            array('bytes=-5', 30, 5, 'bytes 30-34/35'),
            array('bytes=30-', 30, 5, 'bytes 30-34/35'),
            array('bytes=30-30', 30, 1, 'bytes 30-30/35'),
            array('bytes=30-34', 30, 5, 'bytes 30-34/35'),
        );
    }

    /**
     * @dataProvider provideFullFileRanges
     */
    public function testFullFileRequests($requestRange)
    {
        $response = self::createResponse(__DIR__.'/Fixtures/test.gif')->setAutoEtag();

        // prepare a request for a range of the testing file
        $request = Request::create('/');
        $request->headers->set('Range', $requestRange);

        $data = file_get_contents(__DIR__.'/Fixtures/test.gif');
        $this->expectOutputString($data);
        $response = clone $response;
        $response->prepare($request);
        $response->sendContent();

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function provideFullFileRanges()
    {
        return array(
            array('bytes=0-'),
            array('bytes=0-34'),
            array('bytes=-35'),
            // Syntactical invalid range-request should also return the full resource
            array('bytes=20-10'),
            array('bytes=50-40'),
        );
    }

    /**
     * @dataProvider provideInvalidRanges
     */
    public function testInvalidRequests($requestRange)
    {
        $response = self::createResponse(__DIR__.'/Fixtures/test.gif')->setAutoEtag();

        // prepare a request for a range of the testing file
        $request = Request::create('/');
        $request->headers->set('Range', $requestRange);

        $response = clone $response;
        $response->prepare($request);
        $response->sendContent();

        $this->assertEquals(416, $response->getStatusCode());
    }

    public function provideInvalidRanges()
    {
        return array(
            array('bytes=-40'),
            array('bytes=30-40'),
        );
    }

    public function testAcceptRangeOnUnsafeMethods()
    {
        $request = Request::create('/', 'POST');
        $response = self::createResponse(__DIR__.'/Fixtures/test.gif');
        $response->prepare($request);

        $this->assertEquals('none', $response->headers->get('Accept-Ranges'));
    }

    public function testAcceptRangeNotOverridden()
    {
        $request = Request::create('/', 'POST');
        $response = self::createResponse(__DIR__.'/Fixtures/test.gif');
        $response->headers->set('Accept-Ranges', 'foo');
        $response->prepare($request);

        $this->assertEquals('foo', $response->headers->get('Accept-Ranges'));
    }
}
