<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<slot />
	<NcButton :disabled="saving" @click="$emit('cancel')">
		{{ t('planninq', 'Cancel') }}
	</NcButton>
	<NcButton
		v-bind="$attrs"
		variant="primary"
		:disabled="saving || disabled"
		@click="$emit('save', $event)">
		<template v-if="saving" #icon>
			<NcLoadingIcon :size="16" />
		</template>
		{{ label || t('planninq', 'Save') }}
	</NcButton>
</template>

<script>
/**
 * DialogSaveActions — the Cancel and Save buttons of an edit dialog, for its
 * `#actions` slot.
 *
 * Both buttons are disabled while saving, and the Save button shows a spinner
 * then. Attributes such as `data-testid` go to the Save button. Buttons in the
 * default slot render before Cancel.
 *
 * @spec exclude Presentation-only button pair shared by the edit dialogs; behaviour lives in each dialog.
 */
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'

export default {
	name: 'DialogSaveActions',

	components: {
		NcButton,
		NcLoadingIcon,
	},

	inheritAttrs: false,

	props: {
		/** Whether the dialog is saving: disables both buttons and shows the spinner. */
		saving: {
			type: Boolean,
			default: false,
		},

		/** Disables the Save button while the form cannot be saved. */
		disabled: {
			type: Boolean,
			default: false,
		},

		/** The Save button's text, already translated; 'Save' when empty. */
		label: {
			type: String,
			default: '',
		},
	},

	emits: ['cancel', 'save'],
}
</script>
