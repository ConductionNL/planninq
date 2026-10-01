<?php

/**
 * The `report` schema (portfolio-flow-reports 3.1): its properties, its
 * owner-only write and owner-or-shared read, and a saved report's exact
 * payload validated against the real register fragment with Opis.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Settings
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Settings;

require_once __DIR__ . '/../Support/RegisterSchemaValidation.php';

use OCA\Planninq\Listener\BoardFilterOwnerListener;
use OCA\Planninq\Tests\Unit\Support\RegisterSchemaValidation;
use PHPUnit\Framework\TestCase;

/**
 * Guards the saved-report schema.
 */
class ReportSchemaTest extends TestCase {
	use RegisterSchemaValidation;

	/**
	 * The shipped register.
	 *
	 * @return array<string,mixed>
	 */
	private function register(): array {
		return json_decode((string)file_get_contents(__DIR__ . '/../../../lib/Settings/planninq_register.json'), true);
	}//end register()

	/**
	 * What ReportBuilderDialog saves for "Open work per person".
	 *
	 * @return array<string,mixed>
	 */
	private function payload(): array {
		return [
			'title' => 'Open work per person',
			'projects' => ['0000de00-0000-4000-8000-000100000001', '0000de00-0000-4000-8000-000100000002'],
			'filters' => ['status' => 'open'],
			'groupBy' => 'assignedTo',
			'metric' => 'count',
			'display' => 'bar',
			'shared' => 'private',
		];
	}//end payload()

	/**
	 * The register lists `report` and declares its properties.
	 *
	 * @return void
	 */
	public function testTheRegisterDeclaresTheReportSchema(): void {
		$register = $this->register();
		self::assertContains('report', $register['components']['registers']['planninq']['schemas']);
		$schema = $register['components']['schemas']['report'];
		self::assertSame(
			['title', 'description', 'owner', 'shared', 'projects', 'filters', 'groupBy', 'metric', 'sumField', 'display'],
			array_keys($schema['properties'])
		);
		self::assertSame(['private', 'readers'], $schema['properties']['shared']['enum']);
		self::assertSame(['table', 'bar', 'donut'], $schema['properties']['display']['enum']);
		self::assertSame(['status', 'priority', 'assignedTo', 'labels', 'column', 'issueType'], array_keys($schema['properties']['filters']['properties']));
		self::assertFalse($schema['properties']['filters']['additionalProperties']);
	}//end testTheRegisterDeclaresTheReportSchema()

	/**
	 * Only the owner (or an admin) writes; the owner, or everyone when shared
	 * is `readers`, reads. The owner is set by the server.
	 *
	 * @return void
	 */
	public function testOwnerOnlyWriteAndOwnerOrSharedRead(): void {
		$rules = $this->register()['components']['schemas']['report']['authorization'];
		self::assertSame(
			[
				['group' => 'authenticated', 'match' => ['owner' => '$userId']],
				['group' => 'authenticated', 'match' => ['shared' => 'readers']],
				['group' => 'admin'],
			],
			$rules['read']
		);
		foreach (['update', 'delete'] as $verb) {
			self::assertSame([['group' => 'authenticated', 'match' => ['owner' => '$userId']], ['group' => 'admin']], $rules[$verb]);
		}

		self::assertContains('report', BoardFilterOwnerListener::OWNED_SCHEMAS);
	}//end testOwnerOnlyWriteAndOwnerOrSharedRead()

	/**
	 * The dialog's payload is valid; a non-equality filter, an unknown
	 * filter field and an unknown display are refused.
	 *
	 * @return void
	 */
	public function testASavedReportValidatesAgainstTheRealSchema(): void {
		$payload = $this->payload();
		self::assertSame([], $this->registerSchemaErrors(slug: 'report', payload: $payload));
		self::assertSame([], $this->registerSchemaErrors(slug: 'report', payload: ['owner' => 'ann', 'shared' => 'readers', 'metric' => 'sum', 'sumField' => 'storyPoints'] + $payload));
		self::assertNotSame([], $this->registerSchemaErrors(slug: 'report', payload: ['filters' => ['status' => ['$ne' => 'done']]] + $payload), 'control: an operator is not an equality filter');
		self::assertNotSame([], $this->registerSchemaErrors(slug: 'report', payload: ['filters' => ['dueDate' => '2026-01-01']] + $payload), 'control: an unknown filter field');
		self::assertNotSame([], $this->registerSchemaErrors(slug: 'report', payload: ['display' => 'pie'] + $payload), 'control: an unknown display');
	}//end testASavedReportValidatesAgainstTheRealSchema()
}//end class
