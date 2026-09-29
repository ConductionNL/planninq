<?php

/**
 * Planninq PhaseConcludingDocumentGuard
 *
 * OpenRegister lifecycle guard on the `complete` transition of a project
 * phase: a phase closes only once the file named as its concluding document
 * is attached to that phase.
 *
 * @category Lifecycle
 * @package  OCA\Planninq\Lifecycle
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Lifecycle;

use OCA\OpenRegister\Lifecycle\GuardResult;
use OCA\OpenRegister\Lifecycle\LifecycleGuardInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Allows closing a phase only with an attached concluding document.
 *
 * The register names this class in the `requires` of the phase's `complete`
 * transition; OpenRegister's LifecycleGuardRegistry resolves it by class name
 * through the server container. It reads one file and never writes.
 *
 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-1.2
 */
class PhaseConcludingDocumentGuard implements LifecycleGuardInterface {

	/**
	 * The refusal every client shows when a phase cannot close yet.
	 *
	 * @var string
	 */
	public const MESSAGE = 'Upload the concluding document before you close this phase.';

	/**
	 * OpenRegister's FileService, resolved at runtime (ADR-022).
	 *
	 * @var string
	 */
	private const OR_FILE_SERVICE = 'OCA\\OpenRegister\\Service\\FileService';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Resolves OpenRegister's FileService.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @return void
	 */
	public function __construct(
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Allow the transition when `concludingDocument` names a file attached to this phase.
	 *
	 * @param array<string,mixed> $object The phase as it would be saved.
	 * @param string $action The transition action.
	 * @param string $userId The caller.
	 *
	 * @return GuardResult
	 *
	 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-1.2
	 */
	public function check(array $object, string $action, string $userId): GuardResult {
		$document = trim((string)($object['concludingDocument'] ?? ''));
		$phaseId = (string)($object['id'] ?? '');
		if ($document === '' || $phaseId === '') {
			return GuardResult::deny(message: self::MESSAGE);
		}

		try {
			$file = $this->container->get(self::OR_FILE_SERVICE)->getFile(object: $phaseId, file: $document);
		} catch (\Throwable $e) {
			// Fail closed: a phase whose document cannot be checked does not close.
			$this->logger->warning(
				'Planninq: the concluding document of a phase could not be checked',
				['phase' => $phaseId, 'action' => $action, 'user' => $userId, 'exception' => $e->getMessage()]
			);
			return GuardResult::deny(message: self::MESSAGE);
		}

		if ($file === null) {
			return GuardResult::deny(message: self::MESSAGE);
		}

		return GuardResult::allow();

	}//end check()
}//end class
