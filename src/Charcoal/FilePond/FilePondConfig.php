<?php

namespace Charcoal\FilePond;

use Charcoal\Config\AbstractConfig;

/**
 * FilePond Contrib Module Config
 */
class FilePondConfig extends AbstractConfig
{
    /**
     * @var array
     */
    private $servers;

    /**
     * The default data is defined in a JSON file.
     *
     * @return array
     */
    public function defaults()
    {
        $baseDir = rtrim(realpath(__DIR__.'/../../../'), '/');
        $confDir = $baseDir.'/config';

        return $this->loadFile($confDir.'/file-pond.json');
    }

    /**
     * @param string $ident The server ident.
     * @return ServerConfig|null
     */
    public function getServer($ident)
    {
        return $this->servers[$ident];
    }

    /**
     * @return array
     */
    public function getServers(): array
    {
        return $this->servers;
    }

    /**
     * @param array $servers Servers for FilePondConfig.
     * @return self
     */
    public function setServers(array $servers)
    {
        $this->servers = array_map(function ($server) {
            return new ServerConfig($server);
        }, $servers);

        return $this;
    }
}
