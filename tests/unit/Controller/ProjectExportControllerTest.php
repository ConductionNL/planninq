<?php

/**
 * Tests for starting a project export (project-data-export, task 2.1).
 *
 * The controller and ProjectRoles are the real classes; the membership
 * service is a mock answering the project's data, and the job list records
 * what is queued.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Controller
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

namespace OCA\Planninq\Tests\Unit\Controller;

use OCA\Planninq\BackgroundJob\ExportProjectJob;
use OCA\Planninq\Controller\ProjectExportController;
use OCA\Planninq\Service\ProjectMembershipService;
use OCA\Planninq\Service\ProjectRoles;
use OCP\AppFramework\Http;
use OCP\BackgroundJob\IJobList;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Planninq\Controller\ProjectExportController
 * @covers \OCA\Planninq\Service\ProjectRoles
 */
class ProjectExportControllerTest extends TestCase {

	private const PROJECT = '6f1d6c0e-1b2a-4c3d-8e9f-0a1b2c3d4e5f';

	/**
	 * The project's data as the membership service reads it.
	 *
	 * @var array<string,mixed>
	 */
	private array $project = [
		'title'         => 'Herinrichting Marktplein',
		'owner'         => 'olga',
		'managers'      => ['mia'],
		'members'       => ['mo'],
		'viewers'       => ['vic'],
		'ownerGroups'   => ['owners'],
		'managerGroups' => ['leads'],
		'memberGroups'  => ['staff'],
	];

	/**
	 * The jobs queued: [class, argument].
	 *
	 * @var list<array{0:string,1:mixed}>
	 */
	private array $queued = [];

	/**
	 * Who may and who may not start an export, by user and by group.
	 *
	 * @return array<string,array{0:string,1:array<int,string>,2:int}>
	 */
	public static function callers(): array {
		return [
			'the owner'                 => ['olga', [], Http::STATUS_ACCEPTED],
			'a manager'                 => ['mia', [], Http::STATUS_ACCEPTED],
			'someone in an owner group' => ['otto', ['owners'], Http::STATUS_ACCEPTED],
			'someone in a manager group' => ['lea', ['leads'], Http::STATUS_ACCEPTED],
			'a member'                  => ['mo', ['staff'], Http::STATUS_FORBIDDEN],
			'a viewer'                  => ['vic', [], Http::STATUS_FORBIDDEN],
			'a stranger'                => ['zed', ['other'], Http::STATUS_FORBIDDEN],
		];
	}//end callers()

	/**
	 * Owners and managers queue the export; members and viewers are refused with nothing queued.
	 *
	 * @param string            $uid    The caller.
	 * @param array<int,string> $groups The caller's groups.
	 * @param int               $status The expected status.
	 *
	 * @dataProvider callers
	 *
	 * @return void
	 */
	public function testOnlyOwnersAndManagersStartAnExport(string $uid, array $groups, int $status): void {
		$response = $this->controller(uid: $uid, groups: $groups)->create(id: self::PROJECT);

		$this->assertSame($status, $response->getStatus());
		if ($status === Http::STATUS_ACCEPTED) {
			$this->assertSame(['status' => 'queued'], $response->getData());
			$this->assertSame([[ExportProjectJob::class, ['projectId' => self::PROJECT, 'userId' => $uid]]], $this->queued);
			return;
		}

		$this->assertSame(['error' => 'Only an owner or manager of the project can export it.'], $response->getData());
		$this->assertSame([], $this->queued);
	}//end testOnlyOwnersAndManagersStartAnExport()

	/**
	 * Without a session the answer is 401.
	 *
	 * @return void
	 */
	public function testWithoutASessionTheAnswerIs401(): void {
		$response = $this->controller(uid: null, groups: [])->create(id: self::PROJECT);

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame([], $this->queued);
	}//end testWithoutASessionTheAnswerIs401()

	/**
	 * An unknown project is 404.
	 *
	 * @return void
	 */
	public function testAnUnknownProjectIs404(): void {
		$response = $this->controller(uid: 'olga', groups: [], found: false)->create(id: self::PROJECT);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame([], $this->queued);
	}//end testAnUnknownProjectIs404()

	/**
	 * An empty uid and a group list that is not a list never manage a project.
	 *
	 * @return void
	 */
	public function testAnEmptyUidOrAMalformedListDoesNotManage(): void {
		$roles = new ProjectRoles();

		$this->assertFalse($roles->mayManage(project: ['owner' => ''], uid: '', groupIds: []));
		$this->assertFalse($roles->mayManage(project: ['managers' => 'mia', 'managerGroups' => 'leads'], uid: 'mia', groupIds: ['leads']));
	}//end testAnEmptyUidOrAMalformedListDoesNotManage()

	/**
	 * The controller for a caller.
	 *
	 * @param string|null       $uid    The caller, or null for no session.
	 * @param array<int,string> $groups The caller's groups.
	 * @param bool              $found  Whether the project resolves.
	 *
	 * @return ProjectExportController
	 */
	private function controller(?string $uid, array $groups, bool $found=true): ProjectExportController {
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn($groups);

		$membership = $this->createMock(ProjectMembershipService::class);
		$membership->method('objectData')->willReturnCallback(
			fn (string $schema, string $id): ?array => ($found === true && $schema === 'project' && $id === self::PROJECT) ? $this->project : null
		);

		$jobs = $this->createMock(IJobList::class);
		$jobs->method('add')->willReturnCallback(
			function (string $job, mixed $argument = null): void {
				$this->queued[] = [$job, $argument];
			}
		);

		return new ProjectExportController(
			request: $this->createMock(IRequest::class),
			membership: $membership,
			roles: new ProjectRoles(),
			userSession: $session,
			groupManager: $groupManager,
			jobList: $jobs
		);
	}//end controller()
}//end class
