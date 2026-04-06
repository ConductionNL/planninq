<?php

/**
 * PHPUnit bootstrap for standalone unit tests.
 *
 * Loads the Composer autoloader and registers OCP stubs when running
 * outside of a full Nextcloud environment.
 *
 * @category Tests
 * @package  OCA\Planix\Tests
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

// Define that we're running PHPUnit.
define('PHPUNIT_RUN', 1);

// Include Composer's autoloader.
require_once __DIR__.'/../vendor/autoload.php';

// Bootstrap Nextcloud — since we run inside the Docker container,
// the full environment (including \OC::$server) is available.
if (file_exists(__DIR__.'/../../../lib/base.php') === true) {
    include_once __DIR__.'/../../../lib/base.php';
} else {
    // Running outside Nextcloud (CI / standalone). Register the OCP stubs
    // shipped by nextcloud/ocp so that unit tests can mock OCP interfaces.
    $ocpDir = __DIR__.'/../vendor/nextcloud/ocp';
    if (is_dir($ocpDir) === true) {
        $loader = new \Composer\Autoload\ClassLoader();
        $loader->addPsr4('OCP\\', $ocpDir.'/OCP/');
        $loader->addPsr4('NCU\\', $ocpDir.'/NCU/');
        $loader->register(prepend: true);
    }
}

// Register Test\ namespace for NC test classes.
$serverTestsLib = __DIR__.'/../../../tests/lib/';
if (is_dir($serverTestsLib) === true) {
    $loader = new \Composer\Autoload\ClassLoader();
    $loader->addPsr4('Test\\', $serverTestsLib);
    $loader->register(prepend: true);
}
