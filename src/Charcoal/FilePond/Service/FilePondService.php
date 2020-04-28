<?php

namespace Charcoal\FilePond\Service;

// from 'charcoal-contrib-filepond'
use Charcoal\App\Config\FilesystemConfig;
use Charcoal\Config\ConfigInterface;
use Charcoal\FilePond\FilePondConfig;
use Charcoal\FilePond\Service\Helper\FilesystemAwareTrait;
use Charcoal\FilePond\Service\Helper\Post;
use Charcoal\FilePond\Service\Helper\Transfer;

// from 'league/flysystem'
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\AdapterInterface;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\MountManager;
use League\Flysystem\Util;

// from 'Psr-7'
use Pimple\Container;
use Psr\Http\Message\RequestInterface;

/**
 * File Pond Service
 */
class FilePondService
{
    use FilesystemAwareTrait;

    // name to use for the file metadata object
    const METADATA_FILENAME = '.metadata';

    /**
     * @var FilesystemInterface $currentFilesystem
     */
    private $currentFilesystem;

    /**
     * @var FilesystemInterface $targetFilesystem
     */
    private $targetFilesystem;

    /**
     * @var string $currentFilesystemIdent
     */
    private $currentFilesystemIdent;

    /**
     * @var string $targetFilesystemIdent
     */
    private $targetFilesystemIdent;

    /**
     * @var AdapterInterface|AbstractAdapter $currentFileApapter
     */
    private $currentFileAdapter;

    /**
     * @var FilePondConfig $config
     */
    private $config;

    /**
     * FilePondService constructor.
     * @param ConfigInterface|FilePondConfig   $config           The service Config.
     * @param ConfigInterface|FilesystemConfig $filesystemConfig The Filesystem Config.
     * @param MountManager|FilesystemInterface $mountManager     The Filesystem MountManager.
     * @param Container|FilesystemInterface[]  $filesystems      The available filesystems.
     */
    public function __construct($config, $filesystemConfig, $mountManager, Container $filesystems)
    {
        /** @see \Charcoal\App\ServiceProvider\FilesystemServiceProvider */
        $this->config = $config;

        /** @see \Charcoal\App\ServiceProvider\FilesystemServiceProvider */
        $this->filesystemConfig = $filesystemConfig;
        $this->mountManager = $mountManager;
        $this->filesystems = $filesystems;

        // Default to config filesystem.
        $this->setCurrentFilesystem($this->config->filesystemIdent());
    }

    /**
     * @return FilesystemInterface|null
     */
    public function currentFilesystem()
    {
        return $this->currentFilesystem;
    }

    /**
     * @param string $ident Files system ident.
     * @return self
     */
    public function setCurrentFilesystem($ident)
    {
        $this->currentFilesystem = $this->getFilesystem($ident);
        $this->currentFilesystemIdent = $ident;

        return $this;
    }

    /**
     * @return FilesystemInterface
     */
    public function targetFilesystem()
    {
        return $this->targetFilesystem;
    }

    /**
     * @param string $ident TargetFilesystem for FilePondService.
     * @return self
     */
    public function setTargetFilesystem($ident)
    {
        $this->targetFilesystem = $this->getFilesystem($ident);
        $this->targetFilesystemIdent = $ident;

        return $this;
    }

    /**
     * @return AdapterInterface|AbstractAdapter
     */
    protected function currentFileAdapter()
    {
        $this->currentFileAdapter = ($this->currentFileAdapter) ?:
            $this->currentFilesystem()->getAdapter();

        return $this->currentFileAdapter;
    }

    /**
     * @param string|array $entries A list of where to fetch files from.
     * @return array
     */
    public function parsePostFiles($entries)
    {
        // if a single field entry is supplied, turn it into an array
        $entries = is_string($entries) ? [$entries] : $entries;

        foreach ($entries as $entry) {
            $post = $this->getPost($entry);
            if (!$post) {
                continue;
            }

            return [
                'ident' => $post->getFormat(),
                'data'  => $post->getValues()
            ];
        }
    }

