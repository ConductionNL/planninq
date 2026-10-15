<template>
	<NcDialog
		:name="release ? t('planninq', 'Edit release') : t('planninq', 'New release')"
		@closing="$emit('close')">
		<template #default>
			<div class="release-edit-dialog__body">
				<NcTextField
					v-model="title"
					:label="t('planninq', 'Name')"
					data-testid="release-title"
					required />
				<div class="release-edit-dialog__row">
					<NcTextField v-model="startDate"
						type="date"
						:label="t('planninq', 'Start date')"
						data-testid="release-start" />
					<NcTextField v-model="releaseDate"
						type="date"
						:label="t('planninq', 'Target date')"
						data-testid="release-date" />
				</div>
				<NcTextArea
					v-model="description"
					:label="t('planninq', 'Description')"
					resize="vertical" />
				<div v-if="submitError" class="release-edit-dialog__error" role="alert">
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
				data-testid="release-save"
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
 * ReleaseEditDialog.
 *
 * Creates a release in a project, status planned, or edits the name, dates
 * and description of one. An edit sends only those four fields as a PATCH.
 *
 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.3
 */
import { NcButton, NcDialog, NcLoadingIcon, NcTextArea, NcTextField } from '@nextcloud/vue'
import { useProjectsStore } from '../store/projects.js'
import { releasePayload } from '../utils/roadmapHelpers.js'

export default {
	name: 'ReleaseEditDialog',

	components: {
		NcButton,
		NcDialog,
		NcLoadingIcon,
		NcTextArea,
		NcTextField,
	},

	props: {
		/** The project the release belongs to. */
		projectId: {
			type: String,
			required: true,
		},

		/** The release to edit, or null for a new one. */
		release: {
			type: Object,
			default: null,
		},
	},

	emits: ['close', 'saved'],

	data() {
		return {
			title: this.release?.title || '',
			description: this.release?.description || '',
			startDate: this.release?.startDate || '',
			releaseDate: this.release?.releaseDate || '',
			saving: false,
			submitError: '',
		}
	},

	methods: {
		/**
		 * Save the release and hand it to the page.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.3
		 */
		async save() {
			this.saving = true
			this.submitError = ''
			const payload = releasePayload({ title: this.title, description: this.description, startDate: this.startDate, releaseDate: this.releaseDate }, this.projectId)
			const body = this.release
				? { id: this.release.id, title: payload.title, description: payload.description, startDate: payload.startDate, releaseDate: payload.releaseDate }
				: payload
			const saved = await useProjectsStore().saveRelease(body)
			this.saving = false
			if (!saved) {
				this.submitError = this.t('planninq', 'Could not save the release. Please try again.')
				return
			}
			this.$emit('saved', saved)
		},
	},
}
</script>

<style scoped>
.release-edit-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: min(480px, 90vw);
}

.release-edit-dialog__row {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
}

.release-edit-dialog__error {
	color: var(--color-error-text, var(--color-error));
}
</style>
