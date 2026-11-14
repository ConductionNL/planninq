<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<div class="running-timer-host">
		<div v-if="shown" class="running-timer" data-testid="running-timer">
			<TimerOutline :size="20" class="running-timer__icon" />
			<span class="running-timer__task">
				<RouterLink v-if="taskRoute" :to="taskRoute" data-testid="running-timer-task">{{ title }}</RouterLink>
				<span v-else data-testid="running-timer-task">{{ title }}</span>
			</span>
			<span class="running-timer__clock" data-testid="running-timer-clock">{{ clock }}</span>
			<span class="running-timer__actions">
				<NcButton variant="primary"
					data-testid="running-timer-stop"
					:disabled="busy"
					@click="stop">
					{{ t('planninq', 'Stop') }}
				</NcButton>
				<NcButton variant="tertiary"
					data-testid="running-timer-discard"
					:disabled="busy"
					@click="discard">
					{{ t('planninq', 'Discard') }}
				</NcButton>
			</span>
		</div>
		<!-- Announced on start and stop only; the ticking clock is not a live region. -->
		<span class="hidden-visually" role="status">{{ announcement }}</span>

		<TimerSwitchDialog v-if="switchTo"
			:runningTitle="title"
			:nextTitle="switchTo.title"
			@close="switchTo = null"
			@confirm="confirmSwitch" />

		<TimeEntryDialog v-if="stopped"
			:taskId="stopped.task"
			:prefillMinutes="stopped.minutes"
			:longHours="stopped.long ? stopped.hours : 0"
			@close="stopped = null"
			@saved="onSaved" />
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
/**
 * RunningTimer.
 *
 * The person's running timer: the task, the elapsed time and Stop and
 * Discard. Stop opens the time entry form with the measured time filled in,
 * so nothing is booked until the person saves; Discard clears the timer.
 * Shown on My tasks and the Timesheet, and on the task page of the task it
 * runs on (`task`). startOn() starts a timer from the page around it, asking
 * first when one already runs on another task.
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.3
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.4
 */
import { NcButton } from '@nextcloud/vue'
import TimerOutline from 'vue-material-design-icons/TimerOutline.vue'
import TimeEntryDialog from '../dialogs/TimeEntryDialog.vue'
import TimerSwitchDialog from '../dialogs/TimerSwitchDialog.vue'
import { useObjectStore } from '../store/objectStore.js'
import { useTimeEntriesStore } from '../store/timeEntries.js'
import { elapsedClock } from '../utils/timer.js'

