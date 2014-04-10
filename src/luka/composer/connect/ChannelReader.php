<?php
/**
 * @author    Axel Helmert <ah@luka.de>
 * @copyright Copyright (c) 2014 LUKA netconsult GmbH (www.luka.de)
 * @license   http://www.gnu.org/licenses/gpl-3.0.en.html
 */

namespace luka\composer\connect;

use Composer\Util\RemoteFilesystem;

class ChannelReader
{
    /**
     * @var RemoteFilesystem
     */
    protected $rfs = null;

    /**
     * @var string
     */
    protected $url = null;

    /**
     * @var PackageInfo[]
     */
    protected $packages = null;

    /**
     * @param string $channelUrl
     * @param RemoteFilesystem $rfs
     */
    public function __construct($channelUrl, RemoteFilesystem $rfs)
    {
        $this->rfs = $rfs;
        $this->url = $channelUrl;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

	/**
     * @param string $path
     * @return \SimpleXMLElement
     */
    protected function requestXml($path)
    {
        $xml = simplexml_load_string($this->rfs->getContents($this->url, $this->url . $path, false));
        if (!$xml instanceof \SimpleXMLElement) {
            throw new \RuntimeException('Failed to load ' . $path);
        }

        return $xml;
    }

    /**
     * @param string $query
     * @return PackageInfo[]
     */
    protected function search($query)
    {
        $filter = function($value) {
            return preg_quote($value, '~');
        };

        $pattern = implode('|', array_map($filter, preg_split('~\s+~', $query)));
        $pattern = "~$pattern~i";
        $result = array();

        foreach ($this->getPackages() as $package) {
            if (preg_match($pattern, $package->getName())) {
                $result[] = $package;
            }
        }

        return $result;
    }

    /**
     * @return PackageInfo[]
     */
    public function getPackages()
    {
        if ($this->packages !== null) {
            return $this->packages;
        }

        $this->packages = array();
        $xml = $this->requestXml('/packages.xml');

        foreach ($xml->p as $packageNode) {
            $name = (string)$packageNode->n;

            if ($name) {
                $this->packages[] = new PackageInfo($name, $this);
            }
        }

        return $this->packages;
    }

    /**
     * @param PackageInfo $package
     * @return self
     */
    public function loadReleases(PackageInfo $package)
    {
        $xml = $this->requestXml(sprintf('/%s/releases.xml', $package->getName()));
        $releases = new \ArrayObject();

        foreach ($xml->r as $releaseNode) {
            $version = (string)$releaseNode->v;

            if (!$version) {
                continue;
            }

            $releases[$version] = new ReleaseInfo($this, $package, (string)$releaseNode->v, (string)$releaseNode->s);
        }

        return $releases;
    }

    /**
     * @param ReleaseInfo $info
     */
    public function getPackageXml(ReleaseInfo $info)
    {
        return $this->requestXml(sprintf('/%s/%s/package.xml', $info->getPackage()->getName(), $info->getVersion()));
    }
}
