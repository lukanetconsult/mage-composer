<?php
/**
 * @author    Axel Helmert <ah@luka.de>
 * @copyright Copyright (c) 2014 LUKA netconsult GmbH (www.luka.de)
 * @license   http://www.gnu.org/licenses/gpl-3.0.en.html
 */

namespace luka\composer;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;

/**
 * @property \Composer\Composer $composer
 * @property \Composer\IO\IOInterface $io
 */
class MagentoPackageInstaller extends LibraryInstaller
{
    protected $targetMap = array(
        'magelocal' => 'app/code/local/',
        "magecommunity" => "app/code/community/",
        "magecore" => "app/code/core/",
        "magedesign" => "app/design/",
        "mageetc" => "app/etc/",
        "magelib" => "lib/",
        "magelocale" => "app/locale/",
        "magemedia" => "media/",
        "mageskin" => "skin/",
        "mageweb" => "",
        "magetest" => "tests/",
        'mage' => '',
    );

    /**
     * @var array
     */
    protected $installedFiles = array();

    /**
     * @var array
     */
    protected $installedDirs = array();

    /**
     * {@inheritdoc}
     * @see \Composer\Installer\LibraryInstaller::supports()
     */
    public function supports($packageType)
    {
        $supported = array(
            'mage-connect-module',
            'magento-connect-module'
        );

        return in_array($packageType, $supported);
    }

	/**
     * @param PackageInterface $package
     * @return string
     */
    protected function getArchiveFile(PackageInterface $package)
    {
        return $this->getInstallPath($package) . '/' . pathinfo($package->getDistUrl(), PATHINFO_BASENAME);
    }

    /**
     * Write install info
     */
    protected function writeInstallInfo(PackageInterface $package)
    {
        $file = $this->filesystem->normalizePath($this->getInstallPath($package) . '/install.info');
        return (bool)file_put_contents($file, json_encode(array(
            'dirs' => $this->installedDirs,
            'files' => $this->installedFiles
        )));
    }

    /**
     * @param PackageInterface $package
     */
    public function readInstallInfo(PackageInterface $package)
    {
        $file = $this->filesystem->normalizePath($this->getInstallPath($package) . '/install.info');
        $json = file_get_contents($file);
        $info = $json? json_decode($json) : array();

        $this->installedDirs = (isset($info->dirs))? $info->dirs : array();
        $this->installedFiles = (isset($info->files))? $info->files : array();
    }

    /**
     * @return string
     */
    protected function getRootDir($path)
    {
        $extra = $this->composer->getPackage()->getExtra();
        $dir = isset($extra['rootDir'])? $extra['rootDir'] : '';

        if ($path) {
            $dir = implode('/', array_filter(array($dir, $path)));
        }

        return $this->filesystem->normalizePath($dir);
    }

    /**
     * @param string $target
     * @return string
     */
    protected function getTargetDir($target)
    {
        if (!isset($this->targetMap[$target])) {
            return 'unknown_pkg_content/';
        }

        return $this->targetMap[$target];
    }

    /**
     * @param string $file
     */
    protected function fixArchive($file)
    {
        $this->io->write('    <warning>Trying to fix invalid archive ...</warning>');
        $xd = dirname($file) . '/_tmp';
        $this->filesystem->ensureDirectoryExists($xd);

        exec('tar -xvz -C ' . escapeshellarg($xd) . ' -f ' . escapeshellarg($file), $out, $return);

        if ($return !== 0) {
            throw new \RuntimeException('Failed to repair tar file!');
        }

        $this->filesystem->remove($file);

        $phar = new \PharData($file);
        $phar->startBuffering();
        $phar->buildFromDirectory($xd);
        $phar->stopBuffering();

        $this->filesystem->remove($xd);

        return $phar;
    }

    /**
     * @param \PharData $archive
     * @param \SimpleXMLElement $node
     * @param string $target
     * @param string $prefix
     */
    private function walkPackage(\PharData $archive, \SimpleXMLElement $node, $target, $overwrite = false, $dir = '')
    {
        $base = $this->getTargetDir($target);

        foreach ($node->dir as $dirNode) {
            $path = $dir . $dirNode['name'];

            if ('' != (string)$dirNode['name']) {
                $path .= '/';
            }

            $dirpath = $this->getRootDir($base . $path);
            if ($dirpath) {
                $this->filesystem->ensureDirectoryExists($dirpath);
                array_unshift($this->installedDirs, $dir);
            }

            $this->walkPackage($archive, $dirNode, $target, $overwrite, $path);
        }

        foreach ($node->file as $fileNode) {
            if ('' == (string)$fileNode['name']) {
                continue;
            }

            $path = $dir . $fileNode['name'];
            $targetPath = $base . $path;

            if (!isset($archive[$path])) {
                if (!isset($archive[$targetPath])) {
                    $this->io->write('<error>Failed to extract package file: ' . $path . '<error>');
                    continue;
                }

                $path = $targetPath;
            }

            if (file_exists($targetPath) && !$overwrite) {
                $this->io->write('<warning>Cannot install package file (File exists): ' . $targetPath . '<warning>');
                continue;
            }

            $track = !file_exists($targetPath);
            $filePath = $this->getRootDir($targetPath);

            file_put_contents($filePath, $archive[$path]->getContent());

            if ($track) {
                $this->installedFiles[$targetPath] = sha1_file($filePath);
            }
        }
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

        /* @var $descriptionFile \PharFileInfo */
        try {
            $archive = new \PharData($this->getArchiveFile($package));
        } catch (\Exception $e) {
            $archive = $this->fixArchive($this->getArchiveFile($package));
        }

        $descriptionFile = isset($archive['package.xml'])? $archive['package.xml'] : false;
        if (($descriptionFile == false) || !$descriptionFile->isFile()) {
            throw new \RuntimeException('Invalid Magento Connect Package. Could not find package.xml');
        }

        $packageXml = @simplexml_load_string($descriptionFile->getContent());
        if (!$packageXml instanceof \SimpleXMLElement) {
            throw new \RuntimeException('Invalid Magento Connect Package. Could not load package.xml');
        }

        foreach ($packageXml->contents->target as $target) {
            $this->walkPackage($archive, $target, (string)$target['name']);
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
            $filePath = $this->getRootDir($file);
            if (is_dir($filePath) || (sha1_file($file) != $hash)) {
                continue;
            }

            $this->io->write('    <comment>removing file: ' . $file . ' ...</comment>');
            $this->filesystem->remove($this->getRootDir($file));
        }

        foreach ($this->installedDirs as $dir) {
            @rmdir($this->getRootDir($dir));
        }

        @unlink($this->filesystem->normalizePath($this->getInstallPath($package) . '/install.info'));
        return parent::removeCode($package);
    }
}
