<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<div v-if="timer"
		class="running-timer"
		role="status"
		data-testid="running-timer">
		<span class="running-timer__label">
			{{ t('planninq', 'Timer running: {minutes} min', { minutes }) }}
		</span>
		<span v-if="warn" class="running-timer__warning" data-testid="running-timer-warning">
			{{ t('planninq', 'This timer has been running for more than 12 hours.') }}
		</span>
		<NcButton
			variant="primary"
			data-testid="running-timer-stop"
			@click="stopping = true">
			{{ t('planninq', 'Stop timer') }}
		</NcButton>
		<NcButton
			variant="tertiary"
			data-testid="running-timer-discard"
			@click="discard">
			{{ t('planninq', 'Discard timer') }}
		</NcButton>
		<TimeEntryDialog
			v-if="stopping"
			:taskId="timer.task"
			:initialMinutes="stoppedMinutes"
			@close="stopping = false"
			@saved="onSaved" />
	</div>
</template>

<script>
/**
 * RunningTimer: shows the person's running timer with Stop and Discard.
 * Stop opens the time entry form prefilled with the measured minutes; the
 * timer clears only once the entry is saved. Cancelling the form books
 * nothing and leaves the timer running.
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.3
 */
import { NcButton } from '@nextcloud/vue'
import TimeEntryDialog from '../dialogs/TimeEntryDialog.vue'
import { useSettingsStore } from '../store/modules/settings.js'
import { useTimeEntriesStore } from '../store/timeEntries.js'
import { elapsedMinutes, timerNeedsWarning } from '../utils/timer.js'

export default {
	name: 'RunningTimer',

	components: { NcButton, TimeEntryDialog },

	emits: ['saved'],

	data() {
		return { now: Date.now(), stopping: false, stoppedMinutes: 0, tick: null }
	},

	computed: {
		/**
		 * @spec exclude Store passthrough, the running timer.
		 */
		timer() {
			return useSettingsStore().settings?.running_timer ?? null
		},

		/**
		 * @spec exclude Display helper, elapsed minutes.
		 */
		minutes() {
			return this.timer ? elapsedMinutes(this.timer.startedAt, this.now) : 0
		},

		/**
		 * @spec exclude Display helper, the twelve hour warning.
		 */
		warn() {
			return this.timer ? timerNeedsWarning(this.timer.startedAt, this.now) : false
		},
	},

	watch: {
		stopping(value) {
			if (value) {
				this.stoppedMinutes = useTimeEntriesStore().stopTimer()?.minutes ?? 0
			}
		},
	},

	async mounted() {
		const settings = useSettingsStore()
		if (!settings.settings || !('running_timer' in settings.settings)) {
			await settings.fetchSettings()
		}
		this.tick = setInterval(() => {
			this.now = Date.now()
		}, 30000)
	},

	beforeUnmount() {
		clearInterval(this.tick)
	},

	methods: {
		/**
		 * Clear the timer without booking anything.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.3
		 */
		async discard() {
			await useTimeEntriesStore().discardTimer()
		},

		/**
		 * The entry is booked: clear the timer and tell the host.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.4
		 */
		async onSaved() {
			this.stopping = false
			await useTimeEntriesStore().discardTimer()
			this.$emit('saved')
		},
	},
}
</script>

<style scoped>
.running-timer {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.running-timer__warning {
	color: var(--color-warning-text);
}
</style>
