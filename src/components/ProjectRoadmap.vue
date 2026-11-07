<template>
	<div class="project-roadmap" data-testid="project-roadmap">
		<div class="project-roadmap__actions">
			<NcButton variant="primary"
				data-testid="release-new"
				@click="editing = { release: null }">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
				{{ t('planninq', 'New release') }}
			</NcButton>
		</div>

		<div v-if="loading" class="project-roadmap__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<template v-else>
			<!-- Chart: the lists below carry every fact it shows, for keyboard and screen-reader use -->
			<div v-if="layout.bars.length || layout.markers.length"
				class="project-roadmap__scroll"
				role="img"
				:aria-label="t('planninq', 'Roadmap chart. The same releases and epics are listed below.')">
				<div class="project-roadmap__chart" :style="{ width: layout.chartWidth + 'px', height: chartHeight + 'px' }">
					<div v-for="tick in monthTicks"
						:key="tick.iso"
						class="project-roadmap__tick"
						:style="{ left: tick.x + 'px' }">
						<span class="project-roadmap__tick-label">{{ tick.label }}</span>
					</div>
					<div v-for="marker in layout.markers"
						:key="marker.id"
						class="project-roadmap__marker"
						:class="{ 'project-roadmap__marker--released': marker.status === 'released' }"
						:style="{ left: marker.left + 'px' }"
						data-testid="roadmap-release-marker"
						:data-date="marker.date">
						<span class="project-roadmap__marker-label">{{ marker.title }}</span>
					</div>
					<div v-for="(bar, index) in layout.bars"
						:key="bar.id"
						class="project-roadmap__bar"
						:style="{ left: bar.left + 'px', width: bar.width + 'px', top: barTop(index) + 'px' }"
						data-testid="roadmap-epic-bar"
						:data-start="bar.start"
						:data-end="bar.end">
						<span class="project-roadmap__bar-label">{{ bar.title || t('planninq', 'Untitled task') }}</span>
					</div>
				</div>
			</div>
			<NcEmptyContent v-else
				:name="t('planninq', 'Nothing on the roadmap yet')"
				:description="t('planninq', 'Releases with a target date and epics with dated tasks appear here.')">
				<template #icon>
					<MapMarkerPath :size="20" />
				</template>
			</NcEmptyContent>

			<!-- Releases -->
			<section class="project-roadmap__section" aria-labelledby="roadmap-releases-heading">
				<h3 id="roadmap-releases-heading" class="project-roadmap__section-title">
					{{ t('planninq', 'Releases') }}
				</h3>
				<p v-if="!sortedReleases.length" class="project-roadmap__empty">
					{{ t('planninq', 'No releases yet') }}
				</p>
				<ul v-else class="project-roadmap__list" data-testid="release-list">
					<li v-for="release in sortedReleases"
						:key="release.id"
						class="project-roadmap__row"
						:data-testid="'release-row-' + release.id">
						<span class="project-roadmap__row-name">{{ release.title }}</span>
						<span>{{ release.releaseDate ? t('planninq', 'Target date: {date}', { date: formatDate(release.releaseDate) }) : t('planninq', 'No target date') }}</span>
						<span>{{ statusLabel(release.status) }}</span>
						<span data-testid="release-progress">{{ progressText(release) }}</span>
						<span class="project-roadmap__row-actions">
							<NcButton variant="tertiary"
								:aria-label="t('planninq', 'Edit {name}', { name: release.title })"
								@click="editing = { release }">
								{{ t('planninq', 'Edit') }}
							</NcButton>
							<NcButton v-if="(release.status || 'planned') === 'planned'"
								variant="secondary"
								:aria-label="t('planninq', 'Mark {name} as released', { name: release.title })"
								data-testid="release-ship"
								@click="shipping = release">
								{{ t('planninq', 'Mark as released') }}
							</NcButton>
						</span>
					</li>
				</ul>
			</section>

			<!-- Epics -->
			<section class="project-roadmap__section" aria-labelledby="roadmap-epics-heading">
				<h3 id="roadmap-epics-heading" class="project-roadmap__section-title">
					{{ t('planninq', 'Epics') }}
				</h3>
				<p v-if="!epics.length" class="project-roadmap__empty">
					{{ t('planninq', 'No epics yet. Open a task to make it an epic.') }}
				</p>
				<ul v-else-if="layout.bars.length" class="project-roadmap__list" data-testid="epic-list">
					<li v-for="bar in layout.bars" :key="bar.id" class="project-roadmap__row">
						<RouterLink class="project-roadmap__row-name" :to="{ name: 'TaskDetail', params: { id: projectId, taskId: bar.id } }">
							{{ bar.title || t('planninq', 'Untitled task') }}
						</RouterLink>
						<span>{{ t('planninq', '{start} to {end}', { start: formatDate(bar.start), end: formatDate(bar.end) }) }}</span>
					</li>
				</ul>
				<template v-if="layout.unscheduled.length">
					<h4 class="project-roadmap__subtitle">
						{{ t('planninq', 'Not scheduled yet') }}
					</h4>
					<ul class="project-roadmap__list" data-testid="epic-unscheduled">
						<li v-for="epic in layout.unscheduled" :key="epic.id" class="project-roadmap__row">
							<RouterLink class="project-roadmap__row-name" :to="{ name: 'TaskDetail', params: { id: projectId, taskId: epic.id } }">
								{{ epic.title || t('planninq', 'Untitled task') }}
							</RouterLink>
						</li>
					</ul>
				</template>
			</section>
		</template>

		<ReleaseEditDialog v-if="editing"
			:projectId="projectId"
			:release="editing.release"
			@saved="onSaved"
			@close="editing = null" />
		<ReleaseShipDialog v-if="shipping"
			:release="shipping"
			:tasks="tasks"
			:releases="releases"
			@shipped="onSaved"
			@close="shipping = null" />
	</div>
