<template>
	<div class="project-timeline">
		<!-- Breadcrumb back to the board -->
		<div class="project-timeline__breadcrumb">
			<NcButton variant="tertiary"
				:aria-label="t('planninq', 'Back to board')"
				@click="$router.push({ name: 'ProjectBoard', params: { id: projectId } })">
				<template #icon>
					<ArrowLeft :size="18" />
				</template>
				{{ projectTitle }}
			</NcButton>
			<span class="project-timeline__crumb-sep" aria-hidden="true">/</span>
			<span>{{ view === 'roadmap' ? t('planninq', 'Roadmap') : t('planninq', 'Timeline') }}</span>
		</div>

		<ProjectTabs :projectId="projectId" />

		<!-- Header + zoom control -->
		<div class="project-timeline__header">
			<h2 class="project-timeline__title">
				{{ view === 'roadmap' ? t('planninq', 'Roadmap') : t('planninq', 'Timeline') }}
			</h2>
			<div class="project-timeline__zoom">
				<div class="project-timeline__view-switch"
					role="group"
					:aria-label="t('planninq', 'Tasks or roadmap')">
					<NcButton :variant="view === 'tasks' ? 'primary' : 'tertiary'"
						:aria-pressed="view === 'tasks'"
						data-testid="timeline-view-tasks"
						@click="setView('tasks')">
						{{ t('planninq', 'Tasks') }}
					</NcButton>
					<NcButton :variant="view === 'roadmap' ? 'primary' : 'tertiary'"
						:aria-pressed="view === 'roadmap'"
						data-testid="timeline-view-roadmap"
						@click="setView('roadmap')">
						{{ t('planninq', 'Roadmap') }}
					</NcButton>
				</div>
				<NcButton v-if="isOwner && view === 'tasks'"
					data-testid="msproject-import-open"
					@click="showImport = true">
					<template #icon>
						<FileImportOutline :size="20" />
					</template>
					{{ t('planninq', 'Import from Microsoft Project') }}
				</NcButton>
				<NcSelect v-model="zoom"
					:options="zoomOptions"
					:inputLabel="t('planninq', 'Zoom')"
					:aria-label-combobox="t('planninq', 'Zoom level')"
					:clearable="false"
					label="label"
					trackBy="value" />
			</div>
		</div>

		<!-- Roadmap view (backlog-releases-roadmap) -->
		<ProjectRoadmap v-if="view === 'roadmap'"
			:projectId="projectId"
			:pxPerDay="pxPerDay" />

		<!-- Loading -->
		<div v-else-if="loading" class="project-timeline__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<!-- Error -->
		<NcEmptyContent v-else-if="error"
			:name="t('planninq', 'Could not load the timeline')"
			:description="error">
			<template #icon>
				<AlertCircleOutline :size="20" />
			</template>
		</NcEmptyContent>

		<template v-else>
			<!-- Gantt grid -->
			<div v-if="scheduledTasks.length" class="project-timeline__scroll">
				<div class="project-timeline__chart" :style="{ width: chartWidth + 'px' }">
					<!-- Day axis -->
					<div class="project-timeline__axis">
						<div v-for="tick in axisTicks"
							:key="tick.iso"
							class="project-timeline__tick"
							:class="{ 'project-timeline__tick--weekend': tick.weekend }"
							:role="tick.holiday ? 'img' : null"
							:aria-label="tick.holiday || null"
							:title="tick.holiday || null"
							:data-date="tick.iso"
							:data-non-working="tick.weekend ? 'true' : null"
							:style="{ left: tick.x + 'px', width: pxPerDay + 'px' }">
							<span class="project-timeline__tick-label">{{ tick.label }}</span>
						</div>
					</div>

					<!-- Bars + dependency overlay -->
					<div class="project-timeline__bars" :style="{ height: barsHeight + 'px' }">
						<!-- Today marker -->
						<div v-if="todayX !== null"
							class="project-timeline__today"
							:style="{ left: todayX + 'px' }"
							:title="t('planninq', 'Today')"
							aria-hidden="true" />

						<!-- Dependency arrows -->
						<svg class="project-timeline__edges"
							:width="chartWidth"
							:height="barsHeight"
							aria-hidden="true">
							<defs>
								<marker id="planninq-timeline-arrow"
									markerWidth="6"
									markerHeight="6"
									refX="5"
									refY="3"
									orient="auto">
									<path d="M0,0 L6,3 L0,6 Z" fill="var(--color-text-maxcontrast)" />
								</marker>
							</defs>
							<line v-for="edge in edgeLines"
								:key="edge.key"
								data-testid="timeline-edge"
								:x1="edge.x1"
								:y1="edge.y1"
								:x2="edge.x2"
								:y2="edge.y2"
								stroke="var(--color-text-maxcontrast)"
								stroke-width="1.5"
								:stroke-dasharray="edge.related ? '4 3' : null"
								:marker-end="edge.related ? null : 'url(#planninq-timeline-arrow)'" />
						</svg>

						<!-- Task bars -->
						<button v-for="bar in taskBars"
							:key="bar.id"
							:ref="`bar-${bar.id}`"
							type="button"
							class="project-timeline__bar"
							:class="{ 'project-timeline__bar--dragging': drag && drag.id === bar.id }"
							:style="barStyle(bar)"
							:title="bar.tooltip"
							:aria-label="barLabel(bar)"
							:data-task-id="bar.id"
							data-testid="timeline-bar"
							@pointerdown="startDrag($event, bar, 'move')"
							@pointermove="moveDrag"
							@pointerup="endDrag"
							@pointercancel="cancelDrag"
							@keydown.left.prevent="keyMove(bar, -1, $event.shiftKey)"
							@keydown.right.prevent="keyMove(bar, 1, $event.shiftKey)"
							@click="openDates(bar)">
							<span class="project-timeline__handle project-timeline__handle--start"
								data-testid="timeline-bar-start"
								aria-hidden="true"
								@pointerdown.stop="startDrag($event, bar, 'start')" />
							<span class="project-timeline__bar-label">{{ bar.title }}</span>
							<span class="project-timeline__handle project-timeline__handle--due"
								data-testid="timeline-bar-due"
								aria-hidden="true"
								@pointerdown.stop="startDrag($event, bar, 'due')" />
						</button>
					</div>
				</div>
			</div>

			<!-- No scheduled tasks -->
			<NcEmptyContent v-else
				:name="t('planninq', 'No scheduled tasks')"
				:description="t('planninq', 'Tasks with a start or due date appear on the timeline. Add dates to see them here.')">
				<template #icon>
					<ChartTimeline :size="20" />
				</template>
			</NcEmptyContent>

			<!-- Unscheduled rail -->
			<div v-if="unscheduled.length" class="project-timeline__unscheduled">
				<h3 class="project-timeline__unscheduled-title">
					{{ t('planninq', 'Unscheduled') }} ({{ unscheduled.length }})
				</h3>
				<ul class="project-timeline__unscheduled-list">
					<li v-for="task in unscheduled"
						:key="task.id"
						class="project-timeline__unscheduled-item">
						<span class="project-timeline__unscheduled-dot"
							:style="{ backgroundColor: statusColor(task.status) }"
							aria-hidden="true" />
						{{ task.title || t('planninq', 'Untitled task') }}
					</li>
				</ul>
			</div>
		</template>

		<p class="hidden-visually"
			aria-live="polite"
			data-testid="timeline-announcement">
			{{ announcement }}
		</p>
		<p v-if="saveError"
			class="project-timeline__save-error"
			role="alert"
			data-testid="timeline-save-error">
			{{ saveError }}
		</p>

		<RescheduleDialog v-if="reschedule"
			:task="reschedule.task"
			:moves="reschedule.moves"
			@all="rescheduleAll"
			@only="rescheduleOnly"
			@cancel="reschedule = null" />

		<TaskDatesDialog v-if="datesTask"
			:task="datesTask"
			@save="saveFromDialog"
			@close="closeDates" />

		<MsProjectImportDialog v-if="showImport"
			:projectId="projectId"
			@imported="load"
			@close="showImport = false" />
	</div>
