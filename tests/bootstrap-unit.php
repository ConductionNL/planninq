<?php

declare(strict_types=1);

// Define that we're running PHPUnit.
define('PHPUNIT_RUN', 1);

// Include Composer's autoloader.
$autoloader = require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Nextcloud — when the app is checked out inside a Nextcloud server
// tree, the full environment (including \OC::$server) is available.
// Only attempt to load base.php if a valid NC config exists (with a DB),
// otherwise base.php aborts on a missing config and takes the suite with it.
$ncBasePath = __DIR__ . '/../../../lib/base.php';
$ncConfigPath = __DIR__ . '/../../../config/config.php';
$ncLoaded = false;

if (file_exists($ncBasePath) && file_exists($ncConfigPath)) {
	$ncConfig = [];
	include $ncConfigPath;
	$ncConfig = $CONFIG ?? [];
	if (isset($ncConfig['dbtype']) || isset($ncConfig['dbhost'])) {
		require_once $ncBasePath;
		$ncLoaded = true;
	}
}

// If Nextcloud could not be loaded, register the OCP/NCU interface stubs
// shipped by the `nextcloud/ocp` dev dependency so pure unit tests can mock
// OCP interfaces (OCP\Activity\IEvent, OCP\IAppConfig, …).
//
// These PSR-4 prefixes are added to the autoloader instance owned by THIS
// PHPUnit process only. They are deliberately NOT declared in composer.json's
// `autoload` block: doing so would bake OCP into the generated
// vendor/composer/autoload_psr4.php and shadow the server's own OCP classes at
// runtime, which bricks the Nextcloud instance the app is installed in.
if ($ncLoaded === false && $autoloader instanceof \Composer\Autoload\ClassLoader) {
	$autoloader->addPsr4('OCP\\', __DIR__ . '/../vendor/nextcloud/ocp/OCP/');
	$autoloader->addPsr4('NCU\\', __DIR__ . '/../vendor/nextcloud/ocp/NCU/');

	// OpenRegister is a sibling Nextcloud app, not a composer dependency, so
	// its runtime classes are genuinely absent from a bare unit-test process.
	// tests/stubs/openregister/ carries the handful of signatures planninq's
	// listeners consume. Same containment rule as above: this prefix exists
	// only on the PHPUnit process's loader, and it is APPENDED, so a real
	// OCA\OpenRegister class always wins when one is on the path.
	$autoloader->addPsr4('OCA\\OpenRegister\\', __DIR__ . '/stubs/openregister/');
}

// Register Test\ namespace for NC test classes.
$serverTestsLib = __DIR__ . '/../../../tests/lib/';
if (is_dir($serverTestsLib)) {
	$loader = new \Composer\Autoload\ClassLoader();
	$loader->addPsr4('Test\\', $serverTestsLib);
	$loader->register(true);
}

// Stub Doctrine\DBAL\ParameterType for unit tests that mock IDBConnection or
// IQueryBuilder. The real class lives in doctrine/dbal, which Nextcloud
// provides at runtime but which is not one of planninq's composer dev deps.
if (class_exists('Doctrine\\DBAL\\ParameterType') === false) {
	eval(
		'namespace Doctrine\\DBAL; '
		. 'enum ParameterType: int { '
		. 'case NULL = 0; '
		. 'case INTEGER = 1; '
		. 'case STRING = 2; '
		. 'case LARGE_OBJECT = 3; '
		. 'case BOOLEAN = 5; '
		. 'case BINARY = 6; '
		. 'case ASCII = 7; '
		. '}'
	);
}
