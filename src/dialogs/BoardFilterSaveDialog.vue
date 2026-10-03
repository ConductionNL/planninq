<template>
	<NcDialog
		:name="saved ? t('planninq', 'Rename saved filter') : t('planninq', 'Save filter')"
		@closing="$emit('close')">
		<template #default>
			<div class="board-filter-save-dialog__body">
				<NcTextField
					v-model="name"
					:label="t('planninq', 'Name')"
					:error="!!nameError"
					:helperText="nameError"
					data-testid="saved-filter-name"
					required />
				<NcCheckboxRadioSwitch
					:modelValue="shared"
					data-testid="saved-filter-shared"
					@update:modelValue="shared = $event">
					{{ t('planninq', 'Share with the project') }}
				</NcCheckboxRadioSwitch>
				<div v-if="submitError" class="board-filter-save-dialog__error" role="alert">
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
				:disabled="saving || !!nameError"
				data-testid="saved-filter-save"
				@click="save">
				{{ t('planninq', 'Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
/**
 * BoardFilterSaveDialog: save the board's active filter under a name,
 * privately or shared with the project, or rename a saved one. The owner is
 * set by the server (BoardFilterOwnerListener).
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-3.2
 */
import { NcButton, NcCheckboxRadioSwitch, NcDialog, NcTextField } from '@nextcloud/vue'
import { useProjectsStore } from '../store/projects.js'
import { savedFilterPayload } from '../utils/boardFilter.js'

export default {
	name: 'BoardFilterSaveDialog',

	components: { NcButton, NcCheckboxRadioSwitch, NcDialog, NcTextField },

	props: {
		/** The saved filter to rename; null to save the active filter. */
		saved: {
			type: Object,
			default: null,
		},

		/** The active filter, saved as the criteria of a new filter. */
		filter: {
			type: Object,
			required: true,
		},

		/** The project UUID. */
		projectId: {
			type: String,
			required: true,
		},
	},

	emits: ['close', 'saved'],

	data() {
		return {
			name: this.saved?.name || '',
			shared: !!this.saved?.shared,
			saving: false,
			submitError: '',
		}
	},

	computed: {
		/**
		 * @spec exclude Form validation message for the name.
		 */
		nameError() {
			return this.name.trim() === '' ? this.t('planninq', 'Give the filter a name') : ''
		},
	},

	methods: {
		/**
		 * Write the saved filter: a new one with the active criteria, or the
		 * name and sharing of an existing one.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-3.2
		 */
		async save() {
			this.saving = true
			this.submitError = ''
			const payload = savedFilterPayload({ name: this.name, shared: this.shared, filter: this.filter, project: this.projectId })
			const body = this.saved
				? { id: this.saved.id, name: payload.name, shared: payload.shared }
				: payload
			const result = await useProjectsStore().saveBoardFilter(body)
			this.saving = false
			if (!result) {
				this.submitError = this.t('planninq', 'Could not save the filter. Please try again.')
				return
			}
			this.$emit('saved', result)
		},
	},
}
</script>

<style scoped>
.board-filter-save-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding-bottom: 8px;
}

.board-filter-save-dialog__error {
	color: var(--color-error-text);
}
</style>
