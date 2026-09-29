<template>
	<NcDialog
		:name="phase ? t('planninq', 'Edit phase') : t('planninq', 'Add phase')"
		@closing="$emit('close')">
		<template #default>
			<div class="phase-edit-dialog__body">
				<NcTextField
					v-model="title"
					:label="t('planninq', 'Name')"
					data-testid="phase-title"
					required />
				<NcTextArea
					v-model="description"
					:label="t('planninq', 'Description')"
					resize="vertical" />
				<div class="phase-edit-dialog__row">
					<NcTextField v-model="startDate"
						type="date"
						:label="t('planninq', 'Start')"
						data-testid="phase-start" />
					<NcTextField v-model="endDate"
						type="date"
						:label="t('planninq', 'End')"
						data-testid="phase-end" />
				</div>
				<NcTextField
					v-model="budgetHours"
					type="number"
					min="0"
					:label="t('planninq', 'Budget hours')"
					data-testid="phase-budget-hours" />
				<div v-if="submitError" class="phase-edit-dialog__error" role="alert">
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
				:disabled="saving || title.trim() === ''"
				data-testid="phase-save"
				@click="save">
				<template v-if="saving" #icon>
					<NcLoadingIcon :size="16" />
				</template>
				{{ t('planninq', 'Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
/**
 * PhaseEditDialog.
 *
 * Adds a phase to a project, last in order, or edits one: name, description,
 * planned dates and budget hours. Status changes go through the phase list and
 * the close dialog, where the lifecycle applies.
 *
 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.2
 */
import { NcButton, NcDialog, NcLoadingIcon, NcTextArea, NcTextField } from '@nextcloud/vue'
import { useProjectsStore } from '../store/projects.js'
import { phasePayload } from '../utils/phaseHelpers.js'

export default {
	name: 'PhaseEditDialog',

	components: {
		NcButton,
		NcDialog,
		NcLoadingIcon,
		NcTextArea,
		NcTextField,
	},

	props: {
		/** The project the phase belongs to. */
		projectId: {
			type: String,
			required: true,
		},

		/** The phase to edit, or null to add one. */
		phase: {
			type: Object,
			default: null,
		},

		/** The project's phases, for the order of a new one. */
		phases: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['close', 'saved'],

	data() {
		return {
			title: this.phase?.title || '',
			description: this.phase?.description || '',
			startDate: this.phase?.startDate || '',
			endDate: this.phase?.endDate || '',
			budgetHours: this.phase?.budgetHours ?? '',
			saving: false,
			submitError: '',
		}
	},

	methods: {
		/**
		 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.2
		 */
		async save() {
			this.saving = true
			this.submitError = ''
			const fields = { id: this.phase?.id, title: this.title, description: this.description, startDate: this.startDate, endDate: this.endDate, budgetHours: this.budgetHours }
			const payload = phasePayload(fields, this.projectId, this.phases)
			const result = await useProjectsStore().savePhase(this.phase ? { id: this.phase.id, ...payload } : payload)
			this.saving = false
			if (!result.ok) {
				this.submitError = this.t('planninq', 'Could not save the phase. Please try again.')
				return
			}
			this.$emit('saved', result.phase)
		},
	},
}
</script>

<style scoped>
.phase-edit-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: min(480px, 90vw);
}

.phase-edit-dialog__row {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
}

.phase-edit-dialog__error {
	color: var(--color-error-text);
}
</style>
