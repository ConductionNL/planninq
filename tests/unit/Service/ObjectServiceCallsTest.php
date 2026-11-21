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
 * @spec openspec/changes/adopt-cnapproot-manifest-shell/tasks.md#task-5.1
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\Service;

use OCA\OpenRegister\Kernel\Contracts\Value\ObjectFindOptions;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectSaveOptions;
use OCA\Planninq\Service\ObjectServiceCalls;
use PHPUnit\Framework\TestCase;

/**
 * The scoped find and the server-trusted save handed to OpenRegister.
 *
 * @spec openspec/changes/adopt-cnapproot-manifest-shell/tasks.md#task-5.1
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
		$calls   = new ObjectServiceCalls();

		$this->assertNotNull(actual: $calls->find(objectService: $objects, register: 'planninq', schema: 'project', id: 'p1'));
		$this->assertNull(actual: $calls->find(objectService: $objects, register: 'planninq', schema: 'project', id: 'gone'));
		$this->assertSame(expected: ['find', 'p1', 'planninq', 'project', true], actual: $objects->calls[0]);
	}//end testFindIsScopedWithRbacOn()

	/**
	 * A refused find passes OpenRegister's exception on, so the caller can classify it.
	 *
	 * @return void
	 */
	public function testARefusedFindPassesTheExceptionOn(): void {
		$this->expectException(exception: \RuntimeException::class);

		(new ObjectServiceCalls())->find(objectService: $this->objectService(), register: 'planninq', schema: 'project', id: 'refused');
	}//end testARefusedFindPassesTheExceptionOn()

	/**
	 * A save writes as the system to the register and schema, creating without a uuid and updating with one.
	 *
	 * @return void
	 */
	public function testSaveAsSystemCreatesOrUpdates(): void {
		$objects = $this->objectService();
		$calls   = new ObjectServiceCalls();

		$created = $calls->saveAsSystem(objectService: $objects, register: 'planninq', schema: 'project', data: ['title' => 'New']);
		$updated = $calls->saveAsSystem(objectService: $objects, register: 'planninq', schema: 'project', data: ['title' => 'Old'], uuid: 'p1');

		$this->assertSame(expected: 'New', actual: $created->title);
		$this->assertSame(expected: 'Old', actual: $updated->title);
		$this->assertSame(expected: ['save', 'planninq', 'project', null, false], actual: $objects->calls[0]);
		$this->assertSame(expected: ['save', 'planninq', 'project', 'p1', false], actual: $objects->calls[1]);
	}//end testSaveAsSystemCreatesOrUpdates()
}//end class
