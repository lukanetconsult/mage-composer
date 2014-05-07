<?php
/**
 * LICENSE: $license_text$
 *
 * @author    Axel Helmert <ah@luka.de>
 * @copyright Copyright (c) 2014 LUKA netconsult GmbH (www.luka.de)
 * @license   $license$
 */

namespace luka\composer;

use Composer\Plugin\PluginInterface;
use Composer\Composer;
use Composer\IO\IOInterface;

class ComposerPlugin implements PluginInterface
{
    /**
     * {@inheritdoc}
     * @see \Composer\Plugin\PluginInterface::activate()
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $im = $composer->getInstallationManager();
        $im->addInstaller(new MagentoPackageInstaller($io, $composer, 'magento-connect-package'));
    }
}