</template>

<script>
/**
 * ProjectTimeline view — Gantt / timeline for a project.
 *
 * Renders the project's scheduled tasks as bars on a horizontal day axis
 * (day/week/month zoom), draws dependency arrows sourced from the existing
 * stored links, lists dateless tasks in an "unscheduled" rail, and marks today.
 * It reads through the stateless, read-only timeline API; the view itself
 * edits one thing, the existing startDate and dueDate of a task, through the
 * same object PATCH (`updateTask`) and member rights the board uses, by
 * dragging a bar or its ends, with Left and Right, or in TaskDatesDialog
 * (planning-timeline-editing). It creates nothing and adds no schema; the
 * dependency edges are rendered, not re-derived. All strings go through t(); no DOM data reads.
 *
 * @spec openspec/changes/gantt-timeline-view/specs/gantt-timeline-view/spec.md
 */
import { getCurrentUser } from '@nextcloud/auth'
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcEmptyContent, NcLoadingIcon, NcSelect } from '@nextcloud/vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import ChartTimeline from 'vue-material-design-icons/ChartTimeline.vue'
import FileImportOutline from 'vue-material-design-icons/FileImportOutline.vue'
import ProjectRoadmap from '../components/ProjectRoadmap.vue'
import ProjectTabs from '../components/ProjectTabs.vue'
import MsProjectImportDialog from '../dialogs/MsProjectImportDialog.vue'
import RescheduleDialog from '../dialogs/RescheduleDialog.vue'
import TaskDatesDialog from '../dialogs/TaskDatesDialog.vue'
import { fetchProjectTimeline } from '../api/timeline.js'
import { useSettingsStore } from '../store/modules/settings.js'
import { useProjectsStore } from '../store/projects.js'
import { mayImport } from '../utils/msprojectImport.js'
import { cascade, writeRun } from '../utils/scheduling.js'
import { keyStep, moveTo, resizeTo, shiftDays } from '../utils/timelineEditing.js'
import {
	buildLayout,
	MS_PER_DAY,
	PX_PER_DAY,
	STATUS_COLORS,
	toScheduled,
} from '../utils/timelineHelpers.js'
import { isWorkingDay, normaliseCalendar } from '../utils/workingCalendar.js'

