<?php

/**
 * Planninq forge link id repair
 *
 * Rewrites stored forge ids that are not in their canonical form.
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
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Service;

use OCA\OpenRegister\Service\Object\ObjectFindTarget;
use OCA\OpenRegister\Service\Object\ObjectSaveOptions;
use OCA\OpenRegister\Service\Object\SavePolicy;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Finds and rewrites code links whose forge id is padded or in another case.
 *
 * A link stored before the canonical form existed does not match a repeat
 * of the same forge item. The plan lists every such link; a link whose
 * canonical id is already on its task is a collision and is left for a
 * person, so the repair never makes two equal links.
 *
 * @spec openspec/specs/code-forge-links/spec.md#requirement-forge-activity-that-names-a-task-key-is-linked-through-integriq
 */
class ForgeLinkIdRepair {

	/**
	 * Constructor.
	 *
	 * @param ForgeLinkService   $forgeLinks Reads the links and gives the canonical form.
	 * @param ContainerInterface $container  Resolves OpenRegister's ObjectService at runtime.
	 * @param LoggerInterface    $logger     The logger.
	 */
	public function __construct(
		private ForgeLinkService $forgeLinks,
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The links to rewrite, read as the system: id, task, stored id, canonical id and whether it collides.
	 *
	 * @return list<array{id:string,task:string,from:string,to:string,collides:bool,data:array<string,mixed>}>
	 *
	 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-1.2
	 */
	public function plan(): array {
		$links = $this->forgeLinks->all();
		$holders = [];
		foreach ($links as $link) {
			$slot           = $this->slot(link: $link, externalId: ($link['data']['externalId'] ?? null));
			$holders[$slot] = (($holders[$slot] ?? 0) + 1);
		}

		$plan = [];
		foreach ($links as $link) {
			$from = ($link['data']['externalId'] ?? null);
			if (is_string($from) === false || $this->forgeLinks->canonicalId(id: $from) === $from) {
				continue;
			}

			$to     = $this->forgeLinks->canonicalId(id: $from);
			$plan[] = [
				'id'       => $link['id'],
				'task'     => (string)($link['data']['task'] ?? ''),
				'from'     => $from,
				'to'       => $to,
				'collides' => ($holders[$this->slot(link: $link, externalId: $to)] > 1),
				'data'     => $link['data'],
			];
		}

		return $plan;
	}//end plan()

	/**
	 * Rewrite one planned link that does not collide, as the system; false when it is skipped or refused.
	 *
	 * @param array{id:string,from:string,to:string,collides:bool,data:array<string,mixed>} $step One entry of plan().
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-1.2
	 */
	public function apply(array $step): bool {
		if ($step['collides'] === true) {
			return false;
		}

		try {
			$this->container->get('OCA\\OpenRegister\\Service\\ObjectService')->saveObject(
				object: ['externalId' => $step['to']] + $step['data'],
				options: new ObjectSaveOptions(
					target: new ObjectFindTarget(register: ProjectMembershipService::REGISTER, schema: ForgeLinkService::SCHEMA),
					uuid: $step['id'],
					policy: new SavePolicy(silent: true)
				),
				_rbac: false,
				_multitenancy: false
			);
		} catch (\Throwable $e) {
			$this->logger->warning('Planninq: could not rewrite a forge id', ['object' => $step['id'], 'exception' => $e->getMessage()]);
			return false;
		}

		// The save is silent, so it leaves no audit row: this line is the record of the rewrite.
		$this->logger->info('Planninq: rewrote a forge id to its canonical form', ['object' => $step['id'], 'from' => $step['from'], 'to' => $step['to']]);

		return true;
	}//end apply()

	/**
	 * The key of one forge id on one task, in canonical form.
	 *
	 * @param array{id:string,data:array<string,mixed>} $link       The link.
	 * @param mixed                                     $externalId Its forge id.
	 *
	 * @return string
	 */
	private function slot(array $link, mixed $externalId): string {
		$canonical = '';
		if (is_string($externalId) === true) {
			$canonical = $this->forgeLinks->canonicalId(id: $externalId);
		}

		return (string)($link['data']['task'] ?? '') . "\n" . $canonical;
	}//end slot()
}//end class
