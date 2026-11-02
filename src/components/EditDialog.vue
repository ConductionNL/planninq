<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<NcDialog v-bind="$attrs" :name="name" @closing="$emit('close')">
		<template #default>
			<slot />
		</template>

		<template #actions>
			<DialogSaveActions
				:saving="saving"
				:disabled="disabled"
				:label="label"
				:data-testid="saveTestid || undefined"
				@cancel="$emit('close')"
				@save="$emit('save', $event)">
				<slot name="actions" />
			</DialogSaveActions>
		</template>
	</NcDialog>
</template>

<script>
/**
 * EditDialog: the frame of an edit dialog. An NcDialog with the form in its
 * default slot and the Cancel and Save buttons of DialogSaveActions as its
 * actions; closing the dialog or Cancel emits close, Save emits save.
 *
 * Other attributes, such as size, go to the NcDialog. Buttons in the actions
 * slot render before Cancel.
 *
 * @spec exclude Presentation-only frame shared by the edit dialogs; behaviour lives in each dialog.
 */
import { NcDialog } from '@nextcloud/vue'
import DialogSaveActions from './DialogSaveActions.vue'

export default {
	name: 'EditDialog',

	components: {
		DialogSaveActions,
		NcDialog,
	},

	inheritAttrs: false,

	props: {
		/** The dialog title, already translated. */
		name: {
			type: String,
			required: true,
		},

		/** saving, disabled and label, passed on to the Save button. */
		...DialogSaveActions.props,

		/** The data-testid of the Save button. */
		saveTestid: {
			type: String,
			default: '',
		},
	},

	emits: ['close', 'save'],
}
</script>