export default {
	name: 'ProjectTimeline',

	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcSelect,
		ArrowLeft,
		AlertCircleOutline,
		ChartTimeline,
		FileImportOutline,
		MsProjectImportDialog,
		RescheduleDialog,
		TaskDatesDialog,
		ProjectRoadmap,
		ProjectTabs,
	},

	data() {
		return {
			loading: true,
			error: null,
			tasks: [],
			unscheduled: [],
			dependencies: [],
			calendar: normaliseCalendar({}),
			drag: null,
			datesTask: null,
			announcement: '',
			saveError: '',
			suppressClick: false,
			reschedule: null,
			showImport: false,
			zoom: { value: 'day', label: t('planninq', 'Day') },
			zoomOptions: [
				{ value: 'day', label: t('planninq', 'Day') },
				{ value: 'week', label: t('planninq', 'Week') },
				{ value: 'month', label: t('planninq', 'Month') },
			],
		}
	},

	computed: {
		/**
		 * @spec exclude Trivial route param getter.
		 */
		projectId() {
			return this.$route.params.id
		},

		/**
		 * Which view the page shows: the task Gantt, or the roadmap of
		 * releases and epics (?view=roadmap, so a link or reload keeps it).
		 *
		 * @return {string} 'tasks' or 'roadmap'.
		 *
		 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.1
		 */
		view() {
			return this.$route.query?.view === 'roadmap' ? 'roadmap' : 'tasks'
		},

		/**
		 * @spec exclude Trivial display getter — project title with UUID fallback.
		 */
		projectTitle() {
			return useProjectsStore().activeProject?.title || this.projectId
		},

		/**
		 * Whether the current user may import a plan: the project owner or an
		 * admin. The server enforces the same rule (ProjectImportController).
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/integration-msproject-import/tasks.md#task-3.1
		 */
		isOwner() {
			return mayImport(useProjectsStore().activeProject, getCurrentUser())
		},

		/**
		 * @spec exclude Trivial getter — pixels per day for the active zoom.
		 */
		pxPerDay() {
			return PX_PER_DAY[this.zoom?.value] || PX_PER_DAY.day
		},

		/**
		 * Scheduled tasks with both endpoints resolved to day indices.
		 *
		 * @return {Array<object>} Tasks carrying numeric startDay/endDay.
		 *
		 * @spec openspec/changes/gantt-timeline-view/specs/gantt-timeline-view/spec.md#requirement-a-projects-tasks-can-be-viewed-on-a-time-axis
		 */
		scheduledTasks() {
			return toScheduled(this.tasks)
		},

		/**
		 * Positioned bars + dependency arrows + chart dimensions (pure helper).
		 *
		 * @return {object} The layout from {@link buildLayout}.
		 *
		 * @spec openspec/changes/gantt-timeline-view/specs/gantt-timeline-view/spec.md#requirement-the-timeline-renders-the-existing-dependency-links-not-a-new-copy
		 */
		layout() {
			return buildLayout(this.scheduledTasks, this.dependencies, this.pxPerDay)
		},

		/**
		 * @spec exclude Trivial getter — earliest scheduled day index.
		 */
		minDay() {
			return this.layout.minDay
		},

		/**
		 * @spec exclude Trivial getter — latest scheduled day index.
		 */
		maxDay() {
			return this.layout.maxDay
		},

		/**
		 * @spec exclude Trivial getter — inclusive day span of the chart.
		 */
		dayCount() {
			return this.layout.dayCount
		},

		/**
		 * @spec exclude Trivial getter — chart width in pixels.
		 */
		chartWidth() {
			return this.layout.chartWidth
		},

		/**
		 * @spec exclude Trivial getter — bar area height in pixels.
		 */
		barsHeight() {
			return this.layout.barsHeight
		},

		/**
		 * @spec exclude Presentational — bars with display title + hover tooltip.
		 */
		taskBars() {
			const tooltips = {}
			this.scheduledTasks.forEach((task) => {
				tooltips[task.id] = this.barTooltip(task)
			})
			return this.layout.bars.map((bar) => ({
				...bar,
				title: bar.title || t('planninq', 'Untitled task'),
				tooltip: tooltips[bar.id] || bar.title,
			}))
		},

		/**
		 * @spec exclude Trivial getter — dependency arrow lines from the layout.
		 */
		edgeLines() {
			return this.layout.edgeLines
		},

		/**
		 * Day axis ticks (one per day), labelled, shaded on every non-working
		 * day of the working calendar and named on a listed holiday.
		 *
		 * @return {Array<object>} Ticks with iso/label/x/weekend/holiday.
		 *
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.4
		 */
		axisTicks() {
			const ticks = []
			for (let d = 0; d < this.dayCount; d++) {
				const date = new Date((this.minDay + d) * MS_PER_DAY)
				const iso = date.toISOString().slice(0, 10)
				ticks.push({
					iso,
					label: this.tickLabel(date, d),
					x: d * this.pxPerDay,
					weekend: !isWorkingDay(iso, this.calendar),
					holiday: this.calendar.holidays.get(iso) || '',
				})
			}
			return ticks
		},

		/**
		 * @spec exclude Presentational — today marker x, or null when off-range.
		 */
		todayX() {
			const today = Math.floor(Date.now() / MS_PER_DAY)
			if (today < this.minDay || today > this.maxDay) {
				return null
			}
			return (today - this.minDay) * this.pxPerDay
		},
	},

	watch: {
		/**
		 * Reload the timeline when the route switches to another project.
		 *
		 * @spec exclude Trivial reactive reload on route change.
		 */
		projectId() {
			this.load()
		},
	},

	/**
	 * Hydrate the active project (for the breadcrumb title) then load the
	 * timeline for the current project.
	 *
	 * @return {Promise<void>}
	 *
	 * @spec openspec/changes/gantt-timeline-view/specs/gantt-timeline-view/spec.md#requirement-a-projects-tasks-can-be-viewed-on-a-time-axis
	 */
	async mounted() {
		const settings = useSettingsStore()
		settings.fetchSettings().then(() => {
			this.calendar = normaliseCalendar(settings.settings)
		}).catch(() => {})
		const store = useProjectsStore()
		if (!store.activeProject || store.activeProject.id !== this.projectId) {
			await store.fetchProject(this.projectId).catch(() => {})
		}
		await this.load()
	},

	methods: {
		/**
		 * A bar's position, shifted while it is being dragged.
		 *
		 * @param {object} bar The bar
		 * @return {object} The inline style
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-2.2
		 */
		barStyle(bar) {
			let left = bar.left
			let width = bar.width
			if (this.drag && this.drag.id === bar.id) {
				const dx = this.drag.dx
				if (this.drag.mode === 'move') {
					left += dx
				} else if (this.drag.mode === 'start') {
					left += Math.min(dx, width - this.pxPerDay)
					width -= Math.min(dx, width - this.pxPerDay)
				} else {
					width = Math.max(this.pxPerDay, width + dx)
				}
			}
			return { left: left + 'px', width: width + 'px', top: bar.top + 'px', backgroundColor: bar.color }
		},

		/**
		 * @param {object} bar The bar
		 * @return {string} The bar's accessible name: title and dates
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-2.2
		 */
		barLabel(bar) {
			const task = this.taskById(bar.id)
			return t('planninq', '{title}, from {start} to {end}', { title: bar.title, start: this.dayName(task?.startDate), end: this.dayName(task?.dueDate) })
		},

		/**
		 * @param {string} id The task id
		 * @return {object|undefined} The task
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-2.2
		 */
		taskById(id) {
			return this.tasks.find((task) => task.id === id)
		},

		/**
		 * @param {string} date A date
		 * @return {string} Day and month in words
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-2.2
		 */
		dayName(date) {
			if (!date) {
				return ''
			}
			return new Date(`${String(date).slice(0, 10)}T00:00:00Z`).toLocaleDateString(undefined, { day: 'numeric', month: 'long', timeZone: 'UTC' })
		},

		/**
		 * @param {PointerEvent} event The pointer
		 * @param {object} bar The bar
		 * @param {'move'|'start'|'due'} mode What the drag changes
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-2.2
		 */
		startDrag(event, bar, mode) {
			if (event.button !== 0) {
				return
			}
			this.drag = { id: bar.id, mode, x: event.clientX, dx: 0, moved: false }
			this.$refs[`bar-${bar.id}`]?.[0]?.setPointerCapture?.(event.pointerId)
		},

		/**
		 * @param {PointerEvent} event The pointer
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-2.2
		 */
		moveDrag(event) {
			if (!this.drag) {
				return
			}
			this.drag.dx = event.clientX - this.drag.x
			this.drag.moved = this.drag.moved || Math.abs(this.drag.dx) > 3
		},

		/**
		 * Release: turn the distance into whole days and save.
		 *
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-2.2
		 */
		async endDrag() {
			const drag = this.drag
			if (!drag) {
				return
			}
			this.suppressClick = drag.moved
			this.drag = null
			const days = Math.round(drag.dx / this.pxPerDay)
			const task = this.taskById(drag.id)
			if (!drag.moved || days === 0 || !task) {
				return
			}
			const start = String(task.startDate).slice(0, 10)
			const due = String(task.dueDate).slice(0, 10)
			const dates = drag.mode === 'move'
				? moveTo(task, shiftDays(start, days), this.calendar)
				: resizeTo(task, drag.mode, shiftDays(drag.mode === 'start' ? start : due, days))
			await this.saveDates(task, dates)
		},

		/**
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-2.2
		 */
		cancelDrag() {
			this.drag = null
		},

		/**
		 * Left or Right: one working day; with Shift only the due date.
		 *
		 * @param {object} bar The bar
		 * @param {number} step 1 or -1
		 * @param {boolean} dueOnly Whether Shift was held
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-2.2
		 */
		async keyMove(bar, step, dueOnly) {
			const task = this.taskById(bar.id)
			if (task) {
				await this.saveDates(task, keyStep(task, step, dueOnly, this.calendar))
				this.$nextTick(() => this.$refs[`bar-${bar.id}`]?.[0]?.focus())
			}
		},

		/**
		 * A click (Enter, Space or a tap) opens the dates dialog; the end of a drag does not.
		 *
		 * @param {object} bar The bar
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-2.2
		 */
		openDates(bar) {
			if (this.suppressClick) {
				this.suppressClick = false
				return
			}
			this.datesTask = this.taskById(bar.id) || null
		},

		/**
		 * @param {{startDate: string, dueDate: string}} dates The dates from the dialog
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-2.2
		 */
		async saveFromDialog(dates) {
			const task = this.datesTask
			this.closeDates()
			if (task) {
				await this.saveDates(task, dates)
			}
		},

		/**
		 * Close the dialog and give focus back to the bar.
		 *
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-2.2
		 */
		closeDates() {
			const id = this.datesTask?.id
			this.datesTask = null
			this.$nextTick(() => this.$refs[`bar-${id}`]?.[0]?.focus())
		},

		/**
		 * A task's new dates: on a project with auto-scheduling, a later due
		 * date that pushes blocked tasks shows the preview first and writes
		 * nothing yet; otherwise the task is written at once.
		 *
		 * @param {object} task The task
		 * @param {{startDate: string, dueDate: string}} dates The new dates
		 * @return {Promise<boolean>} Whether the task was written
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-3.3
		 */
		async saveDates(task, dates) {
			if (task.startDate === dates.startDate && task.dueDate === dates.dueDate) {
				return true
			}
			const auto = useProjectsStore().activeProject?.autoSchedule === true
			if (auto && String(dates.dueDate) > String(task.dueDate).slice(0, 10)) {
				const moves = cascade(this.tasks, this.dependencies, task.id, dates, this.calendar)
				if (moves.length > 0) {
					this.reschedule = { task, dates, moves }
					return false
				}
			}
			return this.writeDates(task, dates)
		},

		/**
		 * Write one task's dates: shown at once, put back when the write fails.
		 *
		 * @param {object} task The task
		 * @param {{startDate: string, dueDate: string}} dates The new dates
		 * @return {Promise<boolean>} Whether the write succeeded
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-2.2
		 */
		async writeDates(task, dates) {
			const old = { startDate: task.startDate, dueDate: task.dueDate }
			this.saveError = ''
			Object.assign(task, dates)
			const saved = await useProjectsStore().updateTask(task.id, dates)
			if (!saved) {
				Object.assign(task, old)
				this.saveError = t('planninq', 'The dates could not be saved.')
				return false
			}
			this.announcement = t('planninq', '{title} now runs from {start} to {end}', { title: task.title, start: this.dayName(dates.startDate), end: this.dayName(dates.dueDate) })
			return true
		},

		/**
		 * Move all: the task, then every pushed task in order, stopping at the
		 * first refused write; then read the timeline again.
		 *
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-3.3
		 */
		async rescheduleAll() {
			const { task, dates, moves } = this.reschedule
			this.reschedule = null
			if (!(await this.writeDates(task, dates))) {
				return
			}
			const store = useProjectsStore()
			const result = await writeRun(moves, (id, to) => store.updateTask(id, to))
			await this.load()
			if (result.failed) {
				this.saveError = t('planninq', 'Stopped at {title}: its dates could not be saved. The tasks before it were moved.', { title: result.failed.title })
			}
		},

		/**
		 * Only this task: write the dragged task and leave the others.
		 *
		 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-3.3
		 */
		async rescheduleOnly() {
			const { task, dates } = this.reschedule
			this.reschedule = null
			await this.writeDates(task, dates)
		},

		/**
		 * Switch between the task Gantt and the roadmap.
		 *
		 * @param {string} view 'tasks' or 'roadmap'.
		 *
		 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.1
		 */
		setView(view) {
			if (view === this.view) {
				return
			}
			this.$router.replace({ name: 'ProjectTimeline', params: { id: this.projectId }, query: view === 'roadmap' ? { view: 'roadmap' } : {} })
		},

		/**
		 * Fetch the timeline payload for the current project (read-only).
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/gantt-timeline-view/specs/gantt-timeline-view/spec.md#requirement-a-projects-tasks-can-be-viewed-on-a-time-axis
		 */
		async load() {
			this.loading = true
			this.error = null
			try {
				const payload = await fetchProjectTimeline(this.projectId)
				this.tasks = payload.tasks
				this.unscheduled = payload.unscheduled
				this.dependencies = payload.dependencies
			} catch (err) {
				this.error = err?.response?.status === 403
					? t('planninq', 'You do not have access to this project.')
					: t('planninq', 'An unexpected error occurred.')
				this.tasks = []
				this.unscheduled = []
				this.dependencies = []
			} finally {
				this.loading = false
			}
		},

		/**
		 * Resolve a task status to its bar colour.
		 *
		 * @param {string} status The task status.
		 * @return {string} A CSS colour value.
		 *
		 * @spec exclude Trivial map lookup — status → CSS colour.
		 */
		statusColor(status) {
			return STATUS_COLORS[status] || STATUS_COLORS.open
		},

		/**
		 * Axis tick label for a day, thinned out per the active zoom level.
		 *
		 * @param {Date} date The tick's date.
		 * @param {number} index The tick's zero-based day index.
		 * @return {string} The label (may be empty to reduce clutter).
		 *
		 * @spec exclude Presentational — axis tick label per zoom level.
		 */
		tickLabel(date, index) {
			if (this.zoom?.value === 'month') {
				return date.getUTCDate() === 1 ? date.toLocaleString(undefined, { month: 'short', timeZone: 'UTC' }) : ''
			}
			if (this.zoom?.value === 'week') {
				return index % 7 === 0 ? String(date.getUTCDate()) : ''
			}
			return String(date.getUTCDate())
		},

		/**
		 * Build the hover tooltip text for a task bar.
		 *
		 * @param {object} task The task row.
		 * @return {string} The tooltip text.
		 *
		 * @spec exclude Presentational — bar hover tooltip text.
		 */
		barTooltip(task) {
			const parts = [task.title || t('planninq', 'Untitled task')]
			if (task.startDate) {
				parts.push(t('planninq', 'Start: {date}', { date: task.startDate }))
			}
			if (task.dueDate) {
				parts.push(t('planninq', 'Due: {date}', { date: task.dueDate }))
			}
			return parts.join(' · ')
		},
	},
}
</script>

