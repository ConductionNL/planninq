<template>
	<div class="project-phases">
		<div class="project-phases__main">
			<div class="project-phases__header">
				<h2>{{ t('planninq', 'Phases') }}</h2>
				<NcButton variant="primary" data-testid="phase-add" @click="openEdit(null)">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ t('planninq', 'Add phase') }}
				</NcButton>
			</div>

			<ProjectTabs :projectId="projectId" />

			<div v-if="loading" class="project-phases__loading">
				<NcLoadingIcon :size="32" />
			</div>

			<NcEmptyContent
				v-else-if="!sorted.length"
				:name="t('planninq', 'No phases yet')"
				:description="t('planninq', 'Break the project into phases, such as initiation, definition and delivery. A phase closes once its concluding document is uploaded.')" />

			<ol v-else class="project-phases__list" data-testid="phase-list">
				<li
					v-for="(phase, index) in sorted"
					:key="phase.id"
					class="project-phases__item"
					:class="{ 'project-phases__item--selected': selectedId === phase.id }"
					data-testid="phase-row"
					:data-phase="phase.id">
					<button
						type="button"
						class="project-phases__name"
						:aria-pressed="String(selectedId === phase.id)"
						data-testid="phase-open"
						@click="select(phase)">
						{{ phase.title }}
					</button>
					<span class="project-phases__meta">
						<span data-testid="phase-status">{{ statusLabel(phase.status) }}</span>
						<span v-if="phase.startDate || phase.endDate">{{ phase.startDate || '' }} – {{ phase.endDate || '' }}</span>
						<span v-if="phase.budgetHours">{{ t('planninq', '{hours} hours', { hours: phase.budgetHours }) }}</span>
						<span
							v-if="flagged[phase.id]"
							class="project-phases__flag"
							data-testid="phase-document-missing">
							{{ t('planninq', 'Concluding document missing') }}
						</span>
					</span>
					<span class="project-phases__actions">
						<NcButton
							variant="tertiary"
							:disabled="index === 0 || moving"
							:aria-label="t('planninq', 'Move {title} up', { title: phase.title })"
							data-testid="phase-move-up"
							@click="move(phase, -1)">
							<template #icon>
								<ArrowUp :size="20" />
							</template>
						</NcButton>
						<NcButton
							variant="tertiary"
							:disabled="index === sorted.length - 1 || moving"
							:aria-label="t('planninq', 'Move {title} down', { title: phase.title })"
							data-testid="phase-move-down"
							@click="move(phase, 1)">
							<template #icon>
								<ArrowDown :size="20" />
							</template>
						</NcButton>
						<NcButton
							variant="tertiary"
							:aria-label="t('planninq', 'Edit {title}', { title: phase.title })"
							data-testid="phase-edit"
							@click="openEdit(phase)">
							<template #icon>
								<Pencil :size="20" />
							</template>
						</NcButton>
						<NcButton
							v-if="phase.status !== 'completed' && phase.status !== 'cancelled'"
							data-testid="phase-close"
							@click="closing = phase">
							{{ t('planninq', 'Close phase') }}
						</NcButton>
					</span>
				</li>
			</ol>
		</div>

		<CnObjectSidebar
			v-if="selected"
			:open="true"
			v-bind="sidebarConfig"
			:title="selected.title"
			:subtitle="t('planninq', 'Phase')"
			:filesLabel="t('planninq', 'Files')"
			:notesLabel="t('planninq', 'Notes')"
			:auditTrailLabel="t('planninq', 'Audit trail')"
			@update:open="onSidebarToggle" />

		<PhaseEditDialog
			v-if="editing"
			:projectId="projectId"
			:phase="editing.phase"
			:phases="phases"
			@close="editing = null"
			@saved="onSaved" />

		<PhaseCloseDialog
			v-if="closing"
			:phase="closing"
			@close="closing = null"
			@closed="onClosed" />
	</div>
</template>

<script>
/**
 * ProjectPhases.
 *
 * The phases of a project in order, with status, dates, budget hours and a
 * flag for a completed phase whose concluding document is gone. Members add,
 * edit and reorder phases (Move up and Move down, by keyboard as well), open
 * a phase's files, notes and audit trail in the sidebar, and close a phase
 * through the close dialog, which OpenRegister's lifecycle guard backs.
 *
 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.2
 */
import { CnObjectSidebar } from '@conduction/nextcloud-vue'
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import ArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import ArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ProjectTabs from '../components/ProjectTabs.vue'
import PhaseCloseDialog from '../dialogs/PhaseCloseDialog.vue'
import PhaseEditDialog from '../dialogs/PhaseEditDialog.vue'
import { listPhaseFiles } from '../api/phaseFiles.js'
import { useProjectsStore } from '../store/projects.js'
import { missingConcludingDocument, sortPhases } from '../utils/phaseHelpers.js'
import { PLANNINQ_REGISTER, TASK_SIDEBAR_HIDDEN_TABS } from '../utils/taskHelpers.js'

