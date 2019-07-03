<?php

namespace Charcoal\FilePond\Service\Helper;

use Charcoal\Factory\FactoryInterface;
use Charcoal\FilePond\FilePondConfig;
use Charcoal\FilePond\Service\FilePondService;
use Charcoal\Model\ModelInterface;
use Charcoal\Property\FileProperty;
use Charcoal\Property\PropertyInterface;
use RuntimeException;

/**
 * Trait FilePondAwareTrait
 * @package Charcoal\FilePond\Service\Helper
 */
trait FilePondAwareTrait
{
    /**
     * @var array $filePondHandlers The possible FilePond handlers.
     */
    protected $filePondHandlers = [
        'TRANSFER_IDS' => 'handleTransferIds'
    ];

    /**
     * @var FilePondService $filePondService
     */
    private $filePondService;

    /**
     * @var FilePondConfig $config
     */
    private $filePondConfig;

    /**
     * @var string $filePondUploadPath
     */
    protected $filePondUploadPath;

    /**
     * Attempts to handle the transfer of uploads given the proper parameters.
     * If possible, it will try to :
     * - fetch the upload path from the property.
     * - fetch the filesystem based on the public access of the property.
     * - Pass it through the best processor depending on wether their were ids passed or not.
     *
     * @param string|array|null             $ids        The uploaded files ids. Let to null to force $_FILES and $_POST.
     * @param string|PropertyInterface|null $property   The property ident or a property.
     * @param ModelInterface|string|null    $context    The context object as model or class ident.
     * @param string|null                   $pathSuffix Path suffix.
     * @return array
     */
    protected function handleTransfer(
        $ids = null,
        $property = null,
        $context = null,
        $pathSuffix = null
    ) {
        // Handle Upload Path and Filesystem based on property.
        if ($property) {
            if (is_string($context)) {
                $context = $this->modelFactory()->create($context);
            }

            if (is_string($property) && $context !== null) {
                $property = $context->p($property);
            }

            if ($property instanceof PropertyInterface &&
                $property instanceof FileProperty
            ) {
                $uploadPath = $property->uploadPath();

                if ($pathSuffix !== null) {
                    $uploadPath .= rtrim($pathSuffix, '/\t');
                }

                $this->setFilePondUploadPath($uploadPath);
                error_log(var_export($property->filesystem(), true));
                $this->filePondService()->setTargetFilesystem($property->filesystem());
            }
        }

        // Transfer from $_FILES or $_POST request using property ident as key.
        if (!$ids && is_string($property)) {
            return $this->parseFilePondPost($property);
        }

        // Transfer from Ids.
        if (is_array($ids)) {
            return $this->handleTransferIds($ids);
        } elseif (is_string($ids)) {
            return $this->handleSingleTransferId($ids);
        }

        return [];
    }

    /**
     * @param string $post The POST ident to parse.
     * @return mixed
     */
    protected function parseFilePondPost($post)
    {
        $handler = $this->filePondService()->parsePostFiles($post);
        if (!isset($this->filePondHandlers[$handler['ident']])) {
            return [];
        }

        return call_user_func(
            [$this, $this->filePondHandlers[$handler['ident']]],
            $handler['data']
        );
    }

    /**
     * @param string $id The file pond id to transfer to final uploads directory.
     * @return array
     */
    protected function handleSingleTransferId($id)
    {
        $out = [];
        $transferDir = $this->filePondConfig()->transferDir();

        // create transfer wrapper around upload
        $transfer = $this->filePondService()->getTransfer($transferDir, $id);

        // transfer not found
        if (!$transfer) {
            return [$id];
        }

        // move files
        $files = $transfer->getFiles(null);
        foreach ($files as $file) {
            if ($this->filePondService()->moveFile($file, $this->filePondUploadPath())) {
                $out[] = $this->filePondUploadPath().DIRECTORY_SEPARATOR.$file['name'];
            }
        }
        // remove transfer directory
        $this->filePondService()->removeTransferDirectory($transferDir, $id);

        return $out;
    }

    /**
     * @param string[] $ids The file pond ids to transfer to final uploads directory.
     * @return array
     */
    protected function handleTransferIds(array $ids)
    {
        $out = [];

        $transferDir = $this->filePondConfig()->transferDir();

        foreach ($ids as $id) {
            // create transfer wrapper around upload
            $transfer = $this->filePondService()->getTransfer($transferDir, $id);

            // transfer not found
            if (!$transfer) {
                $out[] = $id;
                continue;
            }

            // move files
            $files = $transfer->getFiles(null);
            foreach ($files as $file) {
                if ($this->filePondService()->moveFile($file, $this->filePondUploadPath())) {
                    $out[] = $this->filePondUploadPath().DIRECTORY_SEPARATOR.$file['name'];
                }
            }
            // remove transfer directory
            $this->filePondService()->removeTransferDirectory($transferDir, $id);
        }

        return $out;
    }

    /**
     * Return either a manually overridden path or the default one set in the config.
     *
     * @return string
     */
    public function filePondUploadPath()
    {
        return ($this->filePondUploadPath) ?: $this->filePondConfig()->uploadPath();
    }

    /**
     * @param string $filePondUploadPath FilePondUploadPath for FilePondAwareTrait.
     * @return self
     */
    public function setFilePondUploadPath($filePondUploadPath)
    {
        $this->filePondUploadPath = $filePondUploadPath;

        return $this;
    }

    // Dependencies from File Pond
    // ==========================================================================

    /**
     * @return FilePondService
     * @throws RuntimeException If the FilePondService is missing.
     */
    public function filePondService()
    {
        if (!isset($this->filePondService)) {
            throw new RuntimeException(sprintf(
                'FilePond Service is not defined for [%s]',
                get_class($this)
            ));
        }

        return $this->filePondService;
    }

    /**
     * @param FilePondService $filePondService FilePondService for FilePondAwareTrait.
     * @return self
     */
    public function setFilePondService(FilePondService $filePondService)
    {
        $this->filePondService = $filePondService;

        return $this;
    }

    /**
     * @return FilePondConfig
     * @throws RuntimeException If the FilePondConfig is missing.
     */
    public function filePondConfig()
    {
        if (!isset($this->filePondConfig)) {
            throw new RuntimeException(sprintf(
                'FilePond Config is not defined for [%s]',
                get_class($this)
            ));
        }

        return $this->filePondConfig;
    }

    /**
     * @param FilePondConfig $filePondConfig FilePondConfig for FilePondAwareTrait.
     * @return self
     */
    public function setFilePondConfig(FilePondConfig $filePondConfig)
    {
        $this->filePondConfig = $filePondConfig;

        return $this;
    }

    /**
     * Retrieve the model factory.
     *
     * @throws RuntimeException If the model factory is missing.
     * @return FactoryInterface
     */
    abstract public function modelFactory();
}