<style scoped>
.project-timeline {
	padding: 8px 4px 24px;
}

.project-timeline__breadcrumb {
	display: flex;
	align-items: center;
	gap: 4px;
	margin-bottom: 8px;
	font-size: 14px;
	color: var(--color-text-maxcontrast);
}

.project-timeline__crumb-sep {
	margin: 0 2px;
}

.project-timeline__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	margin-bottom: 16px;
}

.project-timeline__title {
	margin: 0;
	font-size: 22px;
	font-weight: 600;
}

.project-timeline__zoom {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 8px;
	min-width: 160px;
}

.project-timeline__view-switch {
	display: flex;
	gap: 4px;
}

.project-timeline__loading {
	display: flex;
	justify-content: center;
	padding: 48px 0;
}

.project-timeline__scroll {
	overflow-x: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	background: var(--color-main-background);
}

.project-timeline__chart {
	position: relative;
	min-width: 100%;
}

.project-timeline__axis {
	position: relative;
	height: 28px;
	border-bottom: 1px solid var(--color-border);
}

.project-timeline__tick {
	position: absolute;
	top: 0;
	height: 100%;
	box-sizing: border-box;
	border-inline-start: 1px solid var(--color-border-dark, var(--color-border));
	font-size: 11px;
	color: var(--color-text-maxcontrast);
	text-align: center;
}

