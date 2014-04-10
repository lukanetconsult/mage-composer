<?php
/**
 * LICENSE: $license_text$
 *
 * @author    Axel Helmert <ah@luka.de>
 * @copyright Copyright (c) 2014 LUKA netconsult GmbH (www.luka.de)
 * @license   $license$
 */

namespace luka\composer;

require_once __DIR__ . '/vendor/autoload.php';

$compiler = new Compiler();
$compiler->compile(isset($_SERVER['argv'][1])? $_SERVER['argv'][1] : 'mage-composer.phar');
