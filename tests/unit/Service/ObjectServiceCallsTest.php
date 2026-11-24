<?php

/**
 * ObjectServiceCalls tests.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/ObjectServiceDouble.php';
require_once __DIR__ . '/../Support/InMemoryObjectService.php';

use OCA\OpenRegister\Kernel\Contracts\Value\ObjectFindOptions;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectSaveOptions;
use OCA\Planninq\Service\ObjectServiceCalls;
use OCA\Planninq\Tests\Unit\Support\InMemoryObjectService;
use PHPUnit\Framework\TestCase;

/**
 * The scoped find and the server-trusted save handed to OpenRegister.
 *
 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.1
 */
class ObjectServiceCallsTest extends TestCase {

	/**
	 * An ObjectService double recording each call.
	 *
	 * @return object
	 */
	private function objectService(): object {
		return new class {
			/**
			 * @var array<int,array<string,mixed>>
			 */
			public array $calls = [];

			/**
			 * OpenRegister's find(): `p1` exists, `refused` throws, the rest is missing.
			 *
			 * @param int|string             $id      The id.
			 * @param ObjectFindOptions|null $options The options.
			 * @param bool                   $_rbac   RBAC on.
			 *
			 * @return object|null
			 */
			public function find(int|string $id, ?ObjectFindOptions $options = null, bool $_rbac = true): ?object {
				$this->calls[] = ['find', $id, $options?->register, $options?->schema, $_rbac];
				if ($id === 'refused') {
					throw new \RuntimeException('Forbidden');
				}

				if ($id === 'p1') {
					return (object)['uuid' => 'p1'];
				}

				return null;
			}

			/**
			 * OpenRegister's saveObject(): echoes the saved data.
			 *
			 * @param array<string,mixed>    $object  The data.
			 * @param ObjectSaveOptions|null $options The options.
			 * @param bool                   $_rbac   RBAC on.
			 *
			 * @return object
			 */
			public function saveObject(array $object, ?ObjectSaveOptions $options = null, bool $_rbac = true): object {
				$this->calls[] = ['save', $options?->target->register, $options?->target->schema, $options?->uuid, $_rbac];
				return (object)$object;
			}
		};
	}//end objectService()

	/**
	 * A find is scoped to the register and schema with RBAC on, and a miss answers null.
	 *
	 * @return void
	 */
	public function testFindIsScopedWithRbacOn(): void {
		$objects = $this->objectService();
		$calls   = new ObjectServiceCalls(objectService: $objects, register: 'planninq', schema: 'project');

		$this->assertNotNull(actual: $calls->find(id: 'p1'));
		$this->assertNull(actual: $calls->find(id: 'gone'));
		$this->assertSame(expected: ['find', 'p1', 'planninq', 'project', true], actual: $objects->calls[0]);
	}//end testFindIsScopedWithRbacOn()

	/**
	 * A refused find passes OpenRegister's exception on, so the caller can classify it.
	 *
	 * @return void
	 */
	public function testARefusedFindPassesTheExceptionOn(): void {
		$this->expectException(exception: \RuntimeException::class);

		(new ObjectServiceCalls(objectService: $this->objectService(), register: 'planninq', schema: 'project'))->find(id: 'refused');
	}//end testARefusedFindPassesTheExceptionOn()

	/**
	 * A save writes as the system to the register and schema, creating without a uuid and updating with one.
	 *
	 * @return void
	 */
	public function testSaveAsSystemCreatesOrUpdates(): void {
		$objects = $this->objectService();
		$calls   = new ObjectServiceCalls(objectService: $objects, register: 'planninq', schema: 'project');

		$created = $calls->saveAsSystem(data: ['title' => 'New']);
		$updated = $calls->saveAsSystem(data: ['title' => 'Old'], uuid: 'p1');

		$this->assertSame(expected: 'New', actual: $created->title);
		$this->assertSame(expected: 'Old', actual: $updated->title);
		$this->assertSame(expected: ['save', 'planninq', 'project', null, false], actual: $objects->calls[0]);
		$this->assertSame(expected: ['save', 'planninq', 'project', 'p1', false], actual: $objects->calls[1]);
	}//end testSaveAsSystemCreatesOrUpdates()
	/**
	 * A system-scope save stores the whole data as a silent, unvalidated system write.
	 *
	 * @return void
	 */
	public function testSaveInSystemScopeIsASilentSystemWrite(): void {
		$objects = new InMemoryObjectService();
		$calls   = new ObjectServiceCalls(objectService: $objects, register: 'planninq', schema: 'project');

		$this->assertTrue(condition: $calls->hasSystemScope());

		$calls->saveInSystemScope(data: ['title' => 'Visie', 'health' => 'green'], uuid: 'p1');

		$save = $objects->saves[0];
		$this->assertSame(expected: ['title' => 'Visie', 'health' => 'green'], actual: $objects->rows['project']['p1']);
		$this->assertSame(expected: ['planninq', 'project', 'p1'], actual: [$save['register'], $save['schema'], $save['uuid']]);
		$this->assertTrue(condition: $save['system']);
		$this->assertTrue(condition: $save['silent']);
		$this->assertFalse(condition: $save['_validation']);
		$this->assertFalse(condition: $save['_rbac']);
		$this->assertFalse(condition: $save['_multitenancy']);
	}//end testSaveInSystemScopeIsASilentSystemWrite()

	/**
	 * A refused system-scope save passes OpenRegister's exception on.
	 *
	 * @return void
	 */
	public function testARefusedSystemScopeSavePassesTheExceptionOn(): void {
		$objects = new InMemoryObjectService();
		$objects->failingSaves = ['p1'];

		$this->expectException(exception: \RuntimeException::class);

		$calls = new ObjectServiceCalls(objectService: $objects, register: 'planninq', schema: 'project');
		$calls->saveInSystemScope(data: ['title' => 'Visie'], uuid: 'p1');
	}//end testARefusedSystemScopeSavePassesTheExceptionOn()
}//end class
