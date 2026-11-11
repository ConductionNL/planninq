<?php

/**
 * Golden test for the owner-or-admin guard of the case handover and the
 * plan import endpoints.
 *
 * Each caller (nobody signed in, the owner, an admin, someone else) meets
 * each project (one with an owner, one that cannot be found), and the test
 * records the answer and the log lines. Recorded on the code before the
 * guard was shared.
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

use OCA\Planninq\Controller\CaseHandoverController;
use OCA\Planninq\Controller\ProjectImportController;
use OCA\Planninq\Service\CaseHandoverService;
use OCA\Planninq\Service\MsProjectImportService;
use OCA\Planninq\Service\ProjectMembershipService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * Records the guard's answer for every caller and project.
 */
class ProjectOwnerRefusalGoldenTest extends TestCase {

	private const EXPECTED = [
		'handover nobody owned'  => [401, ['error' => 'Authentication required.'], []],
		'handover nobody gone'   => [401, ['error' => 'Authentication required.'], []],
		'handover owner owned'   => [null, null, []],
		'handover owner gone'    => [404, ['error' => 'Project not found.'], []],
		'handover admin owned'   => [null, null, []],
		'handover admin gone'    => [404, ['error' => 'Project not found.'], []],
		'handover other owned'   => [403, ['error' => 'Only the project owner can hand a project over to its case.'], []],
		'handover other gone'    => [404, ['error' => 'Project not found.'], []],
		'import nobody owned'    => [401, ['error' => 'Authentication required.'], []],
		'import nobody gone'     => [401, ['error' => 'Authentication required.'], []],
		'import owner owned'     => [null, null, []],
		'import owner gone'      => [404, ['error' => 'Project not found.'], []],
		'import admin owned'     => [null, null, []],
		'import admin gone'      => [404, ['error' => 'Project not found.'], []],
		'import other owned'     => [
			403,
			['error' => 'Only the project owner can import a plan.'],
			[['Planninq: refused a plan import by someone who is not the project owner', ['project' => 'owned']]],
		],
		'import other gone'      => [404, ['error' => 'Project not found.'], []],
	];

	/**
	 * Every caller meets every project on both endpoints, as recorded.
	 *
	 * @return void
	 */
	public function testTheGuardAnswersAsRecorded(): void {
		$actual = [];
		foreach (['handover', 'import'] as $endpoint) {
			foreach (['nobody', 'owner', 'admin', 'other'] as $caller) {
				foreach (['owned', 'gone'] as $project) {
					$actual["{$endpoint} {$caller} {$project}"] = $this->answer($endpoint, $caller, $project);
				}
			}
		}

		self::assertSame(self::EXPECTED, $actual);
	}//end testTheGuardAnswersAsRecorded()

	/**
	 * The guard's answer and the info lines logged for one case.
	 *
	 * @param string $endpoint 'handover' or 'import'.
	 * @param string $caller   'nobody', 'owner', 'admin' or 'other'.
	 * @param string $project  'owned' or 'gone'.
	 *
	 * @return array{0:int|null,1:mixed,2:list<array{0:string,1:array<string,mixed>}>}
	 */
	private function answer(string $endpoint, string $caller, string $project): array {
		$logged = [];
		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('info')->willReturnCallback(
			static function (string $message, array $context = []) use (&$logged): void {
				$logged[] = [$message, $context];
			}
		);

		$session = $this->createMock(IUserSession::class);
		$user    = null;
		if ($caller !== 'nobody') {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($caller);
		}

		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => $uid === 'admin');
		$membership = $this->createMock(ProjectMembershipService::class);
		$membership->method('ownerOfProject')->willReturnCallback(static fn (string $projectId): ?string => $projectId === 'owned' ? 'owner' : null);

		$controller = $endpoint === 'handover'
			? new CaseHandoverController($this->createMock(IRequest::class), $this->createMock(CaseHandoverService::class), $membership, $session, $groups, $logger)
			: new ProjectImportController($this->createMock(IRequest::class), $this->createMock(MsProjectImportService::class), $membership, $session, $groups, $logger);

		$refusal = (new ReflectionMethod($controller, 'refusal'))->invoke($controller, $project);
		if ($refusal instanceof JSONResponse === false) {
			return [null, null, $logged];
		}

		return [$refusal->getStatus(), $refusal->getData(), $logged];
	}//end answer()
}//end class
