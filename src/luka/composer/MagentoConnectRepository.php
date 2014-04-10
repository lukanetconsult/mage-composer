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
use Composer\DependencyResolver\Pool;
use Composer\Package\PackageInterface;

class MagentoConnectRepository extends ArrayRepository implements ProviderRepositoryInterface
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
     * @var array
     */
    private $packageIndexCache = array();

    /**
     * @var string
     */
    protected $vendorAlias = null;

    /**
     * @var connect\ChannelReader
     */
    protected $channel = null;

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
        $this->channel = new connect\ChannelReader($this->url, $this->rfs);
    }

    /**
     * @param string $version
     * @return string
     */
    protected function normalizeVersion($version)
    {
        if (preg_match('~^\d+(.\d){3}(.\d+)+(-(alpha\d*|beta\d*|dev\d*|rc\d*))?$~', $version)) {
            return $version;
        }

        return $this->versionParser->normalize($version);
    }

    /**
     * @param PackageInfo $info
     * @return string
     */
    private function createPackageName(PackageInfo $info)
    {
        return $this->vendorAlias . '/' . $info->getName();
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
            $normalizedVersion = $this->normalizeVersion($version);
        } catch (\UnexpectedValueException $e) {
            $this->io->write(sprintf('Could not load package %s %s: %s', $package->getName(), $version, $e->getMessage()));
            return false;
        }

        $package = new CompletePackage($composerPackageName, $normalizedVersion, $version);
        $package->setType('magento-connect-module');
        $package->setDistType('file');
        $package->setDistUrl($info->getArchiveUrl());

        $this->addPackage($package);
        return $package;
    }

    /**
     * {@inheritdoc}
     * @see \luka\composer\ProviderRepositoryInterface::whatProvides()
     */
    public function whatProvides(Pool $pool, $name)
    {
        if (strpos($name, strtolower($this->vendorAlias) . '/') !== 0) {
            return array();
        }

        $candidates = array();

        foreach ($this->findPackages($name) as $package) {
            $stability = $this->versionParser->parseStability($package->getStability());

            if (!$pool->isPackageAcceptable($package->getName(), $stability)) {
                continue;
            }

            $candidates[] = $package;
        }

        return $candidates;
    }

    /**
     * {@inheritdoc}
     * @see \Composer\Repository\ArrayRepository::addPackage()
     */
    public function addPackage(PackageInterface $package)
    {
        parent::addPackage($package);

        $name = strtolower($package->getName());
        $this->packageIndexCache[$name][$package->getVersion()] = $package;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @see \Composer\Repository\ArrayRepository::findPackage()
     */
    public function findPackage($name, $version)
    {
        // normalize version & name
        $version = $this->normalizeVersion($version);
        $name = strtolower($name);

        $this->findPackages($name);

        if (isset($this->packageIndexCache[$name][$version])) {
            return $this->packageIndexCache[$name][$version];
        }

        return null;
    }

	/**
     * {@inheritdoc}
     * @see \Composer\Repository\ArrayRepository::findPackages()
     */
    public function findPackages($name, $version = null)
    {
        // normalize name
        $name = strtolower($name);
        $packages = array();

        // normalize version
        if (null !== $version) {
            $version = $this->normalizeVersion($version);
        }

        if (isset($this->packageIndexCache[$name])) {
            if ($version === null) {
                return array_values($this->packageIndexCache[$name]);
            }

            if (isset($this->packageIndexCache[$name][$version])) {
                $packages[] = $this->packageIndexCache[$name][$version];
            }

            return $packages;
        }

        foreach ($this->channel->getPackages() as $packageInfo) {
            $packageName = strtolower($this->createPackageName($packageInfo));

            if ($packageName != $name) {
                continue;
            }

            foreach ($packageInfo->getReleases() as $releaseInfo) {
                $package = $this->createPackage($releaseInfo);

                if ($package && ($version === null || $version == $package->getVersion())) {
                    $packages[] = $package;
                }
            }

            break;
        }

        return $packages;
    }

	/**
     * {@inheritdoc}
     * @see \Composer\Repository\ArrayRepository::hasPackage()
     */
    public function hasPackage(PackageInterface $package)
    {
        $hit = $this->findPackage($package->getName(), $package->getVersion());

        if (!$hit || ($hit->getUniqueName() != $package->getUniqueName())) {
            return false;
        }

        return true;
    }

	/**
     * {@inheritdoc}
     * @see \Composer\Repository\ArrayRepository::search()
     */
    public function search($query, $mode = 0)
    {
        $result = null;

        foreach ($this->channel->search() as $info) {
            $packageName = $this->createPackageName($info);

            $result[$packageName] = array(
                'name' => $packageName,
                'description' => ''
            );
        }

        return $result;
    }
}
