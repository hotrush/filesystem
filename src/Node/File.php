<?php

namespace React\Filesystem\Node;

use React\Filesystem\AdapterInterface;
use React\Filesystem\Stream\GenericStreamInterface;
use React\Promise\FulfilledPromise;
use React\Promise\RejectedPromise;
use React\Stream\BufferedSink;

class File implements NodeInterface, FileInterface, GenericOperationInterface
{
    use GenericOperationTrait;

    protected $open = false;
    protected $fileDescriptor;

    /**
     * @param string $filename
     * @param AdapterInterface $filesystem
     */
    public function __construct($filename, AdapterInterface $filesystem)
    {
        $this->filesystem = $filesystem;
        $this->createNameNParentFromFilename($filename);
    }

    /**
     * {@inheritDoc}
     */
    public function exists()
    {
        return $this->stat()->then(function () {
            return new FulfilledPromise();
        }, function () {
            return new RejectedPromise();
        });
    }

    /**
     * {@inheritDoc}
     */
    public function size()
    {
        return $this->filesystem->stat($this->path)->then(function ($result) {
            return $result['size'];
        });
    }

    /**
     * {@inheritDoc}
     */
    public function time()
    {
        return $this->filesystem->stat($this->path)->then(function ($result) {
            return [
                'atime' => $result['atime'],
                'ctime' => $result['ctime'],
                'mtime' => $result['mtime'],
            ];
        });
    }

    /**
     * {@inheritDoc}
     */
    public function rename($toFilename)
    {
        return $this->filesystem->rename($this->path, $toFilename);
    }

    /**
     * {@inheritDoc}
     */
    public function create($mode = AdapterInterface::CREATION_MODE, $time = null)
    {
        return $this->stat()->then(function () {
            return new RejectedPromise(new \Exception('File exists'));
        }, function () use ($mode, $time) {
            return $this->filesystem->touch($this->path, $mode, $time);
        });
    }


    /**
     * {@inheritDoc}
     */
    public function touch($mode = AdapterInterface::CREATION_MODE, $time = null)
    {
        return $this->filesystem->touch($this->path, $mode, $time);
    }

    /**
     * {@inheritDoc}
     */
    public function open($flags, $mode = AdapterInterface::CREATION_MODE)
    {
        if ($this->open === true) {
            return new RejectedPromise();
        }

        return $this->filesystem->open($this->path, $flags, $mode)->then(function (GenericStreamInterface $stream) {
            $this->open = true;
            $this->fileDescriptor = $stream->getFiledescriptor();
            return $stream;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function close()
    {
        if ($this->open === false) {
            return new RejectedPromise();
        }

        return $this->filesystem->close($this->fileDescriptor)->then(function () {
            $this->open = false;
            $this->fileDescriptor = null;
            return new FulfilledPromise();
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getContents()
    {
        return $this->open('r')->then(function ($stream) {
            return BufferedSink::createPromise($stream);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function remove()
    {
        return $this->filesystem->unlink($this->path);
    }
}
