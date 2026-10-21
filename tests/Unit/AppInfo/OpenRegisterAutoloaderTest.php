<?php

/**
 * Tests for the OpenRegister autoload prelude.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\AppInfo
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\AppInfo;

use OCA\Planninq\AppInfo\OpenRegisterAutoloader;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;

/**
 * The prelude's whole purpose is that it CANNOT take down the caller.
 *
 * The defect it exists to prevent is an exception escaping the composition
 * root: an `\Error` thrown while resolving `OCA\OpenRegister\AppHost\Bootstrap`
 * aborts the app's entire `Application::register()`, so every listener
 * registered below it silently never runs. A prelude that can itself throw
 * would reintroduce exactly that failure, so "never throws" is the contract
 * under test — on ANY instance, with OpenRegister present or absent.
 */
class OpenRegisterAutoloaderTest extends TestCase {

	/**
	 * Temporary fake openregister app directory.
	 *
	 * @var string
	 */
	private string $appPath;

	/**
	 * Create a fake openregister app with one class under `lib/`.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->appPath = sys_get_temp_dir().'/planninq-or-'.bin2hex(random_bytes(4));
		mkdir($this->appPath.'/lib/Nc35Fake', 0777, true);
		file_put_contents(
			$this->appPath.'/lib/Nc35Fake/Probe.php',
			"<?php\nnamespace OCA\\OpenRegister\\Nc35Fake;\nfinal class Probe {}\n"
		);

	}//end setUp()

	/**
	 * Remove the loader and the fake app again.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		OpenRegisterAutoloader::unregister();
		@unlink($this->appPath.'/lib/Nc35Fake/Probe.php');
		@rmdir($this->appPath.'/lib/Nc35Fake');
		@rmdir($this->appPath.'/lib');
		@rmdir($this->appPath);
		parent::tearDown();

	}//end tearDown()

	/**
	 * The prelude must never throw, whatever the instance looks like.
	 *
	 * This runs in both environments the suite is executed in: with Nextcloud
	 * booted (where OpenRegister may or may not be installed) and with only the
	 * OCP stubs registered (where `\OCP\Server::get()` cannot resolve anything).
	 * Both must be swallowed.
	 *
	 * @return void
	 */
	public function testRegisterNeverThrows(): void {
		$result = OpenRegisterAutoloader::register();

		$this->assertIsBool(
			$result,
			'The prelude must report success or failure as a bool, never throw.'
		);

	}//end testRegisterNeverThrows()

	/**
	 * Calling the prelude twice must be free and must agree with itself.
	 *
	 * The prelude keeps its loader in a static and short-circuits on it, so a
	 * second call is a no-op. `Application::register()` may run more
	 * than once in a single process (web and occ share no state, but tests and
	 * repair steps do), and a prelude that failed or threw on the second call
	 * would be a latent bootstrap defect.
	 *
	 * @return void
	 */
	public function testRegisterIsIdempotent(): void {
		$first = OpenRegisterAutoloader::register();
		$second = OpenRegisterAutoloader::register();

		$this->assertSame(
			$first,
			$second,
			'The prelude is idempotent, so repeated calls must agree.'
		);

	}//end testRegisterIsIdempotent()

	/**
	 * Nextcloud 35 removed `OC_App::registerAutoloading()`; the prelude may
	 * only use public API and plain PHP.
	 *
	 * @return void
	 */
	public function testSourceUsesNoPrivateOcAppApi(): void {
		$source = (string) file_get_contents(__DIR__.'/../../../lib/AppInfo/OpenRegisterAutoloader.php');
		$code   = (string) preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $source);
		$this->assertStringNotContainsString('OC_App', $code);

	}//end testSourceUsesNoPrivateOcAppApi()

	/**
	 * With OpenRegister enabled, its `lib/` becomes autoloadable, once.
	 *
	 * @return void
	 */
	public function testRegistersPsr4PrefixWhenEnabled(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForAnyone')->with('openregister')->willReturn(true);
		$appManager->method('getAppPath')->with('openregister')->willReturn($this->appPath.'/');

		$before = count(spl_autoload_functions());
		$this->assertTrue(OpenRegisterAutoloader::register(appManager: $appManager));
		$this->assertTrue(OpenRegisterAutoloader::register(appManager: $appManager));
		$this->assertSame($before + 1, count(spl_autoload_functions()));
		$this->assertTrue(class_exists('OCA\\OpenRegister\\Nc35Fake\\Probe'));

	}//end testRegistersPsr4PrefixWhenEnabled()

	/**
	 * A disabled OpenRegister reports false and its path is not resolved.
	 *
	 * @return void
	 */
	public function testReturnsFalseWhenDisabled(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForAnyone')->willReturn(false);
		$appManager->expects($this->never())->method('getAppPath');

		$this->assertFalse(OpenRegisterAutoloader::register(appManager: $appManager));

	}//end testReturnsFalseWhenDisabled()

	/**
	 * An exception from the app manager is swallowed and reported as false.
	 *
	 * @return void
	 */
	public function testNeverThrows(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForAnyone')->willThrowException(new \RuntimeException('boom'));

		$this->assertFalse(OpenRegisterAutoloader::register(appManager: $appManager));

	}//end testNeverThrows()

	/**
	 * The loader only answers for `OCA\OpenRegister\…` names.
	 *
	 * @return void
	 */
	public function testClassFileOnlyAnswersForOpenRegister(): void {
		$this->assertSame(
			'/x/lib/Db/Schema.php',
			OpenRegisterAutoloader::classFile(appPath: '/x', class: 'OCA\\OpenRegister\\Db\\Schema')
		);
		$this->assertNull(OpenRegisterAutoloader::classFile(appPath: '/x', class: 'OCA\\Planninq\\Foo'));
		$this->assertNull(OpenRegisterAutoloader::classFile(appPath: '/x', class: 'OCA\\OpenRegister\\'));

	}//end testClassFileOnlyAnswersForOpenRegister()
}//end class
