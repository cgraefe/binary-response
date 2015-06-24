BinaryResponse
==============

[![Build Status](https://travis-ci.org/cgraefe/binary-response.svg?branch=master)](https://travis-ci.org/cgraefe/binary-response)

BinaryResponse is a small extension to Symfony's HttpFoundation component, allowing for decoupled, in-memory binary
responses. The BinaryResponse class behaves almost like the BinaryFileResponse class from HttpFoundation with its
built-in support for range requests. Instead of a regular file though, it takes any object implementing the
VirtualFileSource contract interface.

The InMemorySource provides a basic in-memory implementation of this contract:

```php
use Graefe\Net\Http\BinaryResponse;
use Graefe\Net\Http\BinaryResponse\InMemorySource;

$source = new InMemorySource('Any binary data, maybe from a BLOB column.');
$response = new BinaryResponse($source);
$reponse->send();
```
