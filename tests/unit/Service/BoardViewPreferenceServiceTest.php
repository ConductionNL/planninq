<?php

/**
 * Unit tests for BoardViewPreferenceService.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\Planninq\AppInfo\Application;
use OCA\Planninq\Service\BoardViewPreferenceService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

/**
 * A person's board view is stored per person and per project (boards-card-display).
 *
 * @spec openspec/changes/boards-card-display/tasks.md#task-3.1
 */
class BoardViewPreferenceServiceTest extends TestCase {

	/**
	 * User values by "uid/app/key", as IConfig would hold them.
	 *
	 * @var array<string,string>
	 */
	private array $values = [];

	/**
	 * The service under test, on an in-memory IConfig.
	 *
	 * @var BoardViewPreferenceService
	 */
	private BoardViewPreferenceService $service;

	/**
	 * Wire the service to an IConfig double that keeps what it is given.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			fn (string $uid, string $app, string $key, mixed $default = ''): string => ($this->values[$uid.'/'.$app.'/'.$key] ?? (string) $default)
		);
		$config->method('setUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key, mixed $value): void {
				$this->values[$uid.'/'.$app.'/'.$key] = (string) $value;
			}
		);
		$this->service = new BoardViewPreferenceService(config: $config);

	}//end setUp()

	/**
	 * Scenario "come back to a grouped board": Anna's grouping reads back for
	 * her board, and Bram's view of the same board is unchanged.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/boards-card-display/tasks.md#task-3.1
	 */
	public function testViewIsKeptPerPersonAndProject(): void {
		$this->service->save(userId: 'anna', projectId: 'p-verg', view: ['colour' => 'label', 'group' => 'priority']);
		$this->service->save(userId: 'anna', projectId: 'p-other', view: ['group' => 'assignee']);

		self::assertSame(
			expected: [
				'p-verg'  => ['colour' => 'label', 'group' => 'priority'],
				'p-other' => ['colour' => 'none', 'group' => 'assignee'],
			],
			actual: $this->service->views(userId: 'anna')
		);
		self::assertSame(expected: [], actual: $this->service->views(userId: 'bram'));
		self::assertArrayHasKey(key: 'anna/'.Application::APP_ID.'/'.BoardViewPreferenceService::KEY, array: $this->values);

	}//end testViewIsKeptPerPersonAndProject()

	/**
	 * An unknown colour or grouping is stored as none; a broken stored value reads as no views.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/boards-card-display/tasks.md#task-3.1
	 */
	public function testUnknownChoicesAndBrokenValuesAreRefused(): void {
		$this->service->save(userId: 'anna', projectId: 'p-verg', view: ['colour' => 'rainbow', 'group' => 7]);
		$this->service->save(userId: 'anna', projectId: '', view: ['group' => 'assignee']);
		self::assertSame(expected: ['p-verg' => ['colour' => 'none', 'group' => 'none']], actual: $this->service->views(userId: 'anna'));

		$this->values['carl/'.Application::APP_ID.'/'.BoardViewPreferenceService::KEY] = 'not json';
		self::assertSame(expected: [], actual: $this->service->views(userId: 'carl'));

	}//end testUnknownChoicesAndBrokenValuesAreRefused()

	/**
	 * At most MAX boards are kept; saving a board again makes it the newest.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/boards-card-display/tasks.md#task-3.1
	 */
	public function testOldestViewsGoFirst(): void {
		for ($i = 0; $i <= BoardViewPreferenceService::MAX; $i++) {
			$this->service->save(userId: 'anna', projectId: 'p-'.$i, view: ['group' => 'epic']);
		}

		$views = $this->service->views(userId: 'anna');
		self::assertCount(expectedCount: BoardViewPreferenceService::MAX, haystack: $views);
		self::assertArrayNotHasKey(key: 'p-0', array: $views);
		self::assertArrayHasKey(key: 'p-'.BoardViewPreferenceService::MAX, array: $views);

	}//end testOldestViewsGoFirst()
}//end class