export default {
	name: 'RunningTimer',

	components: { NcButton, TimeEntryDialog, TimerOutline, TimerSwitchDialog },

	props: {
		/** Show the timer only when it runs on this task; empty shows it on any task. */
		task: {
			type: String,
			default: '',
		},
	},

	emits: ['booked'],

	data() {
		return {
			store: useTimeEntriesStore(),
			now: Date.now(),
			ticker: null,
			taskInfo: null,
			busy: false,
			stopped: null,
			switchTo: null,
			announcement: '',
		}
	},

	computed: {
		/**
		 * @spec exclude Store passthrough — the running timer.
		 */
		timer() {
			return this.store.runningTimer
		},

		/**
		 * Whether to show the timer here: one runs, on this page's task when it has one.
		 *
		 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.3
		 */
		shown() {
			return !!this.timer && (!this.task || this.timer.task === this.task)
		},

		/**
		 * @spec exclude Display glue — the elapsed time as a clock.
		 */
		clock() {
			return this.timer ? elapsedClock(this.timer.startedAt, this.now) : ''
		},

		/**
		 * @spec exclude Display glue — the task title, the id until it resolves.
		 */
		title() {
			return this.taskInfo?.title || this.timer?.task || this.stopped?.task || ''
		},

		/**
		 * @spec exclude Display glue — the route to the task page.
		 */
		taskRoute() {
			const project = this.taskInfo?.project
			const task = this.timer?.task
			return project && task ? { name: 'TaskDetail', params: { id: project, taskId: task } } : null
		},
	},

	watch: {
		/**
		 * @spec exclude Display glue — resolves the title when the timer moves to another task, and announces it.
		 */
		'timer.task': {
			handler(task, before) {
				if (task && task !== before) {
					this.resolveTask(task).then((info) => {
						this.taskInfo = info
						this.announcement = this.t('planninq', 'Timer started on {task}', { task: this.title })
					})
				}
			},
		},
	},

	/**
	 * @spec exclude Lifecycle glue — reads the timer and ticks the clock.
	 */
	async mounted() {
		this.ticker = setInterval(() => {
			this.now = Date.now()
		}, 1000)
		await this.store.loadTimer()
		if (this.timer) {
			this.taskInfo = await this.resolveTask(this.timer.task)
		}
	},

	/**
	 * @spec exclude Lifecycle glue — stops the clock.
	 */
	beforeUnmount() {
		clearInterval(this.ticker)
	},

	methods: {
		/**
		 * The task's title and project, or null when it no longer exists.
		 *
		 * @param {string} id Task UUID.
		 * @return {Promise<object|null>}
		 *
		 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.3
		 */
		async resolveTask(id) {
			const objectStore = useObjectStore()
			if (!objectStore.objectTypeRegistry?.task) {
				objectStore.registerObjectType('task', 'task', 'planninq')
			}
			try {
				const task = await objectStore.fetchObject('task', id)
				return task ? { title: task.title || id, project: task.project || '' } : null
			} catch {
				return null
			}
		},

		/**
		 * Start a timer on a task; when one runs on another task, ask first.
		 *
		 * @param {string} taskId Task UUID.
		 * @param {string} taskTitle Its title, for the question.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.3
		 */
		async startOn(taskId, taskTitle) {
			this.busy = true
			try {
				const running = await this.store.loadTimer(true)
				if (running && running.task === taskId) {
					return
				}
				if (running) {
					this.taskInfo = (await this.resolveTask(running.task)) || this.taskInfo
					this.switchTo = { task: taskId, title: taskTitle || taskId }
					return
				}
				if (!(await this.store.startTimer(taskId))) {
					showError(this.t('planninq', 'Could not update the timer. Please try again.'))
				}
			} finally {
				this.busy = false
			}
		},

		/**
		 * Stop the running timer (its form opens) and start the one asked for.
		 *
		 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.3
		 */
		async confirmSwitch() {
			const next = this.switchTo
			this.switchTo = null
			await this.stop()
			if (!this.store.runningTimer && !(await this.store.startTimer(next.task))) {
				showError(this.t('planninq', 'Could not update the timer. Please try again.'))
			}
		},

		/**
		 * Stop the timer and open the prefilled form; a task that is gone discards it.
		 *
		 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.4
		 */
		async stop() {
			this.busy = true
			try {
				const exists = await this.resolveTask(this.timer.task)
				const stopped = await this.store.stopTimer()
				if (!stopped) {
					showError(this.t('planninq', 'Could not update the timer. Please try again.'))
					return
				}
				this.announcement = this.t('planninq', 'Timer stopped')
				if (!exists) {
					showError(this.t('planninq', 'This task no longer exists. The timer was discarded.'))
					return
				}
				this.taskInfo = exists
				this.stopped = stopped
			} finally {
				this.busy = false
			}
		},

		/**
		 * Discard the timer; nothing is booked.
		 *
		 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.3
		 */
		async discard() {
			this.busy = true
			try {
				if (await this.store.discardTimer()) {
					this.announcement = this.t('planninq', 'Timer discarded')
				} else {
					showError(this.t('planninq', 'Could not update the timer. Please try again.'))
				}
			} finally {
				this.busy = false
			}
		},

		/**
		 * @spec exclude UI glue — closes the form after the entry was booked.
		 */
		onSaved() {
			this.stopped = null
			this.$emit('booked')
		},
	},
}
</script>

<style scoped>
.running-timer-host {
	display: contents;
}

.running-timer {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px 12px;
	margin: 8px 0 16px;
	padding: 8px 12px;
	border: 2px solid var(--color-primary-element);
	border-radius: var(--border-radius-large);
	background: var(--color-primary-element-light);
	color: var(--color-main-text);
}

.running-timer__task {
	flex: 1 1 160px;
	min-width: 0;
	overflow-wrap: anywhere;
	font-weight: 600;
}

.running-timer__clock {
	font-variant-numeric: tabular-nums;
}

.running-timer__actions {
	display: flex;
	gap: 8px;
}
</style>
