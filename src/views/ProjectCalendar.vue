<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<div class="project-calendar">
		<div class="project-calendar__header">
			<h2>{{ t('planninq', 'Calendar') }}</h2>
		</div>

		<ProjectTabs :projectId="projectId" />

		<div v-if="loading" class="project-calendar__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<TaskCalendar v-else :tasks="tasks" :initialDate="initialDate" />
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcLoadingIcon } from '@nextcloud/vue'
import ProjectTabs from '../components/ProjectTabs.vue'
import TaskCalendar from '../components/TaskCalendar.vue'
import { useProjectsStore } from '../store/projects.js'

/**
 * ProjectCalendar: a project's tasks on their due dates, in a month, week or
 * list view (planning-calendar). `?date=YYYY-MM-DD` opens it on that day.
 *
 * @spec openspec/changes/planning-calendar/tasks.md#task-1.2
 */
export default {
	name: 'ProjectCalendar',

	components: {
		NcLoadingIcon,
		ProjectTabs,
		TaskCalendar,
	},

	data() {
		return {
			tasks: [],
			loading: true,
		}
	},

	computed: {
		/**
		 * @spec openspec/changes/planning-calendar/tasks.md#task-1.2
		 * @return {string} The project id from the route
		 */
		projectId() {
			return String(this.$route.params.id || '')
		},

		/**
		 * @spec openspec/changes/planning-calendar/tasks.md#task-1.2
		 * @return {string} The day to open on, from ?date=
		 */
		initialDate() {
			const value = String(this.$route.query.date || '')
			return /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : ''
		},
	},

	watch: {
		projectId: {
			immediate: true,
			/**
			 * @spec openspec/changes/planning-calendar/tasks.md#task-1.2
			 */
			handler() {
				this.load()
			},
		},
	},

	methods: {
		t,

		/**
		 * @spec openspec/changes/planning-calendar/tasks.md#task-1.2
		 */
		async load() {
			this.loading = true
			const tasks = await useProjectsStore().fetchTasks(this.projectId)
			this.tasks = Array.isArray(tasks) ? tasks : []
			this.loading = false
		},
	},
}
</script>

<style scoped>
.project-calendar {
	padding: calc(var(--default-grid-baseline) * 5);
}

.project-calendar__loading {
	display: flex;
	justify-content: center;
	padding: calc(var(--default-grid-baseline) * 10);
}
</style>
