<!-- SPDX-License-Identifier: EUPL-1.2 -->
<!-- Copyright (C) 2026 Conduction B.V. -->

<!--
 MyWorkView — grouped task list for the current user.

 Shows tasks in Overdue / Due This Week / Everything Else groups,
 sorted by priority. Supports filter URL params from dashboard KPI cards.

 @spec openspec/changes/dashboard-my-work/tasks.md#task-6
-->
<template>
	<div class="my-work">
		<header class="my-work__header">
			<h2>{{ t('planix', 'My Work') }}</h2>
		</header>

		<!-- Loading state -->
		<div v-if="loading" class="my-work__loading">
			<NcLoadingIcon :size="44" />
			<p>{{ t('planix', 'Loading your work…') }}</p>
		</div>

		<template v-else-if="hasAnyTasks">
			<!-- Completed Today group (ephemeral, from filter) -->
			<section
				v-if="showCompletedToday && completedTodayGroup.length > 0"
				ref="completedTodaySection"
				class="my-work__group">
				<div
					class="my-work__group-header my-work__group-header--success"
					:class="{ 'my-work__group-header--highlight': highlightGroup === 'completed_today' }">
					<button
						class="my-work__group-toggle"
						@click="toggleGroup('completedToday')">
						<span class="my-work__group-arrow" :class="{ 'my-work__group-arrow--collapsed': collapsedGroups.completedToday }">&#9660;</span>
						{{ t('planix', 'Completed Today') }}
						<span class="my-work__group-count">({{ completedTodayGroup.length }})</span>
					</button>
				</div>
				<div v-if="!collapsedGroups.completedToday">
					<MyWorkTaskRow
						v-for="task in completedTodayGroup"
						:key="task.id"
						:task="task"
						:read-only="true" />
				</div>
			</section>

			<!-- Overdue group -->
			<section
				v-if="overdueGroup.length > 0"
				ref="overdueSection"
				class="my-work__group">
				<div
					class="my-work__group-header my-work__group-header--error"
					:class="{ 'my-work__group-header--highlight': highlightGroup === 'overdue' }">
					<button
						class="my-work__group-toggle"
						@click="toggleGroup('overdue')">
						<span class="my-work__group-arrow" :class="{ 'my-work__group-arrow--collapsed': collapsedGroups.overdue }">&#9660;</span>
						{{ t('planix', 'Overdue') }}
						<span class="my-work__group-count">({{ overdueGroup.length }})</span>
					</button>
				</div>
				<div v-if="!collapsedGroups.overdue">
					<MyWorkTaskRow
						v-for="task in overdueGroup"
						:key="task.id"
						:task="task" />
				</div>
			</section>

			<!-- Due This Week group -->
			<section
				v-if="dueThisWeekGroup.length > 0"
				ref="dueThisWeekSection"
				class="my-work__group">
				<div
					class="my-work__group-header my-work__group-header--warning"
					:class="{ 'my-work__group-header--highlight': highlightGroup === 'dueThisWeek' }">
					<button
						class="my-work__group-toggle"
						@click="toggleGroup('dueThisWeek')">
						<span class="my-work__group-arrow" :class="{ 'my-work__group-arrow--collapsed': collapsedGroups.dueThisWeek }">&#9660;</span>
						{{ t('planix', 'Due This Week') }}
						<span class="my-work__group-count">({{ dueThisWeekGroup.length }})</span>
					</button>
				</div>
				<div v-if="!collapsedGroups.dueThisWeek">
					<MyWorkTaskRow
						v-for="task in dueThisWeekGroup"
						:key="task.id"
						:task="task" />
				</div>
			</section>

			<!-- Everything Else group -->
			<section
				v-if="everythingElseGroup.length > 0"
				ref="everythingElseSection"
				class="my-work__group">
				<div
					class="my-work__group-header"
					:class="{ 'my-work__group-header--highlight': highlightGroup === 'everythingElse' }">
					<button
						class="my-work__group-toggle"
						@click="toggleGroup('everythingElse')">
						<span class="my-work__group-arrow" :class="{ 'my-work__group-arrow--collapsed': collapsedGroups.everythingElse }">&#9660;</span>
						{{ t('planix', 'Everything Else') }}
						<span class="my-work__group-count">({{ everythingElseGroup.length }})</span>
					</button>
				</div>
				<div v-if="!collapsedGroups.everythingElse">
					<MyWorkTaskRow
						v-for="task in everythingElseGroup"
						:key="task.id"
						:task="task"
						:highlighted="isHighlightedTask(task)" />
				</div>
			</section>
		</template>

		<!-- Empty state -->
		<NcEmptyContent
			v-else
			:name="t('planix', 'No tasks assigned to you')"
			:description="t('planix', 'Tasks assigned to you will appear here')">
			<template #icon>
				<AccountClockOutline :size="64" aria-hidden="true" />
			</template>
			<template #action>
				<NcButton type="primary" @click="$router.push('/projects')">
					{{ t('planix', 'Browse projects') }}
				</NcButton>
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import { useTasksStore } from '../store/tasks.js'
import { useProjectsStore } from '../store/projects.js'
import MyWorkTaskRow from '../components/MyWorkTaskRow.vue'
import AccountClockOutline from 'vue-material-design-icons/AccountClockOutline.vue'

