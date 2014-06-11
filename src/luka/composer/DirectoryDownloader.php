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
use Composer\Package\Archiver\ArchivableFilesFinder;

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
     * @param string $source
     * @param string $dist
     * @param array $excludes
     * @param string $base
     */
    protected function copy($source, $dest, $excludes)
    {
        $files = new ArchivableFilesFinder($source, $excludes);
//         $dir = new \DirectoryIterator($source);
        $this->filesystem->ensureDirectoryExists($dest);

        /* @var $file \SplFileInfo */
        foreach ($files as $file) {
            $relativePath = str_replace('~^' . preg_quote($source, '~') . '~', '', $file->getPathname());
            $dir = ltrim(dirname($relativePath), '/');

            if ($dir) {
                $this->filesystem->ensureDirectoryExists($dest . '/' . $dir);
            }

            copy($file->getPathname(), $dest . '/' . ltrim($relativePath, '/'));
        }

//         foreach ($dir as $file) {
//             if ($file->isDot() || in_array($file->getFilename(), array('.svn', '.git'))) {
//                 continue;
//             }

//             $destPath = $dest . '/' . $file->getFilename();

//             if ($file->isDir()) {
//                 $this->copy($file->getPathname(), $destPath);
//                 continue;
//             }

//             copy($file->getPathname(), $destPath);
//         }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @see \Composer\Downloader\DownloaderInterface::download()
     */
    public function download(PackageInterface $package, $path)
    {
        $this->io->write("  - Installing <info>" . $package->getName() . "</info> (<comment>" . VersionParser::formatVersion($package) . "</comment>)");

        @unlink($path);
        $this->filesystem->removeDirectory($path);
        $this->filesystem->ensureDirectoryExists($path);

        $source = $this->filesystem->normalizePath($package->getDistUrl());

        if (empty($source) || !is_dir($source)) {
            throw new \RuntimeException(sprintf(
                'Invalid directory: "%s" (%s)',
                $source, $package->getDistUrl()
            ));
        }

        $this->io->write("    Copying directory <comment>$source</comment>");
        $this->copy($source, $path, $package->getArchiveExcludes());
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
