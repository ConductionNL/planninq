<template>
	<NcDialog
		:name="isEdit ? t('planninq', 'Edit column') : t('planninq', 'Add column')"
		@closing="$emit('close')">
		<template #default>
			<div class="column-edit-dialog__body">
				<NcTextField
					v-model="title"
					:label="t('planninq', 'Column name')"
					:error="!!titleError"
					:helperText="titleError"
					data-testid="column-name"
					required />

				<NcTextField
					v-model="wipLimit"
					type="number"
					min="0"
					:label="t('planninq', 'WIP limit')"
					data-testid="column-wip-limit" />

				<div class="column-edit-dialog__color-row">
					<input
						type="color"
						class="column-edit-dialog__swatch"
						:aria-label="t('planninq', 'Pick a color')"
						:value="swatchColor"
						@input="color = ($event.target.value || '').toUpperCase()">
					<NcTextField
						v-model="color"
						:label="t('planninq', 'Hex color')"
						:error="!!colorError"
						:helperText="colorError || t('planninq', 'Six-digit hex code, e.g. #4376FC')" />
				</div>

				<NcCheckboxRadioSwitch
					:modelValue="done"
					data-testid="column-done"
					@update:modelValue="done = $event">
					{{ t('planninq', 'Done column') }}
				</NcCheckboxRadioSwitch>

				<NcSelect
					v-if="!done"
					v-model="statusOption"
					:options="statusOptions"
					:clearable="false"
					:inputLabel="t('planninq', 'Status of cards moved here')"
					label="label" />

				<div v-if="submitError" class="column-edit-dialog__error" role="alert">
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
				:disabled="saving || !isValid"
				data-testid="column-save"
				@click="save">
				<template v-if="saving" #icon>
					<NcLoadingIcon :size="16" />
				</template>
				{{ isEdit ? t('planninq', 'Save') : t('planninq', 'Create') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
/**
 * ColumnEditDialog.
 *
 * Adds a board column or edits one: its name, WIP limit, colour, whether it
 * is the done column, and the status a card gets when it is moved into it.
 * The write goes to the OpenRegister object API through the projects store;
 * the server refuses it unless the caller owns the project or is an admin.
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.1
 */
import {
	NcButton,
	NcCheckboxRadioSwitch,
	NcDialog,
	NcLoadingIcon,
	NcSelect,
	NcTextField,
} from '@nextcloud/vue'
import { useProjectsStore } from '../store/projects.js'
import { columnPayload } from '../utils/columnHelpers.js'
import { DEFAULT_LABEL_COLOR, isValidHexColor } from '../utils/labelHelpers.js'

export default {
	name: 'ColumnEditDialog',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcLoadingIcon,
		NcSelect,
		NcTextField,
	},

	props: {
		/** The column to edit; null to add one. */
		column: {
			type: Object,
			default: null,
		},

		/** The project the column belongs to. */
		projectId: {
			type: String,
			required: true,
		},

		/** The `order` a new column gets: the end of the board. */
		nextOrder: {
			type: Number,
			default: 0,
		},
	},

	emits: ['close', 'saved'],

	data() {
		const statusOptions = [
			{ id: '', label: this.t('planninq', 'Keep the card\'s status') },
			{ id: 'open', label: this.t('planninq', 'Open') },
			{ id: 'in_progress', label: this.t('planninq', 'In Progress') },
			{ id: 'blocked', label: this.t('planninq', 'Blocked') },
			{ id: 'cancelled', label: this.t('planninq', 'Cancelled') },
		]
		return {
			title: this.column?.title || '',
			wipLimit: this.column?.wipLimit ? String(this.column.wipLimit) : '',
			color: this.column?.color || '',
			done: this.column?.type === 'done',
			statusOptions,
			statusOption: statusOptions.find((option) => option.id === (this.column?.status || '')) || statusOptions[0],
			saving: false,
			submitError: '',
		}
	},

	computed: {
		/**
		 * @spec exclude Display flag: whether the dialog edits an existing column.
		 */
		isEdit() {
			return !!this.column?.id
		},

		/**
		 * @spec exclude Form validation message for the column name.
		 */
		titleError() {
			return this.title.trim() === '' ? this.t('planninq', 'Title is required') : ''
		},

		/**
		 * @spec exclude Form validation message for the optional colour.
		 */
		colorError() {
			if (this.color && !isValidHexColor(this.color)) {
				return this.t('planninq', 'Color must be a 6-digit hex code (e.g. #4376FC)')
			}
			return ''
		},

		/**
		 * @spec exclude Display helper: the colour swatch value.
		 */
		swatchColor() {
			return isValidHexColor(this.color) ? this.color : DEFAULT_LABEL_COLOR
		},

		/**
		 * @spec exclude Form validation summary.
		 */
		isValid() {
			return this.titleError === '' && this.colorError === ''
		},
	},

	methods: {
		/**
		 * Save the column and tell the board.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.1
		 */
		async save() {
			if (!this.isValid) {
				return
			}
			this.saving = true
			this.submitError = ''
			const payload = columnPayload({
				title: this.title,
				wipLimit: this.wipLimit,
				color: this.color,
				status: this.statusOption?.id,
				done: this.done,
			})
			const body = this.isEdit
				? { id: this.column.id, ...payload }
				: { ...payload, project: this.projectId, order: this.nextOrder }
			const saved = await useProjectsStore().saveColumn(body)
			this.saving = false
			if (!saved) {
				this.submitError = this.t('planninq', 'Could not save the column. Please try again.')
				return
			}
			this.$emit('saved', saved)
		},
	},
}
</script>

<style scoped>
.column-edit-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding-bottom: 8px;
}

.column-edit-dialog__color-row {
	display: flex;
	align-items: flex-end;
	gap: 8px;
}

.column-edit-dialog__swatch {
	width: 44px;
	height: 44px;
	padding: 0;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: none;
}

.column-edit-dialog__error {
	color: var(--color-error-text);
}
</style>