const PRIORITY_ORDER = { urgent: 0, high: 1, normal: 2, low: 3 }

function sortByPriority(tasks) {
	return [...tasks].sort(
		(a, b) => (PRIORITY_ORDER[a.priority] ?? 2) - (PRIORITY_ORDER[b.priority] ?? 2),
	)
}

/**
 * @spec openspec/changes/dashboard-my-work/tasks.md#task-6
 */
export default {
	name: 'MyWorkView',
	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		MyWorkTaskRow,
		AccountClockOutline,
	},
	data() {
		return {
			loading: true,
			highlightGroup: null,
			highlightFilter: null,
			collapsedGroups: {
				completedToday: false,
				overdue: false,
				dueThisWeek: false,
				everythingElse: false,
			},
		}
	},
	computed: {
		tasksStore() {
			return useTasksStore()
		},
		allTasks() {
			return this.tasksStore.tasks || []
		},
		activeTasks() {
			return this.allTasks.filter(
				(t) => t.status !== 'done' && t.status !== 'cancelled',
			)
		},
		showCompletedToday() {
			return this.$route.query.filter === 'completed_today'
		},
		/** @spec openspec/changes/dashboard-my-work/tasks.md#task-9 */
		completedTodayGroup() {
			const now = new Date()
			return sortByPriority(
				this.allTasks.filter((task) => {
					if (task.status !== 'done' || !task.completedAt) return false
					const completed = new Date(task.completedAt)
					return completed.getFullYear() === now.getFullYear()
						&& completed.getMonth() === now.getMonth()
						&& completed.getDate() === now.getDate()
				}),
			)
		},
		overdueGroup() {
			const today = new Date()
			today.setHours(0, 0, 0, 0)
			return sortByPriority(
				this.activeTasks.filter(
					(t) => t.dueDate && new Date(t.dueDate) < today,
				),
			)
		},
		dueThisWeekGroup() {
			const today = new Date()
			today.setHours(0, 0, 0, 0)
			const weekFromNow = new Date(today)
			weekFromNow.setDate(weekFromNow.getDate() + 7)
			return sortByPriority(
				this.activeTasks.filter((t) => {
					if (!t.dueDate) return false
					const due = new Date(t.dueDate)
					return due >= today && due <= weekFromNow
				}),
			)
		},
		everythingElseGroup() {
			const today = new Date()
			today.setHours(0, 0, 0, 0)
			const weekFromNow = new Date(today)
			weekFromNow.setDate(weekFromNow.getDate() + 7)
			return sortByPriority(
				this.activeTasks.filter((t) => {
					if (!t.dueDate) return true
					const due = new Date(t.dueDate)
					return due > weekFromNow
				}),
			)
		},
		hasAnyTasks() {
			return this.overdueGroup.length > 0
				|| this.dueThisWeekGroup.length > 0
				|| this.everythingElseGroup.length > 0
				|| (this.showCompletedToday && this.completedTodayGroup.length > 0)
		},
	},
	watch: {
		/** @spec openspec/changes/dashboard-my-work/tasks.md#task-9 */
		'$route.query.filter': function handler() {
			this.applyFilter()
		},
	},
	async mounted() {
		await this.loadData()
		this.applyFilter()
	},
	methods: {
		t,
		async loadData() {
			this.loading = true
			const uid = getCurrentUser()?.uid || ''
			const tasksStore = useTasksStore()
			const projectsStore = useProjectsStore()

			await Promise.allSettled([
				tasksStore.fetchTasks({ assignedTo: uid }),
				projectsStore.fetchProjects(),
			])

			this.loading = false
		},
		toggleGroup(group) {
			this.collapsedGroups[group] = !this.collapsedGroups[group]
		},
		isHighlightedTask(task) {
			return this.highlightFilter === 'in_progress' && task.status === 'in_progress'
		},
		/** @spec openspec/changes/dashboard-my-work/tasks.md#task-9 */
		applyFilter() {
			const filter = this.$route.query.filter
			if (!filter) {
				this.highlightGroup = null
				this.highlightFilter = null
				return
			}

			this.$nextTick(() => {
				let targetRef = null

				switch (filter) {
				case 'overdue':
					targetRef = this.$refs.overdueSection
					this.highlightGroup = 'overdue'
					break
				case 'in_progress':
					targetRef = this.$refs.everythingElseSection
					this.highlightGroup = 'everythingElse'
					this.highlightFilter = 'in_progress'
					break
				case 'completed_today':
					targetRef = this.$refs.completedTodaySection
					this.highlightGroup = 'completed_today'
					break
				case 'open':
				default:
					this.highlightGroup = null
					break
				}

				if (targetRef) {
					targetRef.scrollIntoView({ behavior: 'smooth' })
				}

				// Remove highlight after 2 seconds.
				if (this.highlightGroup) {
					setTimeout(() => {
						this.highlightGroup = null
						this.highlightFilter = null
					}, 2000)
				}
			})
		},
	},
}
</script>

