<?php

/**
 * Tests for the state of one queued project export (project-data-export, task 1.3).
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\BackgroundJob
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
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\BackgroundJob;

use OCA\Planninq\BackgroundJob\ExportRequest;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Planninq\BackgroundJob\ExportRequest
 */
class ExportRequestTest extends TestCase {

	/**
	 * A new request is known by its project id and holds no zip or file yet.
	 *
	 * @return void
	 */
	public function testANewRequestIsKnownByItsProjectId(): void {
		$user    = $this->createMock(IUser::class);
		$request = new ExportRequest(user: $user, projectId: 'p-1');

		$this->assertSame($user, $request->user);
		$this->assertSame('p-1', $request->projectId);
		$this->assertSame('p-1', $request->title);
		$this->assertNull($request->local);
		$this->assertNull($request->target);
	}//end testANewRequestIsKnownByItsProjectId()

	/**
	 * Adopting content takes a non-empty title and returns the content unchanged.
	 *
	 * @return void
	 */
	public function testAdoptTakesANonEmptyTitleAndReturnsTheContent(): void {
		$request = new ExportRequest(user: $this->createMock(IUser::class), projectId: 'p-1');

		$this->assertSame(['title' => ''], $request->adopt(content: ['title' => '']));
		$this->assertSame('p-1', $request->title, 'an empty title keeps the id');
		$this->assertSame(['key' => 'X'], $request->adopt(content: ['key' => 'X']));
		$this->assertSame('p-1', $request->title, 'a missing title keeps the id');

		$content = ['title' => 42, 'key' => 'HM'];
		$this->assertSame($content, $request->adopt(content: $content));
		$this->assertSame('42', $request->title);
	}//end testAdoptTakesANonEmptyTitleAndReturnsTheContent()

	/**
	 * Removing the local zip deletes an existing file and ignores none, an empty path or a missing file.
	 *
	 * @return void
	 */
	public function testRemoveLocalDeletesOnlyAnExistingZip(): void {
		$request = new ExportRequest(user: $this->createMock(IUser::class), projectId: 'p-1');
		$request->removeLocal();

		$request->local = '';
		$request->removeLocal();

		$request->local = sys_get_temp_dir() . '/pq-missing-' . uniqid() . '.zip';
		$request->removeLocal();
		$this->assertFileDoesNotExist($request->local);

		$path           = (string)tempnam(sys_get_temp_dir(), 'pqr');
		$request->local = $path;
		$this->assertFileExists($path);
		$request->removeLocal();
		$this->assertFileDoesNotExist($path);
	}//end testRemoveLocalDeletesOnlyAnExistingZip()
}//end class
