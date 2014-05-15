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
use Composer\Package\Link;
use Composer\Package\LinkConstraint\VersionConstraint;
use Composer\Package\LinkConstraint\LinkConstraintInterface;

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
     * @var LinkConstraintInterface
     */
    protected $includeVersionConstraint = null;

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
            throw new \UnexpectedValueException('Invalid url given for Magento Connect 2.0 repository: '.$repoConfig['url']);
        }

        $this->url = rtrim($repoConfig['url'], '/');
        $this->io = $io;
        $this->rfs = $rfs ?: new RemoteFilesystem($this->io);
        $this->vendorAlias = isset($repoConfig['vendor-alias']) ? $repoConfig['vendor-alias'] : 'mage-community';
        $this->versionParser = new VersionParser();
        $this->channel = new connect\ChannelReader($this->url, $this->rfs);

        if (isset($repoConfig['options']['limit-versions'])) {
            $this->includeVersionConstraint = $this->versionParser->parseConstraints($repoConfig['options']['limit-versions']);
        }
    }

    /**
     * @param string $version
     * @return string
     */
    protected function normalizeVersion($version)
    {
//         if (preg_match('~^(?P<version>\d+(.\d+){3})(?P<level>(.\d+)+)(-(?P<stability>alpha|beta|dev|rc)\d*)?$~', $version, $m)) {
//             $s = isset($m['stability'])? $m['stability'] : 'stable';
//             return $m['version'] . '-' . $s . str_replace('.', '', $m['level']);
//         }

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
        $composerPackageName = $this->createPackageName($package);
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
        $package->setRequires(array(
            new Link($composerPackageName, 'luka/mage-composer-plugin', $this->versionParser->parseConstraints('~1.0'), 'requires', '~1.0')
        ));

        $package->setExtra(array(
            'magento-connect-orig-version' => $info->getVersion(true)
        ));

        $this->addPackage($package);
        return $package;
    }

    /**
     * {@inheritdoc}
     * @see \Composer\Repository\ArrayRepository::initialize()
     */
    protected function initialize()
    {
        parent::initialize();

        foreach ($this->channel->getPackages() as $package) {
            foreach ($package->getReleases() as $release) {
                if ($this->includeVersionConstraint && !$this->includeVersionConstraint->matches(new VersionConstraint('=', $this->normalizeVersion($release->getVersion())))) {
                    continue;
                }

                $this->createPackage($release);
            }
        }
    }
}
