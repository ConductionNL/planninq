<template>
	<fieldset class="project-copy-parts" data-testid="project-copy-parts">
		<legend class="project-copy-parts__legend">
			{{ t('planninq', 'Copy these parts') }}
		</legend>
		<NcCheckboxRadioSwitch
			v-for="part in parts"
			:key="part.id"
			:modelValue="value[part.id]"
			:disabled="part.disabled"
			:data-testid="'project-copy-part-' + part.id"
			@update:modelValue="(checked) => set(part.id, checked)">
			{{ part.label }}
		</NcCheckboxRadioSwitch>
	</fieldset>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { consistentParts } from '../utils/projectCopy.js'

/**
 * ProjectCopyParts: the checkboxes that choose what a project copy keeps.
 * Dependencies need tasks, so they switch off with them.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.3
 */
export default {
	name: 'ProjectCopyParts',

	components: { NcCheckboxRadioSwitch },

	props: {
		/** The chosen parts, `{ columns, phases, tasks, dependencies, people }` booleans. */
		value: {
			type: Object,
			required: true,
		},
	},

	emits: ['update:value'],

	computed: {
		/**
		 * @spec exclude Display helper — the part choices with their labels.
		 */
		parts() {
			return [
				{ id: 'columns', label: this.t('planninq', 'Columns') },
				{ id: 'phases', label: this.t('planninq', 'Phases') },
				{ id: 'tasks', label: this.t('planninq', 'Tasks') },
				{ id: 'dependencies', label: this.t('planninq', 'Dependencies'), disabled: !this.value.tasks },
				{ id: 'people', label: this.t('planninq', 'People') },
			]
		},
	},

	methods: {
		t,

		/**
		 * Change one part and tell the parent.
		 *
		 * @param {string}  id      The part.
		 * @param {boolean} checked Whether it is kept.
		 *
		 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.3
		 */
		set(id, checked) {
			this.$emit('update:value', consistentParts({ ...this.value, [id]: checked === true }))
		},
	},
}
</script>

<style scoped>
.project-copy-parts {
	border: 0;
	margin: 0;
	padding: 0;
}

.project-copy-parts__legend {
	font-weight: 500;
	padding: 0;
	margin-bottom: calc(var(--default-grid-baseline) * 2);
}
</style>