</template>

<script>
/**
 * ProjectRoadmap.
 *
 * The project's releases as markers on their target date and its epics as
 * bars, on the Timeline tab's Roadmap view, with the release list (progress,
 * edit, mark as released) and the epic list under it. The lists are the
 * keyboard and screen-reader equivalent of the chart. Every read is an
 * OpenRegister object read scoped to the project; no controller.
 *
 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.2
 */
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import MapMarkerPath from 'vue-material-design-icons/MapMarkerPath.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ReleaseEditDialog from '../dialogs/ReleaseEditDialog.vue'
import ReleaseShipDialog from '../dialogs/ReleaseShipDialog.vue'
import { useProjectsStore } from '../store/projects.js'
import { dayIso, isEpic, releaseProgress, roadmapLayout, sortReleases } from '../utils/roadmapHelpers.js'
import { BAR_HEIGHT, ROW_GAP } from '../utils/timelineHelpers.js'

/** Height of the axis and marker label band above the bars (px). */
const LABEL_BAND = 44

export default {
	name: 'ProjectRoadmap',

	components: {
		MapMarkerPath,
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		PlusIcon,
		ReleaseEditDialog,
		ReleaseShipDialog,
	},

	props: {
		/** The project. */
		projectId: {
			type: String,
			required: true,
		},

		/** Pixels per day, from the timeline's zoom. */
		pxPerDay: {
			type: Number,
			default: 6,
		},
	},

	data() {
		return {
			loading: true,
			releases: [],
			tasks: [],
			editing: null,
			shipping: null,
		}
	},

	computed: {
		/**
		 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.2
		 */
		epics() {
			return this.tasks.filter(isEpic)
		},

		/**
		 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.2
		 */
		sortedReleases() {
			return sortReleases(this.releases)
		},

		/**
		 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.2
		 */
		layout() {
			return roadmapLayout(this.epics, this.tasks, this.releases, this.pxPerDay)
		},

		/**
		 * @spec exclude Presentational chart height.
		 */
		chartHeight() {
			return LABEL_BAND + Math.max(1, this.layout.bars.length) * (BAR_HEIGHT + ROW_GAP) + ROW_GAP
		},

		/**
		 * @spec exclude Presentational axis: a tick on the first of each month.
		 */
		monthTicks() {
			const ticks = []
			for (let d = 0; d < this.layout.dayCount; d++) {
				const iso = dayIso(this.layout.minDay + d)
				if (d === 0 || iso.endsWith('-01')) {
					ticks.push({ iso, x: d * this.pxPerDay, label: this.formatMonth(iso) })
				}
			}
			return ticks
		},
	},

	watch: {
		/**
		 * @spec exclude Reload on project change.
		 */
		projectId() {
			this.load()
		},
	},

	/**
	 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.2
	 */
	mounted() {
		this.load()
	},

	methods: {
		/**
		 * Read the project's releases and tasks.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.2
		 */
		async load() {
			this.loading = true
			const store = useProjectsStore()
			const [releases, tasks] = await Promise.all([store.fetchReleases(this.projectId), store.fetchTasks(this.projectId)])
			this.releases = releases
			this.tasks = Array.isArray(tasks) ? tasks : []
			this.loading = false
		},

		/**
		 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.3
		 */
		onSaved() {
			this.editing = null
			this.shipping = null
			this.load()
		},

		/**
		 * @param {object} release The release.
		 * @return {string} "3 of 5 tasks done".
		 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.2
		 */
		progressText(release) {
			const { done, total } = releaseProgress(release, this.tasks)
			return this.t('planninq', '{done} of {total} tasks done', { done, total })
		},

		/**
		 * @param {string} status The release status.
		 * @return {string}
		 * @spec exclude Display map of the release status.
		 */
		statusLabel(status) {
			const labels = { planned: this.t('planninq', 'Planned'), released: this.t('planninq', 'Released'), archived: this.t('planninq', 'Archived') }
			return labels[status || 'planned'] || status
		},

		/**
		 * @param {number} index The bar's row.
		 * @return {number}
		 * @spec exclude Presentational bar position.
		 */
		barTop(index) {
			return LABEL_BAND + ROW_GAP + index * (BAR_HEIGHT + ROW_GAP)
		},

		/**
		 * @param {string} iso An ISO date.
		 * @return {string}
		 * @spec exclude Presentational date format.
		 */
		formatDate(iso) {
			return new Date(`${String(iso).slice(0, 10)}T00:00:00Z`).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' })
		},

		/**
		 * @param {string} iso An ISO date.
		 * @return {string}
		 * @spec exclude Presentational month label.
		 */
		formatMonth(iso) {
			return new Date(`${iso}T00:00:00Z`).toLocaleDateString(undefined, { month: 'short', year: 'numeric', timeZone: 'UTC' })
		},
	},
}
</script>