    /**
     * @param RequestInterface $request The request object.
     * @return mixed
     */
    public function parseApiRequest(RequestInterface $request)
    {
        // get the request method so we don't have to use $_SERVER each time
        switch ($request->getMethod()) {
            case 'POST':
                // post new files
                return $this->post($request);
            case 'DELETE':
                // revert existing transfer
                return $this->delete();
            case 'GET':
            case 'HEAD':
                // fetch, load, restore
                return $this->get($request);
        }

        return [];
    }

    /**
     * @param RequestInterface $request A PSR-7 compatible Response instance.
     * @return array
     */
    private function post(RequestInterface $request)
    {
        $entries = array_keys($request->getParams());
        // if a single field entry is supplied, turn it into an array
        $entries = is_string($entries) ? [$entries] : $entries;

        foreach ($entries as $entry) {
            $post = $this->getPost($entry);

            if (!$post) {
                continue;
            }
            $transfer = new Transfer();
            $transfer->populate($entry);

            return [
                'ident' => 'FILE_TRANSFER',
                'data'  => $transfer
            ];
        }
    }

    /**
     * @return array
     */
    private function delete()
    {
        return [
            'ident' => 'REVERT_FILE_TRANSFER',
            'data'  => file_get_contents('php://input')
        ];
    }

    /**
     * @param RequestInterface $request A PSR-7 compatible Response instance.
     * @return array
     */
    private function get(RequestInterface $request)
    {
        $params = $request->getParams();

        $handlers = [
            'fetch'   => 'FETCH_REMOTE_FILE',
            'restore' => 'RESTORE_FILE_TRANSFER',
            'load'    => 'LOAD_LOCAL_FILE'
        ];

        foreach ($handlers as $param => $handler) {
            if (isset($params[$param])) {
                return [
                    'ident' => $handler,
                    'data'  => $params[$param]
                ];
            }
        }
    }

    /**
     * @param string   $path     The path to folder.
     * @param Transfer $transfer The transfer file.
     * @return void
     */
    public function storeTransfer($path, Transfer $transfer)
    {
        // create transfer directory
        $path = $path . DIRECTORY_SEPARATOR . $transfer->getId();
        $filesystem = $this->currentFilesystem();

        if (!$filesystem->has($path)) {
            $filesystem->createDir($path);
        }
        // store metadata
        if ($transfer->getMetadata()) {
            $filesystem->write(
                $path . DIRECTORY_SEPARATOR . self::METADATA_FILENAME,
                @json_encode($transfer->getMetadata())
            );
        }

        // store main file
        $files = $transfer->getFiles();
        $file = $files[0];

        $this->moveFile($file, $path);

        // store variants if we want to support variants
        // My guess is that variants are useful for post processing on uploaded files.
    }

    /**
     * @param string $id The FilePond hashed ident.
     * @return integer
     */
    public function isValidTransferId($id)
    {
        return !!preg_match('/^[0-9a-fA-F]{32}$/', $id);
    }

    /**
     * @param string $path The directory path to remove.
     * @return boolean
     */
    private function removeDirectory($path)
    {
        return $this->currentFilesystem()->deleteDir($path);
    }

    /**
     * @param string $path The base path for FilePond Transfer directory.
     * @param string $id   The FilePond hashed ident.
     * @return boolean
     */
    public function removeTransferDirectory($path, $id)
    {
        // don't remove anything if the transfer id is not valid (just a security precaution)
        if (!$this->isValidTransferId($id)) {
            return false;
        }

        //@TODO remove the variant container if implemented

        return $this->removeDirectory($path . DIRECTORY_SEPARATOR . $id);
    }

    /**
     * @param array  $file The file.
     * @param string $path The path to folder.
     * @return boolean
     */
    public function moveFile(array $file, $path)
    {
        if (is_uploaded_file($file['tmp_name'])) {
            return $this->moveTempFile($file, $path);
        }

        $filePath = $path . DIRECTORY_SEPARATOR . $file['name'];
        // OVERWRITE FILES
        if ($this->currentFilesystem()->has($filePath)) {
            $this->currentFilesystem()->delete($filePath);
        }

        if (!empty($this->targetFilesystemIdent) &&
            $this->targetFilesystemIdent !== $this->currentFilesystemIdent
        ) {
            $success = $this->mountManager->copy(
                $this->currentFilesystemIdent . '://' . $file['tmp_name'],
                $this->targetFilesystemIdent . '://' . $filePath
            );

            if ($success) {
                $this->currentFilesystem()->delete($file['tmp_name']);
            }

            return $success;
        }

        return $this->currentFilesystem()->rename($file['tmp_name'], $filePath);
    }

