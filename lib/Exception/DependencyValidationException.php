<?php

/**
 * Planix Dependency Validation Exception
 *
 * Thrown by DependencyService when a dependency create/delete request fails a
 * domain rule (self-edge, duplicate, cross-project, cycle, non-member) or an
 * infrastructure precondition (OpenRegister unavailable). Carries a coarse
 * code the controller maps to an HTTP status, keeping the service free of any
 * framework (HTTP) coupling.
 *
 * @category Exception
 * @package  OCA\Planix\Exception
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */

declare(strict_types=1);

namespace OCA\Planix\Exception;

/**
 * Domain exception for dependency operations.
 *
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */
class DependencyValidationException extends \RuntimeException {

	/**
	 * Validation failure (self-edge, duplicate, cross-project, cycle) → 422.
	 *
	 * @var int
	 */
	public const CODE_VALIDATION = 1;

	/**
	 * Referenced object not found → 404.
	 *
	 * @var int
	 */
	public const CODE_NOT_FOUND = 2;

	/**
	 * Caller is not a member of the project → 403.
	 *
	 * @var int
	 */
	public const CODE_FORBIDDEN = 3;

	/**
	 * No authenticated user → 401.
	 *
	 * @var int
	 */
	public const CODE_UNAUTHENTICATED = 4;

	/**
	 * OpenRegister (or another upstream) is unavailable → 503.
	 *
	 * @var int
	 */
	public const CODE_UNAVAILABLE = 5;

	/**
	 * Constructor.
	 *
	 * @param string $message Human-readable error message (safe to surface to the client).
	 * @param int $code One of the CODE_* constants.
	 *
	 * @return void
	 */
	public function __construct(string $message, int $code) {
		parent::__construct(message: $message, code: $code);

	}//end __construct()
}//end class
