<?php
/**
 * @author    Axel Helmert <ah@luka.de>
 * @copyright Copyright (c) 2014 LUKA netconsult GmbH (www.luka.de)
 * @license http://www.gnu.org/licenses/gpl-3.0.en.html
 */

namespace luka\composer;

use Composer\Satis\Console\Application;

class SatisConsoleApplication extends Application
{
    /**
     * {@inheritdoc}
     * @see \Composer\Console\Application::getComposer()
     */
    public function getComposer($required = true, $disablePlugins = false)
    {
        if (null === $this->composer) {
            try {
                $this->composer = Factory::create($this->io, null, $disablePlugins);
            } catch (\InvalidArgumentException $e) {
                if ($required) {
                    $this->io->write($e->getMessage());
                    exit(1);
                }
            }
        }

        return $this->composer;
    }
}
