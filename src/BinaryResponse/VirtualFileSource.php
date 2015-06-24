<?php

namespace Graefe\Net\Http\BinaryResponse;

interface VirtualFileSource
{

    /**
     * @return string|null The file name for HTTP download.
     */
    public function getName();

    /**
     * @return int Total number of bytes in data.
     */
    public function getSize();

    /**
     * @return string|null
     */
    public function getETag();

    /**
     * @return \DateTime
     */
    public function getDateModified();

    /**
     * @return string|null MIME-type of data.
     */
    public function getContentType();

    /**
     * Method will be invoked before any call to seek() or read().
     */
    public function open();

    /**
     * Method will be invoked after the last call to seek() or read().
     */
    public function close();

    /**
     * Sets the position indicator for subsequent calls to read.
     * @param int $offset
     */
    public function seek($offset);

    /**
     * Reads up to $length bytes of data and increments the position indicator
     * by the total number of bytes read.
     *
     * @param int $length Maximum number of bytes to read.
     * @return mixed The data read.
     */
    public function read($length);

}
