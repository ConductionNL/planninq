<template>
	<NcDialog
		:name="view ? t('planninq', 'Edit view') : t('planninq', 'New view')"
		@closing="$emit('close')">
		<template #default>
			<div class="projects-view-edit-dialog__body">
				<NcTextField
					v-model="title"
					:label="t('planninq', 'Name')"
					:error="touched && problems.includes('title')"
					:helperText="touched && problems.includes('title') ? t('planninq', 'Give the view a name') : ''"
					data-testid="view-name"
					required />
				<NcSelect
					v-model="pickedProjects"
					:options="projectOptions"
					:multiple="true"
					:inputLabel="t('planninq', 'Projects')"
					label="label"
					data-testid="view-projects" />
				<p v-if="touched && projectsError" class="projects-view-edit-dialog__error" role="alert">
					{{ projectsError }}
				</p>
				<NcSelect
					v-model="pickedPeople"
					:options="peopleOptions"
					:multiple="true"
					:inputLabel="t('planninq', 'Shared with')"
					label="label"
					data-testid="view-people" />
				<p class="projects-view-edit-dialog__hint">
					{{ t('planninq', 'People see the tasks of the projects they are in, never more.') }}
				</p>
				<div v-if="submitError" class="projects-view-edit-dialog__error" role="alert">
					{{ submitError }}
				</div>
			</div>
		</template>

		<template #actions>
			<NcButton :disabled="saving" @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="saving"
				data-testid="view-save"
				@click="save">
				{{ t('planninq', 'Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
/**
 * ProjectsViewEditDialog: name a cross-project view, pick its projects from
 * the user's own projects and the people it is shared with, from the members
 * of those projects. The owner is set by the server (BoardFilterOwnerListener).
 *
 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-3.1
 */
import { NcButton, NcDialog, NcSelect, NcTextField } from '@nextcloud/vue'
import { useProjectsStore } from '../store/projects.js'
import { MAX_VIEW_PROJECTS, viewPayload, viewProblems } from '../utils/projectsView.js'
import { memberOptions } from '../utils/taskPeople.js'

export default {
	name: 'ProjectsViewEditDialog',

	components: { NcButton, NcDialog, NcSelect, NcTextField },

	props: {
		/** The view to edit; null for a new one. */
		view: {
			type: Object,
			default: null,
		},

		/** The projects the user may put in a view (pickableProjects()). */
		projects: {
			type: Array,
			required: true,
		},

		/** The current user, left out of the people list. */
		uid: {
			type: String,
			default: '',
		},
	},

	emits: ['close', 'saved'],

	data() {
		const chosen = new Set(this.view?.projects || [])
		const shared = new Set(this.view?.members || [])
		return {
			title: this.view?.title || '',
			pickedProjects: this.projects.filter((project) => chosen.has(project.id)).map((project) => ({ id: project.id, label: project.title })),
			pickedPeople: [...shared].map((id) => ({ id, label: id })),
			touched: false,
			saving: false,
			submitError: '',
		}
	},

	computed: {
		/**
		 * @spec exclude Display options — the pickable projects by title.
		 */
		projectOptions() {
			return this.projects.map((project) => ({ id: project.id, label: project.title }))
		},

		/**
		 * The people offered: everyone in the picked projects but the user.
		 *
		 * @return {Array<{id: string, label: string}>}
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-3.1
		 */
		peopleOptions() {
			const picked = new Set(this.pickedProjects.map((option) => option.id))
			const byId = {}
			for (const project of this.projects.filter((candidate) => picked.has(candidate.id))) {
				for (const option of memberOptions(project)) {
					if (option.id !== this.uid) {
						byId[option.id] = option
					}
				}
			}
			return Object.values(byId).sort((a, b) => a.label.localeCompare(b.label))
		},

		/**
		 * @spec exclude Form state — the body to save.
		 */
		payload() {
			return viewPayload({
				title: this.title,
				projects: this.pickedProjects.map((option) => option.id),
				members: this.pickedPeople.map((option) => option.id),
			})
		},

		/**
		 * @spec exclude Form validation — what stops the save.
		 */
		problems() {
			return viewProblems(this.payload)
		},

		/**
		 * @spec exclude Form validation message for the projects.
		 */
		projectsError() {
			if (this.problems.includes('noProjects')) {
				return this.t('planninq', 'Pick at least one project')
			}
			if (this.problems.includes('tooManyProjects')) {
				return this.t('planninq', 'A view shows at most {max} projects', { max: MAX_VIEW_PROJECTS })
			}
			return ''
		},
	},

	methods: {
		/**
		 * Write the view: POST a new one, PATCH an existing one.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-3.1
		 */
		async save() {
			this.touched = true
			if (this.problems.length) {
				return
			}
			this.saving = true
			this.submitError = ''
			const result = await useProjectsStore().saveBoardView(this.view ? { id: this.view.id, ...this.payload } : this.payload)
			this.saving = false
			if (!result) {
				this.submitError = this.t('planninq', 'Could not save the view. Please try again.')
				return
			}
			this.$emit('saved', result)
		},
	},
}
</script>

<style scoped>
.projects-view-edit-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding-block: 8px;
}

.projects-view-edit-dialog__hint {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.projects-view-edit-dialog__error {
	margin: 0;
	color: var(--color-error-text);
}
</style>
