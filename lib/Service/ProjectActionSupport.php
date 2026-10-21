<?php

/**
 * Planninq ProjectActionSupport
 *
 * What the server-side project actions share: who the caller is and what
 * they may do, reading an object with the caller's rights, and writing as an
 * OpenRegister system operation once the caller has been checked.
 *
 * @category Service
 * @package  OCA\Planninq\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/projects.md
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\Planninq\Exception\ProjectActionException;
use OCP\App\IAppManager;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;

/**
 * Caller checks and system writes for the project actions.
 *
 * @spec openspec/specs/projects.md
 */
class ProjectActionSupport {

	/**
	 * OpenRegister's system-operation scope, by name.
	 *
	 * @var string
	 */
	private const OR_SYSTEM_CONTEXT = 'OCA\\OpenRegister\\Service\\SystemOperationContext';

	/**
	 * Constructor.
	 *
	 * @param ProjectMembershipService $membership   Reads projects as the system.
	 * @param ContainerInterface       $container    Resolves OpenRegister's ObjectService.
	 * @param IUserSession             $userSession  The caller.
	 * @param IGroupManager            $groupManager The caller's groups and admin status.
	 * @param IAppManager              $appManager   Tells whether OpenRegister is installed.
	 */
	public function __construct(
		private ProjectMembershipService $membership,
		private ContainerInterface $container,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private IAppManager $appManager,
	) {
	}//end __construct()

	/**
	 * Refuse unless the caller writes the project's work (or is an admin).
	 *
	 * @param string $projectId The project.
	 *
	 * @return void
	 *
	 * @throws ProjectActionException When they do not.
	 */
	public function assertWrites(string $projectId): void {
		$user    = $this->caller();
		$project = $this->membership->objectData(schema: ProjectMembershipService::PROJECT_SCHEMA, id: $projectId);
		if ($this->isAdmin(user: $user) === true) {
			return;
		}

		$lists = $this->membership->childLists(project: ($project ?? []));
		$isWriter = (in_array($user->getUID(), $lists['members'], true) === true
			|| array_intersect($lists['memberGroups'], $this->groupManager->getUserGroupIds($user)) !== []);
		if ($project === null || $isWriter === false) {
			throw new ProjectActionException(reason: 'planninq-not-a-project-member', status: 403, message: 'You are not a member of this project.');
		}
	}//end assertWrites()

	/**
	 * An object the caller may read, or a 404 that does not say whether it exists.
	 *
	 * @param string $schema The schema slug.
	 * @param string $id     The UUID.
	 *
	 * @return array<string,mixed>
	 *
	 * @throws ProjectActionException When it does not resolve or the caller cannot read it.
	 */
	public function readable(string $schema, string $id): array {
		try {
			$entity = $this->objectService()->find(id: $id, register: ProjectMembershipService::REGISTER, schema: $schema);
		} catch (\Throwable $e) {
			$entity = null;
		}

		if ($entity === null) {
			throw new ProjectActionException(reason: 'planninq-not-found', status: 404, message: 'Not found.');
		}

		return (array)$entity->getObject();
	}//end readable()

	/**
	 * Whether the caller owns the project, in person or through its owning group.
	 *
	 * @param array<string,mixed> $project The project.
	 * @param IUser               $user    The caller.
	 *
	 * @return bool
	 */
	public function ownsProject(array $project, IUser $user): bool {
		if (($project['owner'] ?? null) === $user->getUID()) {
			return true;
		}

		$ownerGroups = $this->membership->normalise(members: ($project['ownerGroups'] ?? null));

		return array_intersect($ownerGroups, $this->groupManager->getUserGroupIds($user)) !== [];
	}//end ownsProject()

	/**
	 * Whether the caller is an admin.
	 *
	 * @param IUser $user The caller.
	 *
	 * @return bool
	 */
	public function isAdmin(IUser $user): bool {
		return $this->groupManager->isAdmin($user->getUID());
	}//end isAdmin()

	/**
	 * The signed-in caller.
	 *
	 * @return IUser
	 *
	 * @throws ProjectActionException Without a session.
	 */
	public function caller(): IUser {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new ProjectActionException(reason: 'planninq-not-logged-in', status: 401, message: 'Not logged in.');
		}

		return $user;
	}//end caller()

	/**
	 * Save one object as the system and return its data with its `id`.
	 *
	 * @param string              $schema The schema slug.
	 * @param string|null         $id     The UUID, null to create.
	 * @param array<string,mixed> $data   The data.
	 *
	 * @return array<string,mixed>
	 */
	public function save(string $schema, ?string $id, array $data): array {
		unset($data['@self'], $data['id']);
		$saved = $this->objectService()->saveObject(
			object: $data,
			register: ProjectMembershipService::REGISTER,
			schema: $schema,
			uuid: $id,
			_rbac: false,
			_multitenancy: false
		);

		return ['id' => (string)$saved->getUuid()] + (array)$saved->getObject();
	}//end save()

	/**
	 * Delete one object as the system.
	 *
	 * @param string $schema The schema slug.
	 * @param string $id     The UUID.
	 *
	 * @return void
	 */
	public function deleteAsSystem(string $schema, string $id): void {
		$this->objectService()->deleteObject(uuid: $id, register: ProjectMembershipService::REGISTER, schema: $schema, _rbac: false, _multitenancy: false);
	}//end deleteAsSystem()

	/**
	 * Run writes inside an OpenRegister system operation.
	 *
	 * @param callable $writes The writes.
	 *
	 * @return mixed What the writes return.
	 */
	public function asSystem(callable $writes): mixed {
		$class = self::OR_SYSTEM_CONTEXT;
		if (class_exists($class) === true) {
			return $class::run($writes);
		}

		return $writes();
	}//end asSystem()

	/**
	 * OpenRegister's ObjectService.
	 *
	 * @return object
	 *
	 * @throws ProjectActionException When OpenRegister is not installed.
	 */
	public function objectService(): object {
		if ($this->appManager->isInstalled('openregister') === false) {
			throw new ProjectActionException(reason: 'planninq-openregister-missing', status: 503, message: 'OpenRegister is not installed.');
		}

		return $this->container->get('OCA\\OpenRegister\\Service\\ObjectService');
	}//end objectService()
}//end class
