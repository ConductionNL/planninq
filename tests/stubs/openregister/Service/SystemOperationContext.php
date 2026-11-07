<?php

/**
 * Test stub of OpenRegister's SystemOperationContext.
 *
 * Same static API as lib/Service/SystemOperationContext.php in
 * ConductionNL/openregister (run() and isActive() with a depth counter), so
 * planninq's system-scoped writes can be tested without OpenRegister.
 *
 * @category Test
 * @package  OCA\OpenRegister\Service
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

namespace OCA\OpenRegister\Service;

/**
 * Ambient marker for a trusted system operation.
 */
final class SystemOperationContext {

	/**
	 * Nesting depth of active scopes.
	 *
	 * @var integer
	 */
	private static int $depth = 0;

	/**
	 * Run a callable inside the system-operation scope.
	 *
	 * @param callable $operation The operation.
	 *
	 * @return mixed Whatever the callable returns.
	 */
	public static function run(callable $operation) {
		self::$depth++;

		try {
			return $operation();
		} finally {
			self::$depth--;
		}
	}//end run()

	/**
	 * Whether a system operation is running.
	 *
	 * @return bool
	 */
	public static function isActive(): bool {
		return self::$depth > 0;
	}//end isActive()
}//end class
