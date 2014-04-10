<?php
/**
 * @author    Axel Helmert <ah@luka.de>
 * @copyright Copyright (c) 2014 LUKA netconsult GmbH (www.luka.de)
 * @license   http://www.gnu.org/licenses/gpl-3.0.en.html
 */

namespace luka\composer;

use Composer\Plugin\PluginInterface;
use Composer\Composer;
use Composer\IO\IOInterface;

class MagentoPlugin implements PluginInterface
{
    /**
     * {@inheritdoc}
     * @see \Composer\Plugin\PluginInterface::activate()
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        if ($io->isVerbose()) {
            $io->write('Loading magento composer module');
        }

        $composer->getRepositoryManager()->setRepositoryClass('mage', 'luka\composer\MagentoConnectRepository');
        $composer->getInstallationManager()->addInstaller(new MagentoPackageInstaller($io, $composer, 'magento-connect-package'));
    }
}
