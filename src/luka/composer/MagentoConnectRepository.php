<?php
/**
 * @author    Axel Helmert <ah@luka.de>
 * @copyright Copyright (c) 2014 LUKA netconsult GmbH (www.luka.de)
 * @license   http://www.gnu.org/licenses/gpl-3.0.en.html
 */

namespace luka\composer;

use luka\composer\connect\PackageInfo;
use luka\composer\connect\ReleaseInfo;

use Composer\Repository\ArrayRepository;
use Composer\IO\IOInterface;
use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Util\RemoteFilesystem;
use Composer\Package\Version\VersionParser;
use Composer\Package\CompletePackage;

class MagentoConnectRepository extends ArrayRepository
{
    /**
     * @var string
     */
    protected $url = null;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var RemoteFilesystem
     */
    private $rfs;

    /**
     * @var VersionParser
     */
    private $versionParser;

    /**
     * @var string
     */
    protected $vendorAlias = null;

    /**
     * @param array $repoConfig
     * @param IOInterface $io
     * @param Config $config
     * @param EventDispatcher $dispatcher
     * @param RemoteFilesystem $rfs
     * @throws \UnexpectedValueException
     */
    public function __construct(array $repoConfig, IOInterface $io, Config $config, EventDispatcher $dispatcher = null, RemoteFilesystem $rfs = null)
    {
        if (!preg_match('{^https?://}', $repoConfig['url'])) {
            $repoConfig['url'] = 'http://'.$repoConfig['url'];
        }

        $urlBits = parse_url($repoConfig['url']);
        if (empty($urlBits['scheme']) || empty($urlBits['host'])) {
            throw new \UnexpectedValueException('Invalid url given for PEAR repository: '.$repoConfig['url']);
        }

        $this->url = rtrim($repoConfig['url'], '/');
        $this->io = $io;
        $this->rfs = $rfs ?: new RemoteFilesystem($this->io);
        $this->vendorAlias = isset($repoConfig['vendor-alias']) ? $repoConfig['vendor-alias'] : 'mage-community';
        $this->versionParser = new VersionParser();
    }

    /**
     * @param PackageInfo $info
     * @return string
     */
    private function createPackageName(PackageInfo $info)
    {
        return $this->vendorAlias . '/' . strtolower($info->getName());
    }

    /**
     * @param PackageInfo $info
     */
    private function createPackage(ReleaseInfo $info)
    {
        $package = $info->getPackage();
        $composerPackageName = $this->createPackageName($info->getPackage());
        $version = $info->getVersion();

        try {
            $normalizedVersion = $this->versionParser->normalize($version);
        } catch (\UnexpectedValueException $e) {
            $this->io->write('Could not load package %s %s: %s', $package->getName(), $version, $e->getMessage());
            return $this;
        }

        $package = new CompletePackage($composerPackageName, $normalizedVersion, $version);
        $package->setType('magento-connect-module');
        $package->setDistType('file');
        $package->setDistUrl($info->getArchiveUrl());

        $this->addPackage($package);
        return $this;
    }

    /**
     * {@inheritdoc}
     * @see \Composer\Repository\ArrayRepository::initialize()
     */
    protected function initialize()
    {
        $channel = new connect\ChannelReader($this->url, $this->rfs);

        foreach ($channel->getPackages() as $packageInfo) {
            foreach ($packageInfo->getReleases() as $releaseInfo) {
                $this->createPackage($releaseInfo);
            }
        }
    }
}
