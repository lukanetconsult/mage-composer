<?php
/**
 * LICENSE: $license_text$
 *
 * @author    Axel Helmert <ah@luka.de>
 * @copyright Copyright (c) 2014 LUKA netconsult GmbH (www.luka.de)
 * @license   $license$
 */

namespace luka\composer;

use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Composer;
use Composer\Util\Filesystem;
use Composer\Package\PackageInterface;

/**
 * Installer for magento modules
 */
class MagentoInstaller extends LibraryInstaller
{
    /**
     * @var string
     */
    protected $rootDir = null;
    protected $strategy = 'copy';

    protected $installedFiles = array();
    protected $installedDirs = array();

    /**
     * {@inheritdoc}
     * @see \Composer\Installer\LibraryInstaller::__construct()
     */
    public function __construct(IOInterface $io, Composer $composer, $type = 'magento-module', Filesystem $filesystem = null)
    {
        parent::__construct($io, $composer, $type, $filesystem);

        $extra = $composer->getPackage()->getExtra();
        $this->rootDir = isset($extra['magento-root-dir'])? $this->filesystem->normalizePath($extra['magento-root-dir']) : '';

        if (isset($extra['magento-deploystrategy'])) {
            $this->strategy = $extra['magento-deploystrategy'];
        }

        if (!empty($this->rootDir) && (substr($this->rootDir, -1) != '/')) {
            $this->rootDir .= '/';
        }
    }

    /**
     * @param PackageInterface $package
     */
    protected function writeInstallInfo(PackageInterface $package)
    {
        $file = $this->getInstallPath($package) . '/install.info';
        file_put_contents($file, json_encode(array(
            'files' => $this->installedFiles,
            'dirs' => $this->installedDirs
        )));
    }

    /**
     * @param PackageInterface $package
     */
    protected function readInstallInfo(PackageInterface $package)
    {
        $this->installedDirs = array();
        $this->installedFiles = array();

        $file = $this->getInstallPath($package) . '/install.info';
        $info = @json_decode(file_get_contents($file));

        if ($info) {
            $this->installedDirs = (isset($info->dirs) && is_array($info->dirs))? $info->dirs : array();
            $this->installedFiles = (isset($info->files) && is_array($info->files))? $info->files : array();
        }
    }

    /**
     * @param PackageInterface $package
     * @return boolean
     */
    protected function isOverwriteAllowed(PackageInterface $package)
    {
        $extra = $package->getExtra();
        return (isset($extra['overwrite']) && $extra['overwrite']);
    }

    /**
     * {@inheritdoc}
     * @see \Composer\Installer\LibraryInstaller::installCode()
     */
    protected function installCode(PackageInterface $package)
    {
        $this->installedDirs = array();
        $this->installedFiles = array();

        parent::installCode($package);

        /* @var $file \SplFileInfo */
        $allowOverwrite = $this->isOverwriteAllowed($package);
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->getInstallPath($package)), \RecursiveIteratorIterator::SELF_FIRST);
        $base = rtrim($this->getInstallPath($package), '/') . '/';
        $pathOffset = strlen($base);

        foreach ($iter as $file) {
            $relativePath = substr($file->getPathname(), $pathOffset);
            $targetPath = $this->rootDir . $relativePath;

            if ($relativePath == 'install.info') {
                continue;
            }

            if ($file->isDir()) {
                if ($this->io->isVeryVerbose()) {
                    $this->io->write(sprintf('    <comment>Adding directory "%s" ...</comment>', $targetPath));
                }

                $this->filesystem->ensureDirectoryExists($this->rootDir . $relativePath);
                array_unshift($this->installedDirs, $relativePath);
                continue;
            }

            if (file_exists($targetPath)) {
                if (!$allowOverwrite) {
                    $this->io->write(sprintf('    <warning>Cannot install "%s": File exists.</warning>', $targetPath));
                    continue;
                }

                unlink($targetPath);

                if ($this->io->isVerbose()) {
                    $this->io->write(sprintf('    <comment>Overwriting file "%s" ...</comment>', $targetPath));
                }
            }

            if ($this->io->isVeryVerbose()) {
                $this->io->write(sprintf('    <comment>Installing file "%s" ...</comment>', $targetPath));
            }

            $this->filesystem->rename($file->getPathname(), $targetPath);
            $this->installedFiles[$relativePath] = sha1_file($targetPath);
        }

        $this->writeInstallInfo($package);
    }

    /**
     * {@inheritdoc}
     * @see \Composer\Installer\LibraryInstaller::removeCode()
     */
    protected function removeCode(PackageInterface $package)
    {
        $this->readInstallInfo($package);

        foreach ($this->installedFiles as $file => $hash) {
            if (sha1_file($file) != $hash) {
                $this->io->write(sprintf('    <warning>Cannot remove %s, different checksum</warning>', $file));
                continue;
            }

            $this->filesystem->remove($file);
        }

        foreach ($this->installedDirs as $dir) {
            $result = @rmdir($dir);

            if ($this->io->isVeryVerbose() && $result) {
                $this->io->write(sprintf('    <comment>Removed empty directory "%s"</comment>', $dir));
            }
        }

        $infoFile = $this->getInstallPath($package) . '/install.info';
        @unlink($infoFile);
    }
}
