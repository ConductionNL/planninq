<template>
	<NcDialog
		v-model:open="open"
		:name="t('planninq', 'A timer is already running')"
		@close="$emit('close')">
		<p>{{ t('planninq', 'A timer is running on {running}. Stop it and start one on {next}?', { running: runningTitle, next: nextTitle }) }}</p>
		<template #actions>
			<NcButton variant="primary" data-testid="start-timer-switch" @click="$emit('switch')">
				{{ t('planninq', 'Stop it and start the new one') }}
			</NcButton>
			<NcButton @click="$emit('close')">
				{{ t('planninq', 'Keep the running timer') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcDialog } from '@nextcloud/vue'

/**
 * TimerSwitchDialog: asks whether to stop the timer that runs on another task
 * before starting one on this task.
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.3
 */
export default {
	name: 'TimerSwitchDialog',

	components: { NcButton, NcDialog },

	props: {
		/** Title of the task the running timer belongs to. */
		runningTitle: {
			type: String,
			default: '',
		},

		/** Title of the task about to be timed. */
		nextTitle: {
			type: String,
			default: '',
		},
	},

	emits: ['close', 'switch'],

	data() {
		return { open: true }
	},

	methods: { t },
}
</script>
