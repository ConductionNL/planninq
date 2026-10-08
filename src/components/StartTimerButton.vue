<template>
	<div class="start-timer">
		<NcSelect
			v-if="pick"
			v-model="chosen"
			:options="taskOptions"
			:inputLabel="t('planninq', 'Task to time')"
			:loading="loadingTasks"
			label="label"
			data-testid="start-timer-task" />
		<NcButton variant="secondary"
			:disabled="!target"
			data-testid="start-timer"
			@click="start">
			<template #icon>
				<ClockPlusOutline :size="20" />
			</template>
			{{ t('planninq', 'Start timer') }}
		</NcButton>

		<!-- A timer runs on another task: stop it first, booking its time, or keep it -->
		<NcDialog
			v-if="asking"
			:name="t('planninq', 'A timer is already running')"
			@update:open="asking = false">
			<p>{{ t('planninq', 'A timer is running on {running}. Stop it and start one on {next}?', { running: runningTitle, next: nextTitle }) }}</p>
			<template #actions>
				<NcButton variant="primary" data-testid="start-timer-switch" @click="stopFirst">
					{{ t('planninq', 'Stop it and start the new one') }}
				</NcButton>
				<NcButton @click="asking = false">
					{{ t('planninq', 'Keep the running timer') }}
				</NcButton>
			</template>
		</NcDialog>

		<TimeEntryDialog
			v-if="booking"
			:taskId="booking.task"
			:initialMinutes="booking.minutes"
			@close="booking = null"
			@saved="onBooked" />
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { NcButton, NcDialog, NcSelect } from '@nextcloud/vue'
import ClockPlusOutline from 'vue-material-design-icons/ClockPlusOutline.vue'
import TimeEntryDialog from '../dialogs/TimeEntryDialog.vue'
import { useObjectStore } from '../store/objectStore.js'
import { useProjectsStore } from '../store/projects.js'
import { useTimeEntriesStore } from '../store/timeEntries.js'
import { startDecision } from '../utils/timer.js'

/**
 * StartTimerButton: starts the person's timer on a task. On a task page the
 * task is given; on the Timesheet a task picker chooses it. When a timer already
 * runs on another task it asks whether to stop that one first, which opens the
 * time entry form for the time measured so far.
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.3
 */
export default {
	name: 'StartTimerButton',

	components: { ClockPlusOutline, NcButton, NcDialog, NcSelect, TimeEntryDialog },

	props: {
		/** The task to time. Leave empty together with `pick` to choose one. */
		taskId: {
			type: String,
			default: '',
		},

		/** Show a task picker (the person's own tasks) instead of a fixed task. */
		pick: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['started', 'saved'],

	data() {
		return {
			chosen: null,
			tasks: [],
			loadingTasks: false,
			asking: false,
			booking: null,
			titles: {},
		}
	},

	computed: {
		/**
		 * @spec exclude Store passthrough — the time entries store.
		 */
		store() {
			return useTimeEntriesStore()
		},

		/**
		 * @spec exclude Display helper — the task to time.
		 */
		target() {
			return this.pick ? (this.chosen?.id ?? '') : this.taskId
		},

		/**
		 * @spec exclude Display helper — the picker options.
		 */
		taskOptions() {
			return this.tasks
				.filter((task) => !['done', 'cancelled'].includes(task.status))
				.map((task) => ({ id: task.id, label: String(task.title || task.id) }))
		},

		/**
		 * @spec exclude Display helper — the title of the task the timer runs on.
		 */
		runningTitle() {
			const task = this.store.runningTimer()?.task
			return this.titles[task] || task || ''
		},

		/**
		 * @spec exclude Display helper — the title of the task about to be timed.
		 */
		nextTitle() {
			return this.chosen?.label || this.titles[this.target] || this.target
		},
	},

	async mounted() {
		if (this.pick) {
			this.loadingTasks = true
			this.tasks = await useProjectsStore().fetchMyTasks()
			this.loadingTasks = false
		}
	},

	methods: {
		/**
		 * Start the timer, or ask first when it runs on another task.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.3
		 */
		async start() {
			const decision = startDecision(this.store.runningTimer(), this.target)
			if (decision === 'same') {
				return
			}
			if (decision === 'ask') {
				await this.nameTasks()
				this.asking = true
				return
			}
			await this.begin()
		},

		/**
		 * Start the timer on the target.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.3
		 */
		async begin() {
			if (await this.store.startTimer(this.target)) {
				this.$emit('started', this.target)
				return
			}
			showError(this.t('planninq', 'The timer could not be started. Please try again.'))
		},

		/**
		 * Look up the titles of the two tasks named in the question.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.3
		 */
		async nameTasks() {
			const objectStore = useObjectStore()
			if (!objectStore.objectTypeRegistry?.task) {
				objectStore.registerObjectType('task', 'task', 'planninq')
			}
			for (const id of [this.store.runningTimer()?.task, this.target]) {
				if (id && !this.titles[id]) {
					try {
						this.titles = { ...this.titles, [id]: (await objectStore.fetchObject('task', id))?.title || id }
					} catch {
						this.titles = { ...this.titles, [id]: id }
					}
				}
			}
		},

		/**
		 * Open the time entry form for the running timer; nothing is booked until it is saved.
		 *
		 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.3
		 */
		stopFirst() {
			this.asking = false
			this.booking = this.store.stopTimer()
		},

		/**
		 * The first timer's time is booked: clear it and start the new one.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.3
		 */
		async onBooked() {
			this.booking = null
			await this.store.discardTimer()
			this.$emit('saved')
			await this.begin()
		},
	},
}
</script>

<style scoped>
.start-timer {
	display: flex;
	align-items: flex-end;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 3);
}
</style>
