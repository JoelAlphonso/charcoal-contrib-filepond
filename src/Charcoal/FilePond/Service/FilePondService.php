<?php

namespace Charcoal\FilePond\Service;

// from 'charcoal-contrib-filepond'
use Charcoal\App\Config\FilesystemConfig;
use Charcoal\Config\ConfigInterface;
use Charcoal\FilePond\FilePondConfig;
use Charcoal\FilePond\ServerConfig;
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

    // name to use for the file metadata object
    const METADATA_FILENAME_PATTERN = '\.metadata';

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
     * The server configuration.
     *
     * @var ServerConfig|ConfigInterface
     */
    private $server;

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
        $this->mountManager     = $mountManager;
        $this->filesystems      = $filesystems;

        // Default server
        $this->setServer('default');
        $this->setCurrentFilesystem();
    }

    /**
     * The __invoke method is called when a script tries to call an object as a function.
     *
     * @param string $server The server identifier to use.
     * @return mixed
     * @link https://php.net/manual/en/language.oop5.magic.php#language.oop5.magic.invoke
     */
    public function __invoke($server)
    {
        $this->setServer($server);

        // Default to config filesystem.
        $this->setCurrentFilesystem();

        return $this;
    }

    /**
     * @return ServerConfig|ConfigInterface
     */
    public function getServer(): ConfigInterface
    {
        return $this->server;
    }

    /**
     * @param string $server Server for FilePondService.
     * @return self
     */
    public function setServer($server)
    {
        $this->server = $this->config->getServer($server);

        return $this;
    }

    /**
     * @return FilesystemInterface|null
     */
    public function currentFilesystem()
    {
        return $this->currentFilesystem;
    }

    /**
     * @param string|null $ident Files system ident. Default to filesystem from server.
     * @return self
     */
    public function setCurrentFilesystem($ident = null)
    {
        if (!$ident) {
            $ident = $this->getServer()->filesystemIdent();
        }
        $this->currentFilesystem      = $this->getFilesystem($ident);
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
        $this->targetFilesystem      = $this->getFilesystem($ident);
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
                'data'  => $post->getValues(),
            ];
        }

        return [];
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
                'data'  => $transfer,
            ];
        }

        return [];
    }

    /**
     * @return array
     */
    private function delete()
    {
        return [
            'ident' => 'REVERT_FILE_TRANSFER',
            'data'  => file_get_contents('php://input'),
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
            'load'    => 'FILE_LOAD',
        ];

        foreach ($handlers as $param => $handler) {
            if (isset($params[$param])) {
                return [
                    'ident' => $handler,
                    'data'  => $params[$param],
                ];
            }
        }

        return null;
    }

    /**
     * @param string   $path     The path to folder.
     * @param Transfer $transfer The transfer file.
     * @return void
     */
    public function storeTransfer($path, Transfer $transfer)
    {
        // create transfer directory
        $path       = $path.DIRECTORY_SEPARATOR.$transfer->getId();
        $filesystem = $this->currentFilesystem();

        if (!$filesystem->has($path)) {
            $filesystem->createDir($path);
        }
        // store metadata
        if ($transfer->getMetadata()) {
            $filesystem->write(
                $path.DIRECTORY_SEPARATOR.self::METADATA_FILENAME,
                @json_encode($transfer->getMetadata())
            );
        }

        // store main file
        $files = $transfer->getFiles();
        $file  = $files[0];

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

        // @TODO remove the variant container if implemented

        return $this->removeDirectory($path.DIRECTORY_SEPARATOR.$id);
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

        $filePath = $path.DIRECTORY_SEPARATOR.$file['name'];
        // OVERWRITE FILES
        if ($this->currentFilesystem()->has($filePath)) {
            $this->currentFilesystem()->delete($filePath);
        }

        if (!empty($this->targetFilesystemIdent) &&
            $this->targetFilesystemIdent !== $this->currentFilesystemIdent
        ) {
            $success = $this->mountManager->copy(
                $this->currentFilesystemIdent.'://'.$file['tmp_name'],
                $this->targetFilesystemIdent.'://'.$filePath
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
        $target = $this->currentFileAdapter()->applyPathPrefix(
            Util::normalizePath($path.DIRECTORY_SEPARATOR.$file['name'])
        );

        return $this->currentFilesystem()->write($target, file_get_contents($file['tmp_name']));
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
        $path     = $path.DIRECTORY_SEPARATOR.$id;

        $file     = $this->getFile($path, '.+\..+');
        $metadata = $this->getFile($path, self::METADATA_FILENAME_PATTERN);
        // TODO get file variants if implemented.

        $transfer->restore($file, [], $metadata ?? []);

        return $transfer;
    }

    // File Helpers
    // ==========================================================================

    /**
     * @param string      $directory  The file directory.
     * @param string      $pattern    The file name pattern.
     * @param string|null $filesystem The filesystem ident to use.
     * @return array
     */
    private function getFiles($directory, $pattern = null, $filesystem = null)
    {
        $fs = ($filesystem) ? $this->getFilesystem($filesystem) : $this->currentFilesystem();

        $path = ($pattern) ? preg_quote($directory.DIRECTORY_SEPARATOR, '/').$pattern : $directory;

        if ($pattern) {
            $files = $fs->listContents($fs->getAdapter()->applyPathPrefix($directory));

            $files = array_filter($files, function ($file) use ($path) {
                return preg_match('/'.$path.'/U', $file['path']);
            });

            return array_map(function ($path) use ($fs, $filesystem) {
                return $this->createFileObject($fs->getAdapter()->applyPathPrefix($path), $filesystem);
            }, array_column($files, 'path'));
        } else {
            $path = $fs->getAdapter()->applyPathPrefix($path);
            return $fs->has($path) ? [$this->createFileObject($path, $filesystem)] : [];
        }
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
        $fs = ($filesystem) ? $this->getFilesystem($filesystem) : $this->currentFilesystem();
        $adapter = $fs->getAdapter();
        $fileMetadata = $fs->getMetadata($filename);

        return [
            'tmp_name' => $adapter->removePathPrefix($filename),
            'name'     => ($fileMetadata['basename'] ?? basename($filename)),
            'type'     => ($fileMetadata['mimetype'] ?? mime_content_type($filename)),
            'length'   => $fileMetadata['size'],
            'error'    => 0,
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
            $fileMetadata = $fs->getMetadata($filename);
        } catch (FileNotFoundException $e) {
            // Add some logging.

            return false;
        }

        if (!$content) {
            return false;
        }

        return [
            'tmp_name' => $filename,
            'name'     => $fileMetadata['basename'],
            'content'  => $content,
            'type'     => $fileMetadata['mimetype'],
            'length'   => $fileMetadata['size'],
            'error'    => 0,
        ];
    }
}
