<?php
/**
 * LICENSE: $license_text$
 *
 * @author    Axel Helmert <ah@luka.de>
 * @copyright Copyright (c) 2014 LUKA netconsult GmbH (www.luka.de)
 * @license   $license$
 */

namespace luka\composer;

use Composer\Downloader\DownloaderInterface;
use Composer\Package\PackageInterface;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Composer\Package\Version\VersionParser;
use Composer\EventDispatcher\EventDispatcher;

class DirectoryDownloader implements DownloaderInterface
{
    /**
     * @var IOInterface
     */
    protected $io;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @param IOInterface      $io              The IO instance
     * @param EventDispatcher  $eventDispatcher The event dispatcher
     * @param Filesystem       $filesystem      The filesystem
     */
    public function __construct(IOInterface $io, EventDispatcher $eventDispatcher = null, Filesystem $filesystem = null)
    {
        $this->io = $io;
        $this->eventDispatcher = $eventDispatcher;
        $this->filesystem = $filesystem ?: new Filesystem();
    }



    /**
     * {@inheritdoc}
     * @see \Composer\Downloader\DownloaderInterface::download()
     */
    public function download(PackageInterface $package, $path)
    {
        @unlink($path);
        $this->filesystem->ensureDirectoryExists(dirname($path));

        $target = $this->filesystem->normalizePath($package->getSourceUrl());
        if (empty($target) || !is_dir($target)) {
            throw new \RuntimeException('Invalid source directory: ' . $target);
        }

        if (!symlink($target, $path)) {
            throw new \RuntimeException('Failed to symlink directory');
        }
    }

    /**
     * {@inheritdoc}
     * @see \Composer\Downloader\DownloaderInterface::getInstallationSource()
     */
    public function getInstallationSource()
    {
        return 'dist';
    }

    /**
     * {@inheritdoc}
     * @see \Composer\Downloader\DownloaderInterface::remove()
     */
    public function remove(PackageInterface $package, $path)
    {
        $this->io->write("  - Removing <info>" . $package->getName() . "</info> (<comment>" . VersionParser::formatVersion($package) . "</comment>)");

        if (is_link($path)) {
            if (!unlink($path)) {
                throw new \RuntimeException('Could not completely delete '.$path.', aborting.');
            }

            return;
        }

        if (!$this->filesystem->removeDirectory($path)) {
            // retry after a bit on windows since it tends to be touchy with mass removals
            if (!defined('PHP_WINDOWS_VERSION_BUILD') || (usleep(250000) && !$this->filesystem->removeDirectory($path))) {
                throw new \RuntimeException('Could not completely delete '.$path.', aborting.');
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see \Composer\Downloader\DownloaderInterface::setOutputProgress()
     */
    public function setOutputProgress($outputProgress)
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     * @see \Composer\Downloader\DownloaderInterface::update()
     */
    public function update(PackageInterface $initial, PackageInterface $target, $path)
    {
        $this->remove($initial, $path);
        $this->download($target, $path);
    }
}
