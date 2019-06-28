<?php

namespace Charcoal\FilePond\Service\Helper;

use Charcoal\App\Config\FilesystemConfig;
use League\Flysystem\FilesystemInterface;

/**
 * Trait FilesystemAwareTrait
 * @package Charcoal\FilePond\Service\Helper
 */
trait FilesystemAwareTrait
{
    /**
     * Store the collection of filesystem adapters.
     *
     * @var FilesystemInterface[]
     */
    protected $filesystems;

    /**
     * Store the filesystem configset.
     *
     * @var FilesystemConfig
     */
    protected $filesystemConfig;

    /**
     * Get the named filesystem object.
     *
     * @param  string $ident The filesystem identifier.
     * @return FilesystemInterface|null Returns the filesystem instance
     *     or NULL if not found.
     */
    protected function getFilesystem($ident)
    {
        if (isset($this->filesystems[$ident])) {
            return $this->filesystems[$ident];
        }

        return null;
    }

    /**
     * Determine if the named filesystem object exists.
     *
     * @param  string $ident The filesystem identifier.
     * @return boolean TRUE if the filesystem instance exists, otherwise FALSE.
     */
    protected function hasFilesystem($ident)
    {
        return ($this->getFilesystem($ident) !== null);
    }

    /**
     * Get the given filesystem's storage configset.
     *
     * @param  string $ident The filesystem identifier.
     * @return array|null Returns the filesystem configset
     *     or NULL if the filesystem is not found.
     */
    protected function getFilesystemConfig($ident)
    {
        if ($this->hasFilesystem($ident) === false) {
            return null;
        }

        if (isset($this->filesystemConfig['connections'][$ident])) {
            return $this->filesystemConfig['connections'][$ident];
        }

        return [];
    }

    /**
     * Determine if the named filesystem is public (from its configset).
     *
     * @param  string $ident The filesystem identifier.
     * @return boolean TRUE if the filesystem is public, otherwise FALSE.
     */
    protected function isFilesystemPublic($ident)
    {
        if ($this->hasFilesystem($ident) === false) {
            return false;
        }

        $config = $this->getFilesystemConfig($ident);
        if (isset($config['public']) && $config['public'] === false) {
            return false;
        }

        return true;
    }
}
