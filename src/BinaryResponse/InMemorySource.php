<?php

namespace Graefe\Net\Http\BinaryResponse;


class InMemorySource implements VirtualFileSource
{
    /** @var mixed The payload data. */
    protected $data;

    /** @var string The file name to be used for HTTP downloads. */
    protected $name;

    /** @var \DateTime */
    protected $dateModified;

    /** @var string */
    protected $eTag;

    /** @var string */
    protected $contentType = 'application/octet-stream';

    /** @var int */
    protected $offset = 0;

    /**
     * InMemorySource constructor.
     * @param mixed $data The binary data to serve.
     * @param string|null $name The file name to be used for HTTP downloads.
     */
    public function __construct($data, $name = null)
    {
        $this->data = $data;
        $this->setName($name);
        $this->setDateModified(new \DateTime());
        $this->setETag(sha1($data));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the file name to be used for HTTP downloads.
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     */
    public function getDateModified()
    {
        return $this->dateModified;
    }

    /**
     * Set the date and time the data was last modified.
     * @param \DateTime $dateModified
     */
    public function setDateModified($dateModified)
    {
        $this->dateModified = $dateModified;
    }

    /**
     * {@inheritdoc}
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * Set the MIME-type of the data.
     * @param string $contentType
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
    }

    /**
     * {@inheritdoc}
     */
    public function getETag()
    {
        return $this->eTag;
    }

    /**
     * Set the ETag to be used for cache control.
     * @param string $eTag
     */
    public function setETag($eTag)
    {
        $this->eTag = $eTag;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize()
    {
        return strlen($this->data);
    }

    /**
     * {@inheritdoc}
     */
    public function open()
    {
        // Nothing to do here.
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        // Nothing to do here.
    }

    /**
     * {@inheritdoc}
     */
    public function seek($offset)
    {
        $this->offset = $offset;
    }

    /**
     * {@inheritdoc}
     */
    public function read($length)
    {
        $data = substr($this->data, $this->offset, $length);
        $this->offset += strlen($data);
        return $data;
    }

}