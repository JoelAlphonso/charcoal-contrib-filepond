<?php

namespace Charcoal\FilePond;

use Charcoal\Config\AbstractConfig;
use Charcoal\FilePond\Service\Helper\FilePondAwareTrait;

/**
 * FilePond Contrib Module Config
 */
class FilePondConfig extends AbstractConfig
{
    /**
     * Server Main Endpoint on which to bind GET POST DELETE requests.
     *
     * @var string $serverEndPoint
     */
    private $serverEndPoint;

    /**
     * The File System ident to be utilized.
     *
     * @var string $filesystemIdent
     */
    private $filesystemIdent;

    /**
     * Default upload path for all uploads.
     * This can be altered per upload basis before calling the filepond 'parseFilePondPost' method.
     * This is done by manually setting the uploadPath on the filepond aware trait
     * {@see FilePondAwareTrait::setFilePondUploadPath()}
     *
     * @var string $uploadPath
     */
    private $uploadPath;

    /**
     * Directory which serves as a temporary upload directory until the transfer is confirmed.
     * Interaction from the front end module of file pond would only affect files within this directory.
     * Once the action completed (either by API endpoint or FormData action) File pond transfers the
     * uploaded files to their final destinations.
     * {@see FilePondAwareTrait::parseFilePondPost()}
     *
     * @var string $transferDir
     */
    private $transferDir;

    /**
     * The default data is defined in a JSON file.
     *
     * @return array
     */
    public function defaults()
    {
        $baseDir = rtrim(realpath(__DIR__ . '/../../../'), '/');
        $confDir = $baseDir . '/config';

        $filePondConfig = $this->loadFile($confDir . '/file-pond.json');

        return $filePondConfig;
    }

    /**
     * @return string
     */
    public function serverEndPoint()
    {
        return $this->serverEndPoint;
    }

    /**
     * @param string $serverEndPoint ServerEndPoint for FilePondConfig.
     * @return self
     */
    public function setServerEndPoint($serverEndPoint)
    {
        $this->serverEndPoint = $serverEndPoint;

        return $this;
    }

    /**
     * @return string
     */
    public function filesystemIdent()
    {
        return $this->filesystemIdent;
    }

    /**
     * @param string $filesystemIdent FilesystemIdent for FilePondConfig.
     * @return self
     */
    public function setFilesystemIdent($filesystemIdent)
    {
        $this->filesystemIdent = $filesystemIdent;

        return $this;
    }

    /**
     * @return string
     */
    public function uploadPath()
    {
        return $this->uploadPath;
    }

    /**
     * @param string $uploadPath UploadPath for FilePondConfig.
     * @return self
     */
    public function setUploadPath($uploadPath)
    {
        $this->uploadPath = $uploadPath;

        return $this;
    }

    /**
     * @return string
     */
    public function transferDir()
    {
        return $this->transferDir;
    }

    /**
     * @param string $transferDir TransferPath for FilePondConfig.
     * @return self
     */
    public function setTransferDir($transferDir)
    {
        $this->transferDir = $transferDir;

        return $this;
    }
}
