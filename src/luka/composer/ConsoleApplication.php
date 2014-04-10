<?php
/**
 * @author    Axel Helmert <ah@luka.de>
 * @copyright Copyright (c) 2014 LUKA netconsult GmbH (www.luka.de)
 * @license http://www.gnu.org/licenses/gpl-3.0.en.html
 */

namespace luka\composer;

use Composer\Console\Application;
use Composer\Json\JsonValidationException;
use Composer\Command\SelfUpdateCommand;

class ConsoleApplication extends Application
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
            } catch (JsonValidationException $e) {
                $errors = ' - ' . implode(PHP_EOL . ' - ', $e->getErrors());
                $message = $e->getMessage() . ':' . PHP_EOL . $errors;
                throw new JsonValidationException($message);
            }

        }

        return $this->composer;
    }

    /**
     * {@inheritdoc}
     * @see \Composer\Console\Application::getDefaultCommands()
     */
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();

        foreach ($commands as $index => $command) {
            if ($command instanceof SelfUpdateCommand) {
                unset($commands[$index]);
            }
        }

        return $commands;
    }
}
