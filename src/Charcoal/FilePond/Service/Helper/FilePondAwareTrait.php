<?php

namespace Charcoal\FilePond\Service\Helper;

use Charcoal\FilePond\Service\FilePondService;

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
    protected $filePondService;

    /**
     * @var string $filePondUploadPath
     */
    protected $filePondUploadPath = 'file-pond/uploads';

    /**
     * @param string $post The POST ident to parse.
     * @return mixed
     */
    protected function parseFilePondPost($post)
    {
        $handler = $this->filePondService->parsePostFiles($post);
        if (!isset($this->filePondHandlers[$handler['ident']])) {
            return [];
        }

        return call_user_func(
            [$this, $this->filePondHandlers[$handler['ident']]],
            $handler['data']
        );
    }

    /**
     * @param string[] $ids The file pond ids to transfer to final uploads directory.
     * @return array
     */
    protected function handleTransferIds(array $ids)
    {
        $out = [];

        foreach ($ids as $id) {
            // create transfer wrapper around upload
            $transfer = $this->filePondService->getTransfer('file-pond/tmp', $id);

            // transfer not found
            if (!$transfer) {
                $out[] = $id;
                continue;
            }

            // move files
            $files = $transfer->getFiles(null);
            foreach ($files as $file) {
                if ($this->filePondService->moveFile($file, $this->filePondUploadPath())) {
                    $out[] = $this->filePondUploadPath().DIRECTORY_SEPARATOR.$file['name'];
                }
            }
            // remove transfer directory
            $this->filePondService->removeTransferDirectory('file-pond/tmp', $id);
        }

        return $out;
    }

    /**
     * @return string
     */
    public function filePondUploadPath()
    {
        return $this->filePondUploadPath;
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
}
