<template>
	<div class="timetable-scenario" data-testid="scenario-sections">
		<section class="timetable-scenario__run">
			<h3>{{ t('planninq', 'Generator run') }}</h3>
			<p v-if="scenario === null && !loadError">
				<NcLoadingIcon :size="20" />
			</p>
			<p v-else-if="loadError" class="timetable-scenario__error" role="alert">
				{{ loadError }}
			</p>
			<template v-else>
				<p data-testid="scenario-status">
					{{ statusText }}
				</p>
				<NcProgressBar v-if="running"
					:value="percent"
					size="medium"
					:aria-label="t('planninq', 'Generator progress')" />
				<p v-if="scenario.status === 'failed' && scenario.reason" class="timetable-scenario__error" role="alert">
					{{ t('planninq', 'The run failed: {reason}', { reason: scenario.reason }) }}
				</p>
				<p v-if="scenario.metrics && scenario.metrics.lessons !== undefined" data-testid="scenario-placed">
					{{ t('planninq', '{placed} of {lessons} lessons placed', { placed: scenario.metrics.placed, lessons: scenario.metrics.lessons }) }}
				</p>
				<NcButton
					v-if="canGenerate"
					variant="primary"
					:disabled="starting"
					data-testid="scenario-generate"
					@click="generate">
					{{ scenario.status === 'done' || scenario.status === 'failed' ? t('planninq', 'Generate again') : t('planninq', 'Generate') }}
				</NcButton>
				<NcButton
					v-if="canImport"
					variant="primary"
					:disabled="starting"
					data-testid="scenario-import"
					@click="takeCurrent">
					{{ t('planninq', 'Take the current timetable') }}
				</NcButton>
				<p v-if="startError" class="timetable-scenario__error" role="alert">
					{{ startError }}
				</p>
			</template>
		</section>

		<section v-if="scenario" class="timetable-scenario__list">
			<h3>{{ t('planninq', 'Unplaced lessons') }}</h3>
			<p v-if="unplaced.length === 0">
				{{ t('planninq', 'Every lesson has a place.') }}
			</p>
			<table v-else data-testid="scenario-unplaced">
				<thead>
					<tr>
						<th scope="col">
							{{ t('planninq', 'Lesson') }}
						</th>
						<th scope="col">
							{{ t('planninq', 'Blocked by') }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in unplaced" :key="row.lesson">
						<td>{{ row.lesson }}</td>
						<td>{{ blockedBy(row) }}</td>
					</tr>
				</tbody>
			</table>
		</section>

		<section v-if="brokenHard.length > 0" class="timetable-scenario__list">
			<h3>{{ t('planninq', 'Broken hard wishes') }}</h3>
			<table data-testid="scenario-broken-hard">
				<thead>
					<tr>
						<th scope="col">
							{{ t('planninq', 'Wish') }}
						</th>
						<th scope="col">
							{{ t('planninq', 'Lessons') }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(row, index) in brokenHard" :key="index">
						<td>{{ describe(row.wish) }}</td>
						<td>{{ row.lessons.join(', ') }}</td>
					</tr>
				</tbody>
			</table>
		</section>

		<section v-if="scenario" class="timetable-scenario__list">
			<h3>{{ t('planninq', 'Broken soft wishes') }}</h3>
			<p v-if="broken.length === 0">
				{{ t('planninq', 'Every soft wish is kept.') }}
			</p>
			<table v-else data-testid="scenario-broken">
				<thead>
					<tr>
						<th scope="col">
							{{ t('planninq', 'Wish') }}
						</th>
						<th scope="col">
							{{ t('planninq', 'Weight') }}
						</th>
						<th scope="col">
							{{ t('planninq', 'Lessons') }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(row, index) in broken" :key="index">
						<td>{{ describe(row.wish) }}</td>
						<td>{{ row.weight }}</td>
						<td>{{ row.lessons.join(', ') }}</td>
					</tr>
				</tbody>
			</table>
		</section>
	</div>
</template>

<script>
/**
 * TimetableScenarioSections.
 *
 * The body of a timetable scenario's detail page (mounted in CnDetailPage's
 * sections slot): the run's status and progress, the Generate button for
 * admins, the lessons without a place with the hard wish that blocked them,
 * and the soft wishes the run broke. While a run is queued or running the
 * page reads the scenario again every five seconds. On an imported scenario
 * an admin takes the current timetable instead, and its broken hard wishes
 * are listed as well.
 *
 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.3
 * @spec openspec/changes/timetabling-generator/tasks.md#task-6.1
 */
import { buildHeaders } from '@conduction/nextcloud-vue'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import { NcButton, NcLoadingIcon, NcProgressBar } from '@nextcloud/vue'
import { brokenHardRows, brokenSoftRows, canTakeCurrentTimetable, isRunning, progressPercent, unplacedRows } from '../utils/timetableScenarios.js'

const POLL_MS = 5000

export default {
	name: 'TimetableScenarioSections',

	components: {
		NcButton,
		NcLoadingIcon,
		NcProgressBar,
	},

	data() {
		return {
			scenario: null,
			loadError: '',
			starting: false,
			startError: '',
			timer: null,
		}
	},

	computed: {
		/**
		 * The scenario id from the route.
		 *
		 * @return {string}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.3
		 */
		id() {
			return String(this.$route?.params?.id ?? '')
		},

		/**
		 * Whether a run is going.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.3
		 */
		running() {
			return isRunning(this.scenario)
		},

		/**
		 * The share of the time budget used.
		 *
		 * @return {number}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.3
		 */
		percent() {
			return progressPercent(this.scenario)
		},

		/**
		 * The status in words.
		 *
		 * @return {string}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.3
		 */
		statusText() {
			const status = this.scenario?.status
			if (status === 'running') {
				return this.t('planninq', 'Generating: {percent}% of the time budget used', { percent: this.percent })
			}
			return {
				queued: this.t('planninq', 'Waiting to start'),
				done: this.t('planninq', 'Done'),
				failed: this.t('planninq', 'Failed'),
				published: this.t('planninq', 'Published as draft lessons'),
			}[status] ?? ''
		},

		/**
		 * Whether this user may start a run: an admin, on a generated scenario that is not running or published.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.3
		 */
		canGenerate() {
			return getCurrentUser()?.isAdmin === true
				&& this.scenario?.source === 'generated'
				&& !this.running
				&& this.scenario?.status !== 'published'
		},

		/**
		 * Whether this user may take the current timetable into this scenario.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-6.1
		 */
		canImport() {
			return getCurrentUser()?.isAdmin === true && canTakeCurrentTimetable(this.scenario)
		},

		/**
		 * The broken hard wishes (an imported timetable only).
		 *
		 * @return {Array}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-6.1
		 */
		brokenHard() {
			return brokenHardRows(this.scenario)
		},

		/**
		 * The unplaced lessons.
		 *
		 * @return {Array}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.3
		 */
		unplaced() {
			return unplacedRows(this.scenario)
		},

		/**
		 * The broken soft wishes.
		 *
		 * @return {Array}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.3
		 */
		broken() {
			return brokenSoftRows(this.scenario)
		},
	},

	/**
	 * Read the scenario.
	 *
	 * @return {void}
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.3
	 */
	mounted() {
		this.load()
	},

	/**
	 * Stop reading.
	 *
	 * @return {void}
	 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.3
	 */
	beforeUnmount() {
		clearTimeout(this.timer)
	},

	methods: {
		/**
		 * Read the scenario, and again in five seconds while a run is going.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.3
		 */
		async load() {
			clearTimeout(this.timer)
			const response = await fetch(generateUrl(`/apps/openregister/api/objects/planninq/timetableScenario/${this.id}`), { headers: buildHeaders() }).catch(() => null)
			if (!response?.ok) {
				this.loadError = this.t('planninq', 'Could not read the scenario.')
				return
			}
			this.loadError = ''
			this.scenario = await response.json()
			if (this.running) {
				this.timer = setTimeout(() => this.load(), POLL_MS)
			}
		},

		/**
		 * Start a run.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.3
		 */
		async generate() {
			this.starting = true
			this.startError = ''
			const response = await fetch(generateUrl(`/apps/planninq/api/timetable/scenarios/${this.id}/generate`), { method: 'POST', headers: buildHeaders() }).catch(() => null)
			this.starting = false
			if (!response?.ok) {
				const body = await response?.json().catch(() => ({}))
				this.startError = body?.error || this.t('planninq', 'Could not start the run.')
				return
			}
			await this.load()
		},

		/**
		 * Take the scheduled lessons of the scenario's week into it.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-6.1
		 */
		async takeCurrent() {
			this.starting = true
			this.startError = ''
			const response = await fetch(generateUrl(`/apps/planninq/api/timetable/scenarios/${this.id}/import`), { method: 'POST', headers: buildHeaders() }).catch(() => null)
			this.starting = false
			if (!response?.ok) {
				const body = await response?.json().catch(() => ({}))
				this.startError = body?.error || this.t('planninq', 'Could not take the current timetable.')
				return
			}
			await this.load()
		},

		/**
		 * Why a lesson has no place: the hard wish, a time off the week grid, or no free period and room.
		 *
		 * @param {{wish: object|null, reason: string}} row The unplaced row.
		 * @return {string}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-6.1
		 */
		blockedBy(row) {
			if (row.wish) {
				return this.describe(row.wish)
			}
			return row.reason === 'offGrid' ? this.t('planninq', 'Not on a period of the week grid') : this.t('planninq', 'No free period and room')
		},

		/**
		 * A wish in words: what it applies to, who or what, and its kind.
		 *
		 * @param {object|null} wish The wish summary.
		 * @return {string}
		 * @spec openspec/changes/timetabling-generator/tasks.md#task-5.3
		 */
		describe(wish) {
			const kinds = {
				unavailable: this.t('planninq', 'Not on these periods'),
				avoid: this.t('planninq', 'Preferably not on these periods'),
				maxPerDay: this.t('planninq', 'At most a number of lessons a day'),
				noGaps: this.t('planninq', 'No free periods between lessons'),
				sameRoom: this.t('planninq', 'Always the same room'),
			}
			return [wish?.reference, kinds[wish?.kind], (wish?.periods ?? []).join(' ')].filter(Boolean).join(': ')
		},
	},
}
</script>

<style scoped>
.timetable-scenario {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.timetable-scenario table {
	border-collapse: collapse;
	width: 100%;
}

.timetable-scenario th,
.timetable-scenario td {
	border-bottom: 1px solid var(--color-border);
	padding: 4px 8px;
	text-align: start;
}

.timetable-scenario__error {
	color: var(--color-error-text, var(--color-error));
}
</style>
