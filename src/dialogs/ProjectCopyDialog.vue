<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<EditDialog
		:name="t('planninq', 'Copy project')"
		:saving="saving"
		:disabled="!title.trim()"
		:label="t('planninq', 'Copy project')"
		saveTestid="project-copy-save"
		@close="$emit('close')"
		@save="save">
		<div class="project-copy-dialog__body">
			<NcTextField
				v-model="title"
				:label="t('planninq', 'Title')"
				data-testid="project-copy-title"
				required />
			<NcTextField
				v-model="startDate"
				type="date"
				:label="t('planninq', 'Start date')"
				:helperText="t('planninq', 'Every date in the copy moves along with it.')"
				data-testid="project-copy-start-date" />

			<fieldset class="project-copy-dialog__parts">
				<legend>{{ t('planninq', 'Take along') }}</legend>
				<NcCheckboxRadioSwitch
					v-for="part in partOptions"
					:key="part.id"
					v-model="parts[part.id]"
					:disabled="part.id === 'dependencies' && !parts.tasks"
					:data-testid="`project-copy-${part.id}`">
					{{ part.label }}
				</NcCheckboxRadioSwitch>
			</fieldset>
			<p class="project-copy-dialog__hint">
				{{ t('planninq', 'Copied tasks start open, without logged time or comments. You are the owner of the copy.') }}
			</p>

			<p v-if="submitError" class="project-copy-dialog__error" role="alert">
				{{ submitError }}
			</p>
		</div>
	</EditDialog>
</template>

<script>
/**
 * ProjectCopyDialog.
 *
 * Copies a project with the parts the person picks: columns, tasks, phases,
 * dependencies and people. The server remaps every reference inside the copy
 * and moves its dates to the new start date.
 *
 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-1.3
 */
import { NcCheckboxRadioSwitch, NcTextField } from '@nextcloud/vue'
import EditDialog from './EditDialog.vue'
import { copyProject } from '../api/projectCopy.js'
import { copyBody, defaultCopyParts } from '../utils/projectTemplates.js'

export default {
	name: 'ProjectCopyDialog',

	components: {
		EditDialog,
		NcCheckboxRadioSwitch,
		NcTextField,
	},

	props: {
		/** The project to copy. */
		project: { type: Object, required: true },
	},

	emits: ['close', 'copied'],

	data() {
		return {
			title: this.t('planninq', 'Copy of {title}', { title: this.project.title || '' }),
			startDate: this.project.startDate || '',
			parts: defaultCopyParts(),
			saving: false,
			submitError: '',
		}
	},

	computed: {
		/**
		 * @spec exclude Trivial getter: the parts a copy can take along, labelled.
		 */
		partOptions() {
			return [
				{ id: 'columns', label: this.t('planninq', 'Columns') },
				{ id: 'tasks', label: this.t('planninq', 'Tasks') },
				{ id: 'phases', label: this.t('planninq', 'Phases') },
				{ id: 'dependencies', label: this.t('planninq', 'Dependencies') },
				{ id: 'people', label: this.t('planninq', 'People') },
			]
		},
	},

	methods: {
		/**
		 * Copy the project; on success emit the new project, else show why.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-1.3
		 */
		async save() {
			this.saving = true
			this.submitError = ''
			try {
				const copy = await copyProject(this.project.id, copyBody({ title: this.title, startDate: this.startDate, parts: this.parts }))
				this.$emit('copied', copy)
			} catch (error) {
				this.submitError = error.message === 'copy-error' ? this.t('planninq', 'The project could not be copied. Please try again.') : error.message
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.project-copy-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: min(420px, 90vw);
}

.project-copy-dialog__parts {
	border: none;
	padding: 0;
	margin: 0;
}

.project-copy-dialog__hint {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.project-copy-dialog__error {
	color: var(--color-error-text);
}
</style>
