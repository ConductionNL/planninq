<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<NcDialog
		:name="t('planninq', 'A timer is running')"
		@closing="$emit('close')">
		<template #default>
			<p data-testid="timer-switch-question">
				{{ t('planninq', 'A timer is running on {running}. Stop it and start one on {next}?', { running: runningTitle, next: nextTitle }) }}
			</p>
		</template>

		<template #actions>
			<NcButton data-testid="timer-switch-cancel" @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
			<NcButton variant="primary" data-testid="timer-switch-confirm" @click="$emit('confirm')">
				{{ t('planninq', 'Stop and start') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
/**
 * TimerSwitchDialog.
 *
 * Asks before a second timer replaces the running one: one timer runs per
 * person. Confirming stops the running timer the usual way (its time entry
 * form opens) and starts the new one.
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.3
 */
import { NcButton, NcDialog } from '@nextcloud/vue'

export default {
	name: 'TimerSwitchDialog',

	components: { NcButton, NcDialog },

	props: {
		/** Title of the task the running timer is on. */
		runningTitle: {
			type: String,
			required: true,
		},

		/** Title of the task a timer is about to start on. */
		nextTitle: {
			type: String,
			required: true,
		},
	},

	emits: ['close', 'confirm'],
}
</script>