.project-timeline__tick--weekend {
	background: var(--color-background-hover);
}

.project-timeline__tick-label {
	display: inline-block;
	padding-top: 6px;
	pointer-events: none;
}

.project-timeline__bars {
	position: relative;
}

.project-timeline__today {
	position: absolute;
	top: 0;
	bottom: 0;
	width: 2px;
	background: var(--color-error);
	z-index: 2;
}

.project-timeline__edges {
	position: absolute;
	top: 0;
	inset-inline-start: 0;
	pointer-events: none;
	z-index: 1;
}

.project-timeline__bar {
	position: absolute;
	height: 28px;
	border-radius: 6px;
	display: flex;
	align-items: center;
	padding: 0 8px;
	box-sizing: border-box;
	overflow: hidden;
	z-index: 3;
	color: var(--color-primary-element-text, #fff);
}

.project-timeline__bar-label {
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	font-size: 12px;
	font-weight: 500;
}

.project-timeline__unscheduled {
	margin-top: 20px;
}

.project-timeline__unscheduled-title {
	margin: 0 0 8px;
	font-size: 15px;
	font-weight: 600;
}

.project-timeline__unscheduled-list {
	list-style: none;
	padding: 0;
	margin: 0;
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.project-timeline__unscheduled-item {
	display: flex;
	align-items: center;
	gap: 6px;
	padding: 4px 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-pill, 16px);
	font-size: 13px;
	background: var(--color-background-hover);
}

.project-timeline__unscheduled-dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	flex: 0 0 auto;
}

.project-timeline__bar {
	border: none;
	padding: 0;
	font: inherit;
	color: inherit;
	text-align: start;
	cursor: grab;
	touch-action: none;
}

.project-timeline__bar:focus-visible {
	outline: 2px solid var(--color-main-text);
	outline-offset: 2px;
}

.project-timeline__bar--dragging {
	cursor: grabbing;
	opacity: 0.8;
}

.project-timeline__handle {
	position: absolute;
	top: 0;
	bottom: 0;
	width: 8px;
	cursor: ew-resize;
}

.project-timeline__handle--start {
	inset-inline-start: 0;
}

.project-timeline__handle--due {
	inset-inline-end: 0;
}

.project-timeline__save-error {
	color: var(--color-error-text);
}
</style>