export default {
	name: 'ProjectPhases',

	components: {
		ArrowDown,
		ArrowUp,
		CnObjectSidebar,
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		PhaseCloseDialog,
		PhaseEditDialog,
		Pencil,
		PlusIcon,
		ProjectTabs,
	},

	data() {
		return {
			phases: [],
			flagged: {},
			loading: true,
			moving: false,
			selectedId: '',
			editing: null,
			closing: null,
		}
	},

	computed: {
		/**
		 * @return {string}
		 *
		 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.2
		 */
		projectId() {
			return String(this.$route.params.id || '')
		},

		/**
		 * @return {Array<object>}
		 *
		 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.2
		 */
		sorted() {
			return sortPhases(this.phases)
		},

		/**
		 * @return {object|null}
		 *
		 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.3
		 */
		selected() {
			return this.phases.find((phase) => phase.id === this.selectedId) || null
		},

		/**
		 * Files, notes and audit trail of the selected phase, over OpenRegister's per-object endpoints.
		 *
		 * @return {object}
		 *
		 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.3
		 */
		sidebarConfig() {
			return {
				objectId: this.selectedId,
				register: PLANNINQ_REGISTER,
				schema: 'projectPhase',
				objectType: 'planninq-projectPhase',
				useRegistry: false,
				hiddenTabs: TASK_SIDEBAR_HIDDEN_TABS,
			}
		},
	},

	/**
	 * @spec exclude Lifecycle glue: loads the phases.
	 */
	async mounted() {
		await this.load()
	},

	methods: {
		/**
		 * Load the phases, then flag completed ones whose concluding document is gone.
		 *
		 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.5
		 */
		async load() {
			this.phases = await useProjectsStore().fetchPhases(this.projectId)
			this.loading = false
			const flagged = {}
			await Promise.all(this.phases.filter((phase) => phase.status === 'completed').map(async (phase) => {
				try {
					flagged[phase.id] = missingConcludingDocument(phase, await listPhaseFiles(phase.id))
				} catch {
					flagged[phase.id] = false
				}
			}))
			this.flagged = flagged
		},

		/**
		 * @param {string} status The phase status.
		 * @return {string}
		 *
		 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.2
		 */
		statusLabel(status) {
			return {
				open: this.t('planninq', 'Open'),
				in_progress: this.t('planninq', 'In progress'),
				completed: this.t('planninq', 'Completed'),
				cancelled: this.t('planninq', 'Cancelled'),
			}[status] || this.t('planninq', 'Open')
		},

		/**
		 * @param {object} phase The phase.
		 *
		 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.3
		 */
		select(phase) {
			this.selectedId = this.selectedId === phase.id ? '' : phase.id
		},

		/**
		 * @param {boolean} open Whether the sidebar stays open.
		 *
		 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.3
		 */
		onSidebarToggle(open) {
			if (!open) {
				this.selectedId = ''
			}
		},

		/**
		 * @param {object|null} phase The phase to edit, or null to add one.
		 *
		 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.2
		 */
		openEdit(phase) {
			this.editing = { phase }
		},

		/**
		 * @param {object} phase The phase to move.
		 * @param {number} step -1 or +1.
		 *
		 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.2
		 */
		async move(phase, step) {
			this.moving = true
			await useProjectsStore().reorderPhase(this.phases, phase.id, step)
			await this.load()
			this.moving = false
			this.$nextTick(() => {
				const button = this.$el.querySelector(`[data-phase="${phase.id}"] [data-testid="phase-move-${step < 0 ? 'up' : 'down'}"]`)
				;(button && !button.disabled ? button : this.$el.querySelector(`[data-phase="${phase.id}"] [data-testid="phase-open"]`))?.focus()
			})
		},

		/**
		 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.2
		 */
		async onSaved() {
			this.editing = null
			await this.load()
		},

		/**
		 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.4
		 */
		async onClosed() {
			this.closing = null
			await this.load()
		},
	},
}
</script>

<style scoped>
.project-phases {
	display: flex;
	gap: 16px;
}

.project-phases__main {
	flex: 1 1 auto;
	min-width: 0;
	padding: 8px 4px 24px;
	max-width: 1100px;
}

.project-phases__header {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 12px;
}

.project-phases__header h2 {
	margin: 0;
	font-size: 22px;
	font-weight: 600;
}

.project-phases__loading {
	display: flex;
	justify-content: center;
	padding: 48px;
}

.project-phases__list {
	margin: 0;
	padding: 0;
	list-style: none;
}

.project-phases__item {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px 16px;
	padding: 8px;
	border-bottom: 1px solid var(--color-border);
}

.project-phases__item--selected {
	background-color: var(--color-primary-element-light);
}

.project-phases__name {
	padding: 4px 8px;
	border: none;
	border-radius: var(--border-radius-element);
	background: none;
	color: var(--color-main-text);
	font: inherit;
	font-weight: 600;
	cursor: pointer;
}

.project-phases__name:hover {
	background-color: var(--color-background-hover);
}

.project-phases__name:focus-visible {
	outline: 2px solid var(--color-primary-element);
}

.project-phases__meta {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
	flex: 1 1 auto;
	color: var(--color-text-maxcontrast);
}

.project-phases__flag {
	color: var(--color-warning-text);
	font-weight: 600;
}

.project-phases__actions {
	display: flex;
	align-items: center;
	gap: 4px;
}
</style>