<style scoped>
.my-work {
	padding: 8px 4px 24px;
	max-width: 900px;
}

.my-work__header {
	margin-bottom: 20px;
}

.my-work__header h2 {
	margin: 0;
	font-size: 22px;
	font-weight: 600;
}

.my-work__loading {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 12px;
	padding: 60px 0;
	color: var(--color-text-maxcontrast);
}

.my-work__group {
	margin-bottom: 16px;
}

.my-work__group-header {
	padding: 8px 12px;
	border-radius: var(--border-radius, 4px);
	margin-bottom: 4px;
	transition: background-color 0.3s ease;
}

.my-work__group-header--error {
	border-left: 3px solid var(--color-error);
}

.my-work__group-header--warning {
	border-left: 3px solid var(--color-warning);
}

.my-work__group-header--success {
	border-left: 3px solid var(--color-success);
}

.my-work__group-header--highlight {
	animation: highlight-pulse 2s ease-out;
}

@keyframes highlight-pulse {
	0% { background-color: var(--color-primary-element-light, rgba(0, 130, 201, 0.15)); }
	100% { background-color: transparent; }
}

.my-work__group-toggle {
	display: flex;
	align-items: center;
	gap: 8px;
	background: none;
	border: none;
	cursor: pointer;
	font-size: 14px;
	font-weight: 600;
	color: var(--color-main-text);
	padding: 0;
	width: 100%;
	text-align: left;
}

.my-work__group-arrow {
	font-size: 10px;
	transition: transform 0.2s ease;
}

.my-work__group-arrow--collapsed {
	transform: rotate(-90deg);
}

.my-work__group-count {
	font-weight: 400;
	color: var(--color-text-maxcontrast);
}
</style>
