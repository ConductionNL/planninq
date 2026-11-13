<?php

/**
 * Tests for the ApplyProjectPolicy repair step.
 *
 * The step re-applies the project reviewer groups into the live schema after a
 * register import; it must report, but never throw, when the write is refused.
 *
 * @category Test
 * @package  OCA\Planninq\Tests\Unit\Repair
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

namespace OCA\Planninq\Tests\Unit\Repair;

use OCA\Planninq\Repair\ApplyProjectPolicy;
use OCA\Planninq\Service\CreationPolicyService;
use OCA\Planninq\Service\ProjectPolicySchemaService;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ApplyProjectPolicy.
 *
 * @covers \OCA\Planninq\Repair\ApplyProjectPolicy
 */
class ApplyProjectPolicyTest extends TestCase {

	/**
	 * The step, with a policy that names the given reviewer groups and a schema
	 * writer whose apply() returns the given result.
	 *
	 * @param array<int,string> $groups   The reviewer groups the policy names.
	 * @param bool              $applied  What the schema write returns.
	 * @param array<int,string> &$written Captures the groups passed to apply().
	 *
	 * @return ApplyProjectPolicy
	 */
	private function step(array $groups, bool $applied, ?array &$written = null): ApplyProjectPolicy {
		$policy = $this->createMock(originalClassName: CreationPolicyService::class);
		$policy->method('reviewerGroups')->willReturn($groups);

		$schema = $this->createMock(originalClassName: ProjectPolicySchemaService::class);
		$schema->method('apply')->willReturnCallback(
			static function (array $groups) use ($applied, &$written): bool {
				$written = $groups;
				return $applied;
			}
		);

		return new ApplyProjectPolicy(policy: $policy, policySchema: $schema);

	}//end step()

	/**
	 * The name states what the step writes.
	 *
	 * @return void
	 */
	public function testGetNameDescribesTheStep(): void {
		$this->assertStringContainsString('reviewers', $this->step(groups: [], applied: true)->getName());

	}//end testGetNameDescribesTheStep()

	/**
	 * A successful write passes the reviewer groups through and warns nobody.
	 *
	 * @return void
	 */
	public function testASuccessfulWriteIsSilent(): void {
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->never())->method('warning');

		$this->step(groups: ['reviewers', 'board'], applied: true, written: $written)->run($output);

		$this->assertSame(['reviewers', 'board'], $written);

	}//end testASuccessfulWriteIsSilent()

	/**
	 * A refused write warns and does not throw.
	 *
	 * @return void
	 */
	public function testARefusedWriteWarns(): void {
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning')->with($this->stringContains('were not written'));

		$this->step(groups: ['reviewers'], applied: false)->run($output);

	}//end testARefusedWriteWarns()
}//end class
