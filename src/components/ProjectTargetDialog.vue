<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<NcDialog :name="name" @closing="$emit('close')">
		<template #default>
			<div class="project-target-dialog__body">
				<slot />
				<NcLoadingIcon v-if="loading" :size="20" />
				<p v-else-if="!options.length" :data-testid="testid + '-no-targets'">
					{{ emptyText }}
				</p>
				<NcSelect
					v-else
					:modelValue="modelValue"
					:options="options"
					:inputLabel="t('planninq', 'Project')"
					label="label"
					:data-testid="testid + '-target'"
					@update:modelValue="$emit('update:modelValue', $event)" />
				<slot name="details" />

				<div v-if="error" class="project-target-dialog__error" role="alert">
					{{ error }}
				</div>
			</div>
		</template>

		<template #actions>
			<DialogSaveActions
				:saving="busy"
				:disabled="!modelValue"
				:label="confirmLabel"
				:data-testid="testid + '-confirm'"
				@cancel="$emit('close')"
				@save="$emit('confirm')" />
		</template>
	</NcDialog>
</template>

<script>
/**
 * ProjectTargetDialog — the frame of the move and copy dialogs: the dialog's
 * own text in the default slot, then the target-project select (a spinner
 * while the projects load, a note when there is none to pick), the dialog's
 * details slot, the error of a refused write, and Cancel / confirm.
 *
 * @spec exclude Presentation-only frame shared by the move dialogs; behaviour lives in each dialog.
 */
import { NcDialog, NcLoadingIcon, NcSelect } from '@nextcloud/vue'
import DialogSaveActions from './DialogSaveActions.vue'

export default {
	name: 'ProjectTargetDialog',

	components: {
		DialogSaveActions,
		NcDialog,
		NcLoadingIcon,
		NcSelect,
	},

	props: {
		/** The dialog's title, already translated. */
		name: {
			type: String,
			required: true,
		},

		/** The picked option. */
		modelValue: {
			type: Object,
			default: null,
		},

		/** The target projects as select options. */
		options: {
			type: Array,
			default: () => [],
		},

		/** Whether the projects are still loading. */
		loading: {
			type: Boolean,
			default: false,
		},

		/** The note shown when there is no project to pick, already translated. */
		emptyText: {
			type: String,
			required: true,
		},

		/** Whether the write is running: disables the buttons and shows the spinner. */
		busy: {
			type: Boolean,
			default: false,
		},

		/** The error of a refused write, already translated; none when empty. */
		error: {
			type: String,
			default: '',
		},

		/** The confirm button's text, already translated. */
		confirmLabel: {
			type: String,
			required: true,
		},

		/** The data-testid prefix of the dialog. */
		testid: {
			type: String,
			required: true,
		},
	},

	emits: ['close', 'confirm', 'update:modelValue'],
}
</script>

<style scoped>
.project-target-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: min(420px, 90vw);
}

.project-target-dialog__error {
	color: var(--color-error-text);
}
</style>
