<?php

namespace Graefe\Net\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Graefe\Net\Http\BinaryResponse\VirtualFileSource;

/**
 * BinaryResponse represents an HTTP response delivering binary data provided by any object implementing
 * this VirtualFileSource contract interface.
 *
 * This class is heavily based on \Symfony\Component\HttpFoundation\BinaryFileResponse from the Symfony
 * package with the respective authorships and copyrights applying.
 */
class BinaryResponse extends Response
{
    /**
     * @var VirtualFileSource
     */
    protected $source;

    protected $offset;

    protected $maxlen = -1;

    protected $bufferSize = 16384;

    protected $maxBytesPerSecond = 0;

    /**
     * Constructor.
     *
     * @param \Graefe\Net\Http\BinaryResponse\VirtualFileSource $source The data source to stream
     * @param int $status The response status code
     * @param array $headers An array of response headers
     * @param bool $public Files are public by default
     * @param null|string $contentDisposition The type of Content-Disposition to set automatically with the filename
     * @param bool $autoEtag Whether the ETag header should be automatically set
     * @param bool $autoLastModified Whether the Last-Modified header should be automatically set
     */
    public function __construct(VirtualFileSource $source, $status = 200, $headers = array(), $public = true, $contentDisposition = null, $autoEtag = false, $autoLastModified = true)
    {
        parent::__construct(null, $status, $headers);
        $this->setSource($source, $contentDisposition, $autoEtag, $autoLastModified);
        if ($public) {
            $this->setPublic();
        }
    }

    /**
     * @param \Graefe\Net\Http\BinaryResponse\VirtualFileSource $source The data source to stream
     * @param int $status The response status code
     * @param array $headers An array of response headers
     * @param bool $public Files are public by default
     * @param null|string $contentDisposition The type of Content-Disposition to set automatically with the filename
     * @param bool $autoEtag Whether the ETag header should be automatically set
     * @param bool $autoLastModified Whether the Last-Modified header should be automatically set
     *
     * @return BinaryResponse The created response
     */
    public static function create($source = null, $status = 200, $headers = array(), $public = true, $contentDisposition = null, $autoEtag = false, $autoLastModified = true)
    {
        return new self($source, $status, $headers, $public, $contentDisposition, $autoEtag, $autoLastModified);
    }

    /**
     * Sets the file to stream.
     *
     * @param \Graefe\Net\Http\BinaryResponse\VirtualFileSource $source The data source to stream
     * @param string $contentDisposition
     * @param bool $autoEtag
     * @param bool $autoLastModified
     *
     * @return BinaryResponse
     */
    public function setSource(VirtualFileSource $source, $contentDisposition = null, $autoEtag = false, $autoLastModified = true)
    {
        $this->source = $source;
        if ($autoEtag) {
            $this->setAutoEtag();
        }
        if ($autoLastModified) {
            $this->setAutoLastModified();
        }
        if ($contentDisposition) {
            $this->setContentDisposition($contentDisposition);
        }
        return $this;
    }

    /**
     * Automatically sets the Last-Modified header according the file modification date.
     */
    public function setAutoLastModified()
    {
        $this->setLastModified($this->source->getDateModified());
        return $this;
    }

    /**
     * Automatically sets the ETag header according to the checksum of the file.
     */
    public function setAutoEtag()
    {
        $this->setEtag($this->source->getETag());
        return $this;
    }

    /**
     * Sets the Content-Disposition header with the given filename.
     *
     * @param string $disposition ResponseHeaderBag::DISPOSITION_INLINE or ResponseHeaderBag::DISPOSITION_ATTACHMENT
     * @param string $filename Optionally use this filename instead of the real name of the file
     * @param string $filenameFallback A fallback filename, containing only ASCII characters. Defaults to an automatically encoded filename
     *
     * @return BinaryResponse
     */
    public function setContentDisposition($disposition, $filename = '', $filenameFallback = '')
    {
        if ($filename === '') {
            $filename = $this->source->getName();
        }
        $dispositionHeader = $this->headers->makeDisposition($disposition, $filename, $filenameFallback);
        $this->headers->set('Content-Disposition', $dispositionHeader);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(Request $request)
    {
        $this->headers->set('Content-Length', $this->source->getSize());
        if (!$this->headers->has('Accept-Ranges')) {
            // Only accept ranges on safe HTTP methods
            $this->headers->set('Accept-Ranges', $request->isMethodSafe() ? 'bytes' : 'none');
        }
        if (!$this->headers->has('Content-Type')) {
            $this->headers->set('Content-Type', $this->source->getContentType() ?: 'application/octet-stream');
        }
        if ('HTTP/1.0' != $request->server->get('SERVER_PROTOCOL')) {
            $this->setProtocolVersion('1.1');
        }
        $this->ensureIEOverSSLCompatibility($request);
        $this->offset = 0;
        $this->maxlen = -1;
        if ($request->headers->has('Range')) {
            // Process the range headers.
            if (!$request->headers->has('If-Range') || $this->getEtag() == $request->headers->get('If-Range')) {
                $range = $request->headers->get('Range');
                $fileSize = $this->source->getSize();
                list($start, $end) = explode('-', substr($range, 6), 2) + array(0);
                $end = ('' === $end) ? $fileSize - 1 : (int)$end;
                if ('' === $start) {
                    $start = $fileSize - $end;
                    $end = $fileSize - 1;
                } else {
                    $start = (int)$start;
                }
                if ($start <= $end) {
                    if ($start < 0 || $end > $fileSize - 1) {
                        $this->setStatusCode(416);
                    } elseif ($start !== 0 || $end !== $fileSize - 1) {
                        $this->maxlen = $end < $fileSize ? $end - $start + 1 : -1;
                        $this->offset = $start;
                        $this->setStatusCode(206);
                        $this->headers->set('Content-Range', sprintf('bytes %s-%s/%s', $start, $end, $fileSize));
                        $this->headers->set('Content-Length', $end - $start + 1);
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Sends the file's content.
     */
    public function sendContent()
    {
        if (!$this->isSuccessful()) {
            parent::sendContent();
            return;
        }
        if (0 === $this->maxlen) {
            return;
        }
        $out = fopen('php://output', 'wb');
        $this->source->open();

        $remaining = $this->maxlen > 0 ? $this->maxlen : $this->source->getSize() - $this->offset;
        $data = '';
        $this->source->seek($this->offset);
        while (($remaining -= strlen($data)) > 0) {
            $timeStart = microtime(true);
            $data = $this->source->read(min($this->bufferSize, $remaining));
            fwrite($out, $data);
            fflush($out);
            $secondsNeeded = microtime(true) - $timeStart;

            if ($this->maxBytesPerSecond > 0) {
                // Throttle download if needed.
                $secondsRequired = $this->bufferSize / $this->maxBytesPerSecond;
                $this->sleep($secondsRequired - $secondsNeeded);
            }
        }

        $this->source->close();
        fclose($out);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \LogicException when the content is not null
     */
    public function setContent($content)
    {
        if (null !== $content) {
            throw new \LogicException('The content cannot be set on a BinaryResponse instance.');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return false
     */
    public function getContent()
    {
        return false;
    }

    /**
     * Sleep
     *
     * @return  void
     * @param   float $sec
     */
    protected function sleep($sec)
    {
        if ($sec > 0) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                com_message_pump(round($sec * 1000));
            } else {
                usleep(round($sec * 1000000));
            }
        }
    }
}