<style scoped>
.project-roadmap__actions {
	display: flex;
	justify-content: flex-end;
	margin-bottom: 12px;
}

.project-roadmap__loading {
	display: flex;
	justify-content: center;
	padding: 48px 0;
}

.project-roadmap__scroll {
	overflow-x: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	background: var(--color-main-background);
}

.project-roadmap__chart {
	position: relative;
	min-width: 100%;
}

.project-roadmap__tick {
	position: absolute;
	top: 0;
	bottom: 0;
	border-inline-start: 1px solid var(--color-border);
	font-size: 11px;
	color: var(--color-text-maxcontrast);
}

.project-roadmap__tick-label {
	display: inline-block;
	padding: 4px 6px;
	white-space: nowrap;
}

.project-roadmap__marker {
	position: absolute;
	top: 20px;
	bottom: 0;
	border-inline-start: 2px dashed var(--color-primary-element);
	z-index: 2;
}

.project-roadmap__marker--released {
	border-inline-start-style: solid;
	border-inline-start-color: var(--color-success);
}

.project-roadmap__marker-label {
	display: inline-block;
	padding: 2px 6px;
	font-size: 12px;
	font-weight: 600;
	white-space: nowrap;
	background: var(--color-main-background);
}

.project-roadmap__bar {
	position: absolute;
	height: 28px;
	display: flex;
	align-items: center;
	padding: 0 8px;
	box-sizing: border-box;
	overflow: hidden;
	border-radius: 6px;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	z-index: 3;
}

.project-roadmap__bar-label {
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	font-size: 12px;
	font-weight: 500;
}

.project-roadmap__section {
	margin-top: 24px;
}

.project-roadmap__section-title {
	margin: 0 0 8px;
	font-size: 16px;
	font-weight: 600;
}

.project-roadmap__subtitle {
	margin: 12px 0 8px;
	font-size: 14px;
	font-weight: 600;
}

.project-roadmap__empty {
	color: var(--color-text-maxcontrast);
}

.project-roadmap__list {
	margin: 0;
	padding: 0;
	list-style: none;
}

.project-roadmap__row {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px 16px;
	padding: 8px 0;
	border-bottom: 1px solid var(--color-border);
}

.project-roadmap__row-name {
	flex: 1 1 200px;
	font-weight: 600;
	color: var(--color-main-text);
}

.project-roadmap__row-actions {
	display: flex;
	gap: 4px;
}
</style>
