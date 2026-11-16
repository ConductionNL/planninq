<?php

/**
 * Planninq RunsProjectAction
 *
 * Runs a project or board write and answers with its result, its refusal, or
 * a generic error. The write checks the caller's rights itself.
 *
 * @category Controller
 * @package  OCA\Planninq\Controller
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Controller;

use OCA\Planninq\Exception\ProjectActionException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;

/**
 * A write answered as JSON: its result, a ProjectActionException as its status, anything else as a 500.
 *
 * The using controller holds a `$logger` (Psr\Log\LoggerInterface).
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2
 */
trait RunsProjectAction {

	/**
	 * Run a write and answer with its result or its refusal.
	 *
	 * @param callable $action  The write; it checks the caller's rights itself.
	 * @param string   $context What runs, for the log.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2
	 */
	private function run(callable $action, string $context): JSONResponse {
		try {
			return new JSONResponse($action());
		} catch (ProjectActionException $e) {
			return new JSONResponse(['error' => $e->getMessage(), 'code' => $e->getReason()], $e->getStatus());
		} catch (\Throwable $e) {
			$this->logger->error('Planninq: ' . $context . ' failed', ['exception' => $e->getMessage()]);
			return new JSONResponse(['error' => 'Something went wrong. Please try again.'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}//end run()
}//end trait
