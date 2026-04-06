<template>
	<div class="project-board">
		<!-- Access denied state (403 or non-member) -->
		<NcEmptyContent
			v-if="accessDenied"
			:name="t('planix', 'You do not have access to this project')"
			:description="t('planix', 'You are not a member of this project.')">
			<template #icon>
				<LockOutline :size="20" />
			</template>
			<template #action>
				<NcButton type="primary" @click="$router.push({ name: 'Projects' })">
					{{ t('planix', 'Back to projects') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<!-- Loading state -->
		<div v-else-if="loading" class="project-board__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<!-- Board content -->
		<template v-else-if="project">
			<!-- Page header -->
			<div class="project-board__header">
				<!-- Color accent bar -->
				<span
					v-if="project.color"
					class="project-board__color-accent"
					:style="{ backgroundColor: project.color }"
					aria-hidden="true" />

				<span class="project-board__icon" aria-hidden="true">
					{{ project.icon || '📁' }}
				</span>

				<h2 class="project-board__title">
					{{ project.title }}
				</h2>

				<div class="project-board__header-actions">
					<NcButton
						:aria-label="t('planix', 'View backlog')"
						type="tertiary"
						@click="$router.push({ name: 'ProjectBacklog', params: { id: project.id } })">
						{{ t('planix', 'Backlog') }}
					</NcButton>
					<NcButton
						:aria-label="t('planix', 'Project settings')"
						type="tertiary"
						@click="openSettings">
						<template #icon>
							<CogIcon :size="20" />
						</template>
					</NcButton>
				</div>
			</div>

			<!-- Board loading -->
			<div v-if="boardLoading" class="project-board__loading">
				<NcLoadingIcon :size="24" />
			</div>

			<!-- Kanban board -->
			<div
				v-else
				class="kanban-board"
				role="region"
				:aria-label="t('planix', 'Kanban board')">
				<div class="kanban-board__columns">
					<div
						v-for="column in columns"
						:key="column.id"
						class="kanban-column"
						role="list"
						:aria-label="column.title">
						<!-- Column header -->
						<div class="kanban-column__header">
							<h3 class="kanban-column__title">
								{{ column.title }}
							</h3>
							<span class="kanban-column__count">
								{{ tasksForColumn(column.id).length }}
							</span>
						</div>

						<!-- Task cards -->
						<div class="kanban-column__cards">
							<div
								v-for="task in tasksForColumn(column.id)"
								:key="task.id"
								class="kanban-card"
								role="listitem"
								tabindex="0">
								<div class="kanban-card__header">
									<span
										class="kanban-card__priority"
										:class="'kanban-card__priority--' + (task.priority || 'normal')"
										:title="task.priority || 'normal'" />
									<span class="kanban-card__title">{{ task.title }}</span>
								</div>
								<div class="kanban-card__meta">
									<span
										v-if="task.assignedTo"
										class="kanban-card__assignee"
										:title="task.assignedTo">
										<NcAvatar :user="task.assignedTo" :size="20" />
									</span>
									<span
										v-if="task.dueDate"
										class="kanban-card__due"
										:class="{ 'kanban-card__due--overdue': isOverdue(task.dueDate) }">
										{{ formatDate(task.dueDate) }}
									</span>
								</div>
							</div>

							<!-- Empty column placeholder -->
							<div
								v-if="tasksForColumn(column.id).length === 0"
								class="kanban-column__empty">
								{{ t('planix', 'No tasks') }}
							</div>
						</div>
					</div>

					<!-- Add column button -->
					<div class="kanban-board__add-column">
						<NcButton
							v-if="!showAddColumn"
							type="tertiary"
							@click="showAddColumn = true">
							<template #icon>
								<PlusIcon :size="20" />
							</template>
							{{ t('planix', 'Add column') }}
						</NcButton>
						<div v-else class="kanban-board__add-column-form">
							<NcTextField
								:label="t('planix', 'Column title')"
								:value.sync="newColumnTitle"
								@keyup.enter="addColumn" />
							<div class="kanban-board__add-column-actions">
								<NcButton type="primary" :disabled="!newColumnTitle.trim()" @click="addColumn">
									{{ t('planix', 'Add') }}
								</NcButton>
								<NcButton type="tertiary" @click="cancelAddColumn">
									{{ t('planix', 'Cancel') }}
								</NcButton>
							</div>
						</div>
					</div>
				</div>
			</div>
		</template>

		<!-- Settings sidebar (rendered via App.vue outlet, passed via provide) -->
	</div>
</template>

<script>
import { NcAvatar, NcButton, NcEmptyContent, NcLoadingIcon, NcTextField } from '@nextcloud/vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import LockOutline from 'vue-material-design-icons/LockOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import { getCurrentUser } from '@nextcloud/auth'
import { useProjectsStore } from '../store/projects.js'
import ProjectSettingsSidebar from '../components/ProjectSettingsSidebar.vue'

export default {
	name: 'ProjectBoard',

	components: {
		NcAvatar,
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcTextField,
		CogIcon,
		LockOutline,
		PlusIcon,
	},

	inject: {
		setSidebar: { default: null },
		closeSidebar: { default: null },
	},

	data() {
		return {
			showAddColumn: false,
			newColumnTitle: '',
		}
	},

	computed: {
		projectsStore() {
			return useProjectsStore()
		},
		project() {
			return this.projectsStore.activeProject
		},
		loading() {
			return this.projectsStore.loading
		},
		boardLoading() {
			return this.projectsStore.boardLoading
		},
		columns() {
			return this.projectsStore.columns
		},
		tasks() {
			return this.projectsStore.tasks
		},
		accessDenied() {
			const store = this.projectsStore
			if (store.error === 'forbidden') return true
			if (!store.loading && store.activeProject) {
				const uid = getCurrentUser()?.uid
				return !!uid && !store.activeProject.members?.includes(uid)
			}
			return false
		},
	},

	async mounted() {
		const id = this.$route.params.id
		await this.projectsStore.fetchProject(id)
		if (this.project) {
			await this.projectsStore.fetchBoard(this.project.id)
		}
	},

	beforeDestroy() {
		this.closeSidebar?.()
	},

	methods: {
		openSettings() {
			if (!this.setSidebar) return
			this.setSidebar({
				...ProjectSettingsSidebar,
				propsData: { project: this.project },
				on: {
					close: () => this.closeSidebar?.(),
					archived: () => this.$router.push({ name: 'Projects' }),
					deleted: () => this.$router.push({ name: 'Projects' }),
				},
			})
		},

		tasksForColumn(columnId) {
			return this.tasks
				.filter((task) => task.column === columnId)
				.sort((a, b) => (a.columnOrder ?? 0) - (b.columnOrder ?? 0))
		},

		isOverdue(dateStr) {
			if (!dateStr) return false
			const due = new Date(dateStr)
			const now = new Date()
			now.setHours(0, 0, 0, 0)
			return due < now
		},

		formatDate(dateStr) {
			if (!dateStr) return ''
			const date = new Date(dateStr)
			return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
		},

		async addColumn() {
			const title = this.newColumnTitle.trim()
			if (!title) return

			const nextOrder = this.columns.length > 0
				? Math.max(...this.columns.map((c) => c.order ?? 0)) + 1
				: 0

			await this.projectsStore.createNewColumn({
				title,
				project: this.project.id,
				order: nextOrder,
				type: 'active',
			})

			this.newColumnTitle = ''
			this.showAddColumn = false
		},

		cancelAddColumn() {
			this.newColumnTitle = ''
			this.showAddColumn = false
		},
	},
}
</script>

<style scoped>
.project-board {
	padding: 8px 4px 24px;
}

.project-board__header {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-bottom: 24px;
	padding-bottom: 16px;
	border-bottom: 1px solid var(--color-border);
}

.project-board__color-accent {
	flex-shrink: 0;
	width: 6px;
	height: 32px;
	border-radius: 3px;
}

.project-board__icon {
	font-size: 24px;
	line-height: 1;
}

.project-board__title {
	flex: 1;
	margin: 0;
	font-size: 20px;
	font-weight: 600;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.project-board__header-actions {
	display: flex;
	gap: 4px;
}

.project-board__loading {
	display: flex;
	justify-content: center;
	padding: 60px;
}

/* Kanban board layout */
.kanban-board {
	overflow-x: auto;
	padding-bottom: 16px;
}

.kanban-board__columns {
	display: flex;
	gap: 16px;
	align-items: flex-start;
	min-height: 200px;
}

/* Column */
.kanban-column {
	flex: 0 0 280px;
	min-width: 280px;
	background: var(--color-background-dark);
	border-radius: 8px;
	padding: 12px;
}

.kanban-column__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 12px;
	padding-bottom: 8px;
	border-bottom: 2px solid var(--color-border);
}

.kanban-column__title {
	margin: 0;
	font-size: 14px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: var(--color-text-maxcontrast);
}

.kanban-column__count {
	font-size: 12px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	background: var(--color-background-darker, var(--color-border));
	border-radius: 10px;
	padding: 2px 8px;
	min-width: 20px;
	text-align: center;
}

.kanban-column__cards {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.kanban-column__empty {
	padding: 24px 12px;
	text-align: center;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	font-style: italic;
}

/* Card */
.kanban-card {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 6px;
	padding: 10px 12px;
	cursor: default;
	transition: box-shadow 0.15s ease;
}

.kanban-card:hover {
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}

.kanban-card:focus {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -1px;
}

.kanban-card__header {
	display: flex;
	align-items: flex-start;
	gap: 8px;
	margin-bottom: 6px;
}

.kanban-card__priority {
	flex-shrink: 0;
	width: 8px;
	height: 8px;
	border-radius: 50%;
	margin-top: 5px;
}

.kanban-card__priority--low {
	background: #95a5a6;
}

.kanban-card__priority--normal {
	background: #3498db;
}

.kanban-card__priority--high {
	background: #f39c12;
}

.kanban-card__priority--urgent {
	background: #e74c3c;
}

.kanban-card__title {
	font-size: 13px;
	font-weight: 500;
	line-height: 1.4;
	word-break: break-word;
}

.kanban-card__meta {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
}

.kanban-card__assignee {
	display: flex;
	align-items: center;
}

.kanban-card__due {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.kanban-card__due--overdue {
	color: #e74c3c;
	font-weight: 600;
}

/* Add column */
.kanban-board__add-column {
	flex: 0 0 280px;
	min-width: 280px;
}

.kanban-board__add-column-form {
	background: var(--color-background-dark);
	border-radius: 8px;
	padding: 12px;
}

.kanban-board__add-column-actions {
	display: flex;
	gap: 4px;
	margin-top: 8px;
}
</style>
