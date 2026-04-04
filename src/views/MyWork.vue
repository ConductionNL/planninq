// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.
<template>
	<div class="my-work">
		<header class="my-work__header">
			<h2>{{ t('planix', 'My Work') }}</h2>
		</header>

		<div v-if="loading" class="my-work__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<NcEmptyContent
			v-else-if="!loading && allTasks.length === 0"
			:name="t('planix', 'No tasks assigned to you')"
			:description="t('planix', 'Tasks assigned to you will appear here.')">
			<template #icon>
				<CheckCircleOutline :size="20" />
			</template>
			<template #action>
				<NcButton type="primary" @click="$router.push({ name: 'Projects' })">
					{{ t('planix', 'Browse projects') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<template v-else>
			<section v-if="overdueTasks.length" class="my-work__group my-work__group--overdue">
				<h3 class="my-work__group-title">
					<AlertCircleOutline :size="18" />
					{{ t('planix', 'Overdue') }}
					<span class="my-work__group-count">{{ overdueTasks.length }}</span>
				</h3>
				<ul class="my-work__task-list">
					<li
						v-for="task in overdueTasks"
						:key="task.id"
						class="my-work__task-item">
						<span
							class="my-work__priority-dot"
							:class="'my-work__priority-dot--' + (task.priority || 'normal')"
							:title="task.priority || 'normal'" />
						<span
							class="my-work__task-title"
							@click="goToTask(task)">
							{{ task.title }}
						</span>
						<span v-if="projectTitle(task.project)" class="my-work__project-badge">
							{{ projectTitle(task.project) }}
						</span>
						<span class="my-work__due-date my-work__due-date--overdue">
							{{ formatDueDate(task.dueDate) }}
						</span>
					</li>
				</ul>
			</section>

			<section v-if="dueThisWeekTasks.length" class="my-work__group">
				<h3 class="my-work__group-title">
					<CalendarClock :size="18" />
					{{ t('planix', 'Due this week') }}
					<span class="my-work__group-count">{{ dueThisWeekTasks.length }}</span>
				</h3>
				<ul class="my-work__task-list">
					<li
						v-for="task in dueThisWeekTasks"
						:key="task.id"
						class="my-work__task-item">
						<span
							class="my-work__priority-dot"
							:class="'my-work__priority-dot--' + (task.priority || 'normal')"
							:title="task.priority || 'normal'" />
						<span
							class="my-work__task-title"
							@click="goToTask(task)">
							{{ task.title }}
						</span>
						<span v-if="projectTitle(task.project)" class="my-work__project-badge">
							{{ projectTitle(task.project) }}
						</span>
						<span class="my-work__due-date">
							{{ formatDueDate(task.dueDate) }}
						</span>
					</li>
				</ul>
			</section>

			<section v-if="everythingElseTasks.length" class="my-work__group">
				<h3 class="my-work__group-title">
					<FormatListBulleted :size="18" />
					{{ t('planix', 'Everything else') }}
					<span class="my-work__group-count">{{ everythingElseTasks.length }}</span>
				</h3>
				<ul class="my-work__task-list">
					<li
						v-for="task in everythingElseTasks"
						:key="task.id"
						class="my-work__task-item">
						<span
							class="my-work__priority-dot"
							:class="'my-work__priority-dot--' + (task.priority || 'normal')"
							:title="task.priority || 'normal'" />
						<span
							class="my-work__task-title"
							@click="goToTask(task)">
							{{ task.title }}
						</span>
						<span v-if="projectTitle(task.project)" class="my-work__project-badge">
							{{ projectTitle(task.project) }}
						</span>
						<span v-if="task.dueDate" class="my-work__due-date">
							{{ formatDueDate(task.dueDate) }}
						</span>
					</li>
				</ul>
			</section>
		</template>
	</div>
</template>

<script>
// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.
import { useObjectStore } from '@conduction/nextcloud-vue'
import { getCurrentUser } from '@nextcloud/auth'
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import CalendarClock from 'vue-material-design-icons/CalendarClock.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import FormatListBulleted from 'vue-material-design-icons/FormatListBulleted.vue'

const TASK_SCHEMA = 'task'
const PROJECT_SCHEMA = 'project'
const REGISTER = 'planix'
const PRIORITY_ORDER = ['urgent', 'high', 'normal', 'low']

export default {
	name: 'MyWork',
	components: {
		AlertCircleOutline,
		CalendarClock,
		CheckCircleOutline,
		FormatListBulleted,
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
	},
	data() {
		return {
			tasks: [],
			projects: [],
			loading: false,
		}
	},
	computed: {
		todayMidnight() {
			const d = new Date()
			d.setHours(0, 0, 0, 0)
			return d
		},
		allTasks() {
			return this.tasks.filter((task) => task.status !== 'done')
		},
		overdueTasks() {
			const today = this.todayMidnight
			return this.sortByPriority(
				this.allTasks.filter((task) => task.dueDate && new Date(task.dueDate) < today),
			)
		},
		dueThisWeekTasks() {
			const today = this.todayMidnight
			const weekEnd = new Date(today)
			weekEnd.setDate(weekEnd.getDate() + 7)
			const overdueIds = new Set(this.overdueTasks.map((t) => t.id))
			return this.sortByPriority(
				this.allTasks.filter((task) => {
					if (overdueIds.has(task.id)) return false
					if (!task.dueDate) return false
					const due = new Date(task.dueDate)
					return due >= today && due < weekEnd
				}),
			)
		},
		everythingElseTasks() {
			const overdueIds = new Set(this.overdueTasks.map((t) => t.id))
			const thisWeekIds = new Set(this.dueThisWeekTasks.map((t) => t.id))
			return this.sortByPriority(
				this.allTasks.filter((task) => !overdueIds.has(task.id) && !thisWeekIds.has(task.id)),
			)
		},
	},
	mounted() {
		this.fetchData()
	},
	methods: {
		async fetchData() {
			this.loading = true
			try {
				const objectStore = useObjectStore()
				const uid = getCurrentUser()?.uid || ''

				if (!objectStore.objectTypeRegistry?.[TASK_SCHEMA]) {
					objectStore.registerObjectType(TASK_SCHEMA, TASK_SCHEMA, REGISTER)
				}
				if (!objectStore.objectTypeRegistry?.[PROJECT_SCHEMA]) {
					objectStore.registerObjectType(PROJECT_SCHEMA, PROJECT_SCHEMA, REGISTER)
				}

				const [tasks, projects] = await Promise.all([
					objectStore.fetchCollection(TASK_SCHEMA, { assignedTo: uid }),
					objectStore.fetchCollection(PROJECT_SCHEMA, {}),
				])

				this.tasks = tasks || []
				this.projects = projects || []
			} finally {
				this.loading = false
			}
		},
		sortByPriority(tasks) {
			return [...tasks].sort((a, b) => {
				const ai = PRIORITY_ORDER.indexOf(a.priority || 'normal')
				const bi = PRIORITY_ORDER.indexOf(b.priority || 'normal')
				return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi)
			})
		},
		projectTitle(projectId) {
			const project = this.projects.find((p) => p.id === projectId)
			return project?.title || ''
		},
		formatDueDate(dueDate) {
			if (!dueDate) return ''
			const due = new Date(dueDate)
			due.setHours(0, 0, 0, 0)
			const today = this.todayMidnight
			const tomorrow = new Date(today)
			tomorrow.setDate(tomorrow.getDate() + 1)
			if (due.getTime() === today.getTime()) return t('planix', 'Today')
			if (due.getTime() === tomorrow.getTime()) return t('planix', 'Tomorrow')
			return due.toLocaleDateString()
		},
		goToTask(task) {
			if (task.project) {
				this.$router.push({ name: 'ProjectBoard', params: { id: task.project } })
			}
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
	justify-content: center;
	padding: 40px 0;
}

.my-work__group {
	margin-bottom: 24px;
}

.my-work__group-title {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 15px;
	font-weight: 600;
	margin: 0 0 8px;
	padding-bottom: 6px;
	border-bottom: 2px solid var(--color-border);
}

.my-work__group--overdue .my-work__group-title {
	color: var(--color-error);
	border-bottom-color: var(--color-error);
}

.my-work__group-count {
	margin-left: 4px;
	font-size: 12px;
	font-weight: 400;
	color: var(--color-text-maxcontrast);
	background: var(--color-background-dark);
	padding: 1px 6px;
	border-radius: 10px;
}

.my-work__task-list {
	margin: 0;
	padding: 0;
	list-style: none;
}

.my-work__task-item {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 10px 4px;
	border-bottom: 1px solid var(--color-border);
}

.my-work__task-item:last-child {
	border-bottom: none;
}

.my-work__priority-dot {
	width: 10px;
	height: 10px;
	border-radius: 50%;
	flex-shrink: 0;
}

.my-work__priority-dot--urgent {
	background-color: var(--color-error);
}

.my-work__priority-dot--high {
	background-color: var(--color-warning);
}

.my-work__priority-dot--normal {
	background-color: var(--color-primary);
}

.my-work__priority-dot--low {
	background-color: var(--color-text-maxcontrast);
}

.my-work__task-title {
	flex: 1;
	cursor: pointer;
}

.my-work__task-title:hover {
	text-decoration: underline;
}

.my-work__project-badge {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	background: var(--color-background-dark);
	padding: 2px 8px;
	border-radius: 10px;
	white-space: nowrap;
}

.my-work__due-date {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
}

.my-work__due-date--overdue {
	color: var(--color-error);
	font-weight: 600;
}
</style>
