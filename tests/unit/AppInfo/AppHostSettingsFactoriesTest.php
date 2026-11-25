<?php

/**
 * Tests for the admin settings factories AppHostRegistration registers.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\AppInfo
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/app-shell-and-data-store/spec.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\AppInfo;

use OCA\OpenRegister\AppHost\Settings\GenericSettingsSection;
use OCA\Planninq\AppInfo\AppHostRegistration;
use OCA\Planninq\Settings\AdminSettings;
use OCP\App\IAppManager;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * The admin panel and its section build against OpenRegister's settings classes,
 * which take their placement and services as values.
 */
class AppHostSettingsFactoriesTest extends TestCase {

	/**
	 * The factories registerSettings() hands the registration context, by service id.
	 *
	 * @return array<string, \Closure>
	 */
	private function factories(): array {
		$factories = [];
		$context   = $this->createMock(originalClassName: IRegistrationContext::class);
		$context->method('registerService')->willReturnCallback(
			static function (string $name, \Closure $factory) use (&$factories): void {
				$factories[$name] = $factory;
			}
		);

		(new AppHostRegistration())->registerSettings(context: $context);

		return $factories;
	}//end factories()

	/**
	 * A container answering the services the factories ask for.
	 *
	 * @return ContainerInterface
	 */
	private function container(): ContainerInterface {
		$services = [
			'OCP\\App\\IAppManager'                      => $this->createMock(originalClassName: IAppManager::class),
			'OCP\\AppFramework\\Services\\IInitialState' => $this->createMock(originalClassName: IInitialState::class),
			'OCP\\IAppConfig'                            => $this->createMock(originalClassName: IAppConfig::class),
			'OCP\\IURLGenerator'                         => $this->createMock(originalClassName: IURLGenerator::class),
		];
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(static fn (string $id): object => $services[$id]);

		return $container;
	}//end container()

	/**
	 * The admin panel sits in planninq's section at priority 10.
	 *
	 * @return void
	 */
	public function testTheAdminPanelIsBuiltInPlanninqsSection(): void {
		$panel = $this->factories()[AdminSettings::class]($this->container());

		self::assertInstanceOf(expected: AdminSettings::class, actual: $panel);
		self::assertSame(expected: 'planninq', actual: $panel->getSection());
		self::assertSame(expected: 10, actual: $panel->getPriority());
	}//end testTheAdminPanelIsBuiltInPlanninqsSection()

	/**
	 * The section is named Planninq, at priority 75.
	 *
	 * @return void
	 */
	public function testTheSettingsSectionIsNamedPlanninq(): void {
		$sections = array_filter(
			array: $this->factories(),
			callback: static fn (string $name): bool => $name !== AdminSettings::class,
			mode: ARRAY_FILTER_USE_KEY
		);
		self::assertCount(expectedCount: 1, haystack: $sections);

		$section = array_values(array: $sections)[0]($this->container());

		self::assertInstanceOf(expected: GenericSettingsSection::class, actual: $section);
		self::assertSame(expected: 'planninq', actual: $section->getID());
		self::assertSame(expected: 'Planninq', actual: $section->getName());
		self::assertSame(expected: 75, actual: $section->getPriority());
	}//end testTheSettingsSectionIsNamedPlanninq()
}//end class
