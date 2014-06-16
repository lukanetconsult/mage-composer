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
use Composer\Package\PackageInterface;
use Composer\Composer;

class MagentoComposerInstaller extends LibraryInstaller
{
    /**
     * @var Composer
     */
    protected $composer = null;

    /**
     * @return string
     */
    protected function getMagentoRoot()
    {
        $extra = $this->composer->getPackage()->getExtra();
        $root = isset($extra['magento-root-dir'])? $extra['magento-root-dir'] : '';

        if (($root != '') && substr($root, -1) != '/') {
            $root .= '/';
        }

        return $root;
    }

    /**
     * @param PackageInterface $package
     */
    protected function resolveCodePool(PackageInterface $package)
    {
        $path = $this->filesystem->normalizePath($this->getInstallPath($package));
        $root = $this->filesystem->normalizePath($this->getMagentoRoot());

        if ((substr($path, 0, 1) == '/') && (substr($root, 0, 1) != '/'))  {
            $root = $this->filesystem->normalizePath(getcwd() . '/' . $root);
        } else if ((substr($root, 0, 1) == '/') && (substr($path, 0, 1) != '/')) {
            $path = $this->filesystem->normalizePath(getcwd() . '/' . $path);
        }

        $path = ($path != '')? explode('/', $path) : array();
        $root = ($root != '')? explode('/', $root) : array();

        while ((count($root) > 0) && (count($path) > 0)) {
            if ($root[0] != $path[0]) {
                break;
            }

            array_shift($root);
            array_shift($path);
        }

        $pool = array(
            '../..' // relative to "app/code"
        );

        foreach ($root as $dir) {
            $pool[] = '..';
        }

        foreach ($path as $dir) {
            $pool[] = $dir;
        }

        return implode('/', $pool);
    }

    /**
     * @param PackageInterface $package
     * @return string
     */
    protected function getMagentoModuleNames(PackageInterface $package)
    {
        $extra = $package->getExtra();

        if (isset($extra['magento-modules'])) {
            return $extra['magento-modules'];
        }

        return array(str_replace('/', '_', $package->getPrettyName()));
    }

    /**
     * {@inheritdoc}
     * @see \Composer\Installer\LibraryInstaller::installCode()
     */
    protected function installCode(PackageInterface $package)
    {
        parent::installCode($package);

        $extra = $package->getExtra();
        $modules = $this->getMagentoModuleNames($package);
        $pool = $this->resolveCodePool($package);
        $mageRoot = $this->getMagentoRoot();
        $definitionsDir = $mageRoot . 'app/etc/modules';

        foreach ($modules as $module) {
            $file = $definitionsDir . '/' . $module . '.xml';
            $xml = simplexml_load_string("<config>
                <modules>
                    <$module>
                        <codePool><![CDATA[$pool]]></codePool>
                        <active>true</active>
                    </$module>
                </modules>
            </config>");

            if (isset($extra['magento-depends']) && is_array($extra['magento-depends'])) {
                $depends = $xml->modules->$module->addChild('depends');

                foreach ($extra['magento-depends'] as $depend) {
                    $depends->addChild($depend);
                }
            }

            $this->filesystem->ensureDirectoryExists($definitionsDir);
            file_put_contents($file, $xml->asXML());
        }
    }

    /**
     * {@inheritdoc}
     * @see \Composer\Installer\LibraryInstaller::removeCode()
     */
    protected function removeCode(PackageInterface $package)
    {
        parent::removeCode($package);

        $modules = $this->getMagentoModuleNames($package);
        $mageRoot = $this->getMagentoRoot();
        $definitionsDir = $mageRoot . 'app/etc/modules';

        foreach ($modules as $module) {
            $file = $definitionsDir . '/' . $module . '.xml';
            $this->filesystem->remove($file);
        }
    }
}