    /**
     * @param array  $file The file.
     * @param string $path The path to folder.
     * @return boolean
     */
    private function moveTempFile(array $file, $path)
    {
        return move_uploaded_file($file['tmp_name'], $this->currentFileAdapter()->applyPathPrefix(
            Util::normalizePath($path . DIRECTORY_SEPARATOR . $file['name'])
        ));
    }

    /**
     * @TODO Could be improved using RequestInterface::getUploadedFiles and RequestInterface::getParam
     *       But would have to rewrite the Post and Transfer object.
     *
     * @param string $entry The entry to get.
     * @return Post|boolean
     */
    private function getPost($entry)
    {
        return isset($_FILES[$entry]) || isset($_POST[$entry]) ? new Post($entry) : false;
    }

    /**
     * @param string $path The file path.
     * @param string $id   The file pond hashed id..
     * @return Transfer|boolean
     */
    public function getTransfer($path, $id)
    {
        if (!$this->isValidTransferId($id)) {
            return false;
        }

        $transfer = new Transfer($id);
        $path = $path . DIRECTORY_SEPARATOR . $id;

        $file = $this->getFile($path, '*.*');
        $metadata = $this->getFile($path, self::METADATA_FILENAME);
        //TODO get file variants if implemented.

        $transfer->restore($file, [], $metadata);

        return $transfer;
    }

    // File Helpers
    // ==========================================================================

    /**
     * @param string      $path       The file path.
     * @param string      $pattern    The file pattern.
     * @param string|null $filesystem The filesystem ident to use.
     * @return array
     */
    private function getFiles($path, $pattern = null, $filesystem = null)
    {
        $results = [];

        if ($pattern) {
            $path = $path . DIRECTORY_SEPARATOR . $pattern;
        }

        if ($filesystem) {
            $files = glob($this->getFilesystem($filesystem)
                               ->getAdapter()
                               ->applyPathPrefix($path));
        } else {
            $files = glob($this->currentFileAdapter()->applyPathPrefix($path));
        }

        foreach ($files as $file) {
            $results[] = $this->createFileObject($file);
        }

        return $results;
    }

    /**
     * @param string      $path       The file path.
     * @param string      $pattern    The file pattern.
     * @param string|null $filesystem The filesystem ident to use.
     * @return mixed|void
     */
    public function getFile($path, $pattern = null, $filesystem = null)
    {
        $result = $this->getFiles($path, $pattern, $filesystem);
        if (count($result) > 0) {
            return $result[0];
        }
    }

    /**
     * @param string      $filename   The filename.
     * @param string|null $filesystem The filesystem ident to use.
     * @return array
     */
    private function createFileObject($filename, $filesystem = null)
    {
        $adapter = $filesystem ?
            $this->getFilesystem($filesystem)->getAdapter() :
            $this->currentFileAdapter();

        return [
            'tmp_name' => $adapter->removePathPrefix($filename),
            'name'     => basename($filename),
            'type'     => mime_content_type($filename),
            'length'   => filesize($filename),
            'error'    => 0
        ];
    }

    /**
     * @param string $filename The filename.
     * @return array|boolean
     */
    public function readFile($filename)
    {
        $fs = $this->currentFilesystem();

        try {
            $content = $fs->readStream($filename);
            $type = $fs->getMimetype($filename);
            $size = $fs->getSize($filename);
        } catch (FileNotFoundException $e) {
            //Add some logging.

            return false;
        }

        if (!$content) {
            return false;
        }

        return [
            'tmp_name' => $filename,
            'name'     => basename($filename),
            'content'  => $content,
            'type'     => $type,
            'length'   => $size,
            'error'    => 0
        ];
    }
}
