<template>
	<EditDialog
		:name="isEdit ? t('planninq', 'Edit entry') : t('planninq', 'Add entry')"
		:saving="saving"
		:disabled="!isValid"
		saveTestid="log-entry-save"
		@close="$emit('close')"
		@save="save">
		<div class="log-entry-dialog__body">
			<NcSelect
				v-model="typeOption"
				:options="typeOptions"
				:clearable="false"
				:inputLabel="t('planninq', 'Type')"
				label="label"
				data-testid="log-entry-type" />

			<NcTextField
				v-model="title"
				:label="t('planninq', 'Title')"
				data-testid="log-entry-title"
				required />

			<NcTextField
				v-model="date"
				type="date"
				:label="t('planninq', 'Date')"
				data-testid="log-entry-date"
				required />

			<NcSelect
				v-if="typeOption.id === 'meeting'"
				v-model="attendeeOptions"
				:options="people"
				:multiple="true"
				:inputLabel="t('planninq', 'Attendees')"
				label="label"
				data-testid="log-entry-attendees" />

			<NcCheckboxRadioSwitch
				v-if="typeOption.id === 'issue'"
				:modelValue="closed"
				data-testid="log-entry-closed"
				@update:modelValue="closed = $event">
				{{ t('planninq', 'Issue is closed') }}
			</NcCheckboxRadioSwitch>

			<NcTextArea
				v-model="body"
				:label="t('planninq', 'Details')"
				resize="vertical"
				data-testid="log-entry-body" />

			<div v-if="submitError" class="log-entry-dialog__error" role="alert">
				{{ submitError }}
			</div>
		</div>
	</EditDialog>
</template>

<script>
/**
 * LogEntryEditDialog.
 *
 * Adds an entry to a project's log or edits one: its type, title, date,
 * details, the attendees of a meeting and whether an issue is closed. The
 * server adds the author and the time, and refuses a caller who is not on the
 * project.
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
 */
import { NcCheckboxRadioSwitch, NcSelect, NcTextArea, NcTextField } from '@nextcloud/vue'
import EditDialog from '../components/EditDialog.vue'
import dialogSave from '../mixins/dialogSave.js'
import projectDialog from '../mixins/projectDialog.js'
import { useProjectsStore } from '../store/projects.js'
import { localIsoDate } from '../utils/localDate.js'
import { logEntryPayload } from '../utils/projectOverview.js'

export default {
	name: 'LogEntryEditDialog',

	components: {
		EditDialog,
		NcCheckboxRadioSwitch,
		NcSelect,
		NcTextArea,
		NcTextField,
	},

	mixins: [dialogSave, projectDialog],

	props: {
		/** The entry to edit; null to add one. */
		entry: {
			type: Object,
			default: null,
		},

		/** The people on the project, as `{ id, label }`. */
		people: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['close', 'saved'],

	data() {
		const typeOptions = [
			{ id: 'issue', label: this.t('planninq', 'Issue') },
			{ id: 'lesson', label: this.t('planninq', 'Lesson learned') },
			{ id: 'meeting', label: this.t('planninq', 'Meeting') },
			{ id: 'decision', label: this.t('planninq', 'Decision') },
		]
		const attendees = this.entry?.attendees || []
		return {
			typeOptions,
			typeOption: typeOptions.find((option) => option.id === this.entry?.type) || typeOptions[0],
			title: this.entry?.title || '',
			date: this.entry?.date || localIsoDate(),
			body: this.entry?.body || '',
			closed: this.entry?.status === 'closed',
			attendeeOptions: this.people.filter((person) => attendees.includes(person.id)),
		}
	},

	computed: {
		/** @return {boolean} */
		isEdit() {
			return Boolean(this.entry?.id)
		},

		/** @return {boolean} */
		isValid() {
			return this.title.trim() !== '' && /^\d{4}-\d{2}-\d{2}$/.test(this.date)
		},
	},

	methods: {
		/**
		 * Save the entry and hand the saved object to the parent.
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		async save() {
			await this.submitSave(() => {
				const payload = logEntryPayload({
					type: this.typeOption.id,
					title: this.title,
					date: this.date,
					body: this.body,
					status: this.closed ? 'closed' : 'open',
					attendees: this.attendeeOptions.map((person) => person.id),
					actions: this.entry?.actions || [],
				}, this.projectId)
				return useProjectsStore().saveLogEntry(this.isEdit ? { id: this.entry.id, ...payload } : payload)
			}, this.t('planninq', 'Could not save the entry. Please try again.'))
		},
	},
}
</script>

<style scoped>
.log-entry-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: min(480px, 90vw);
}

.log-entry-dialog__error {
	color: var(--color-error-text);
}
</style>
