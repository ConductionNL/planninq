<?php

/**
 * An in-memory locking provider with the contract of Nextcloud's ILockingProvider.
 *
 * `acquireLock()` throws LockedException for a path another holder has, as the
 * real providers do; `holdFor()` makes a path look held by someone else for a
 * number of attempts, which is how a test plays a concurrent create.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\Support
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

namespace OCA\Planninq\Tests\Unit\Support;

use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;

/**
 * In-memory ILockingProvider.
 */
class InMemoryLockingProvider implements ILockingProvider {

	/**
	 * Path => lock type held.
	 *
	 * @var array<string,int>
	 */
	public array $held = [];

	/**
	 * Path => attempts still refused because someone else holds it (-1 = forever).
	 *
	 * @var array<string,int>
	 */
	private array $foreign = [];

	/**
	 * Every acquire attempt, in order.
	 *
	 * @var array<int,string>
	 */
	public array $attempts = [];

	/**
	 * Make a path look held by another request for a number of attempts.
	 *
	 * @param string $path     The lock path.
	 * @param int    $attempts How many attempts fail; -1 for all.
	 *
	 * @return void
	 */
	public function holdFor(string $path, int $attempts): void {
		$this->foreign[$path] = $attempts;
	}//end holdFor()

	/**
	 * {@inheritDoc}
	 */
	public function isLocked(string $path, int $type): bool {
		return isset($this->held[$path]) === true || ($this->foreign[$path] ?? 0) !== 0;
	}//end isLocked()

	/**
	 * {@inheritDoc}
	 */
	public function acquireLock(string $path, int $type, ?string $readablePath = null): void {
		$this->attempts[] = $path;
		$left = ($this->foreign[$path] ?? 0);
		if ($left !== 0) {
			if ($left > 0) {
				$this->foreign[$path] = ($left - 1);
			}

			throw new LockedException($path);
		}

		if (isset($this->held[$path]) === true) {
			throw new LockedException($path);
		}

		$this->held[$path] = $type;
	}//end acquireLock()

	/**
	 * {@inheritDoc}
	 */
	public function releaseLock(string $path, int $type): void {
		unset($this->held[$path]);
	}//end releaseLock()

	/**
	 * {@inheritDoc}
	 */
	public function changeLock(string $path, int $targetType): void {
		$this->held[$path] = $targetType;
	}//end changeLock()

	/**
	 * {@inheritDoc}
	 */
	public function releaseAll(): void {
		$this->held = [];
	}//end releaseAll()
}//end class
