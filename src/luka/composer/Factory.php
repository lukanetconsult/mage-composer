<?php
/**
 * @author    Axel Helmert <ah@luka.de>
 * @copyright Copyright (c) 2014 LUKA netconsult GmbH (www.luka.de)
 * @license http://www.gnu.org/licenses/gpl-3.0.en.html
 */

namespace luka\composer;

use Composer\Factory as ComposerFactory;
use Composer\IO\IOInterface;
use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Composer;

class Factory extends ComposerFactory
{
//     /**
//      * {@inheritdoc}
//      * @see \Composer\Factory::createDefaultInstallers()
//      */
//     protected function createDefaultInstallers(InstallationManager $im, Composer $composer, IOInterface $io)
//     {
//         parent::createDefaultInstallers($im, $composer, $io);
//         $im->addInstaller(new MagentoPackageInstaller($io, $composer, 'magento-connect-package'));
//         $im->addInstaller(new MagentoInstaller($io, $composer));
//     }

    /**
     * {@inheritdoc}
     * @see \Composer\Factory::createRepositoryManager()
     */
    protected function createRepositoryManager(IOInterface $io, Config $config, EventDispatcher $eventDispatcher = null)
    {
        $rm = parent::createRepositoryManager($io, $config, $eventDispatcher);
        $rm->setRepositoryClass('mage', 'luka\composer\MagentoConnectRepository');

        return $rm;
    }
}
