<?php
/**
 * @author    Axel Helmert <ah@luka.de>
 * @copyright Copyright (c) 2014 LUKA netconsult GmbH (www.luka.de)
 * @license   http://www.gnu.org/licenses/gpl-3.0.en.html
 */

namespace luka\composer\connect;

use Composer\Util\RemoteFilesystem;
use Composer\Package\Version\VersionParser;

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
     * @param string $channelUrl This is the Magento connect 2.0 "key"
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
     * @return PackageInfo[]
     */
    public function getPackages()
    {
        if ($this->packages !== null) {
            return $this->packages;
        }

        if (!preg_match('~/(?P<name>[^/]+)$~', $this->url, $m)) {
            throw new \RuntimeException('Invalid magento connect key: ' . $this->url);
        }

        $this->packages = array();
        $this->packages[] = new PackageInfo($m['name'], $this);

        // Too slow
//         $xml = $this->requestXml('/packages.xml');
//         foreach ($xml->p as $packageNode) {
//             $name = (string)$packageNode->n;

//             if ($name) {
//                 $this->packages[] = new PackageInfo($name, $this);
//             }
//         }

        return $this->packages;
    }

    /**
     * @param PackageInfo $package
     * @return self
     */
    public function loadReleases(PackageInfo $package)
    {
        $xml = $this->requestXml('/releases.xml');
        $releases = new \ArrayObject();
        $versionParser = new VersionParser();
        $versionMap = array();

        foreach ($xml->r as $releaseNode) {
            $version = (string)$releaseNode->v;

            if (!$version) {
                continue;
            }

            $info = new ReleaseInfo($this, $package, $version, (string)$releaseNode->s);
            $composerVersion = $info->getVersion();

            if (isset($versionMap[$composerVersion]) && version_compare($versionMap[$composerVersion], $version, '>=')) {
                continue; // ignore lower patchlevels
            }

            $versionMap[$composerVersion] = $version;
            $releases[$composerVersion] = $info;
        }



        return $releases;
    }

    /**
     * @param ReleaseInfo $info
     */
    public function getPackageXml(ReleaseInfo $info)
    {
        return $this->requestXml(sprintf('/%s/package.xml', $info->getVersion()));
    }
}
