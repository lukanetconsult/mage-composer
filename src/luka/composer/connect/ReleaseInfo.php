<?php
/**
 * @author    Axel Helmert <ah@luka.de>
 * @copyright Copyright (c) 2014 LUKA netconsult GmbH (www.luka.de)
 * @license   http://www.gnu.org/licenses/gpl-3.0.en.html
 */

namespace luka\composer\connect;

class ReleaseInfo
{
    /**
     * @var ChannelReader
     */
    protected $channel;

    /**
     * @var PackageInfo
     */
    protected $package;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var string
     */
    protected $stability;

    /**
     * @param ChannelReader $channel
     * @param PackageInfo $package
     * @param string $version
     * @param string $stability
     */
    public function __construct(ChannelReader $channel, PackageInfo $package, $version, $stability)
    {
        $this->channel = $channel;
        $this->package = $package;
        $this->version = $version;
        $this->stability = $stability;
    }

    /**
     * @param string $version
     * @return string|unknown
     */
    private function sanitizeVersion($version)
    {
        if (preg_match('~^(?P<version>\d+(.\d+){3})(?P<plevel>(.\d+)+)(-(?P<stability>(alpha|beta|dev|rc)\d*))?$~', $version, $m)) {
            $stability = isset($m['stability'])? $m['stability'] : $this->getStability();

            if ($stability) {
                $stability = '-' . $stability;
            }

            return $m['version'] . $stability;
        }

        return $version;
    }

    /**
     * @return PackageInfo
     */
    public function getPackage()
    {
        return $this->package;
    }

    /**
     * @return unknown
     */
    public function getVersion($raw = false)
    {
        if ($raw) {
            return $this->version;
        }

        return $this->sanitizeVersion($this->version);
    }

    /**
     * @return unknown
     */
    public function getStability()
    {
        return $this->stability;
    }

    /**
     * @return string
     */
    public function getArchiveUrl()
    {
        $path = sprintf('%s/%3$s/%2$s-%3$s.tgz', $this->channel->getUrl(), $this->package->getName(), $this->getVersion());
        return $path;
    }
}
