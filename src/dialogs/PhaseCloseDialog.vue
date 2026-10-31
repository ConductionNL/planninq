<template>
	<NcDialog :name="t('planninq', 'Close phase {title}', { title: phase.title })" @closing="$emit('close')">
		<template #default>
			<div class="phase-close-dialog__body">
				<p>{{ t('planninq', 'A phase closes with its concluding document: upload it here or pick one of the phase\'s files.') }}</p>

				<div v-if="loading" class="phase-close-dialog__loading">
					<NcLoadingIcon :size="24" />
				</div>
				<fieldset v-else-if="files.length" class="phase-close-dialog__files">
					<legend>{{ t('planninq', 'Concluding document') }}</legend>
					<NcCheckboxRadioSwitch
						v-for="file in files"
						:key="file.id"
						v-model="picked"
						type="radio"
						:value="String(file.id)"
						name="phase-concluding-document"
						data-testid="phase-close-file">
						{{ file.title }}
					</NcCheckboxRadioSwitch>
				</fieldset>
				<p v-else class="phase-close-dialog__muted" data-testid="phase-close-no-files">
					{{ t('planninq', 'This phase has no files yet.') }}
				</p>

				<label class="phase-close-dialog__upload">
					<span>{{ t('planninq', 'Upload a document') }}</span>
					<input type="file"
						data-testid="phase-close-upload"
						:disabled="uploading"
						@change="upload">
				</label>

				<div v-if="error"
					class="phase-close-dialog__error"
					role="alert"
					data-testid="phase-close-error">
					{{ error }}
				</div>
			</div>
		</template>

		<template #actions>
			<NcButton :disabled="saving" @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="saving || uploading"
				data-testid="phase-close-confirm"
				@click="close">
				<template v-if="saving" #icon>
					<NcLoadingIcon :size="16" />
				</template>
				{{ t('planninq', 'Close phase') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
/**
 * PhaseCloseDialog.
 *
 * Closes a phase in one write: the member uploads or picks the concluding
 * document and the dialog sends it with status completed. OpenRegister's
 * lifecycle guard refuses the write unless the file is on the phase; the
 * dialog then says what is needed and stays open. The button is not hidden
 * when no file exists, because the explanation is clearer than a missing
 * button.
 *
 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.4
 */
import { NcButton, NcCheckboxRadioSwitch, NcDialog, NcLoadingIcon } from '@nextcloud/vue'
import { listPhaseFiles, uploadPhaseFile } from '../api/phaseFiles.js'
import { useProjectsStore } from '../store/projects.js'

export default {
	name: 'PhaseCloseDialog',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcLoadingIcon,
	},

	props: {
		/** The phase to close. */
		phase: {
			type: Object,
			required: true,
		},
	},

	emits: ['close', 'closed'],

	data() {
		return {
			files: [],
			picked: this.phase.concludingDocument ? String(this.phase.concludingDocument) : '',
			loading: true,
			uploading: false,
			saving: false,
			error: '',
		}
	},

	/**
	 * @spec exclude Lifecycle glue: loads the phase's files.
	 */
	async mounted() {
		try {
			this.files = await listPhaseFiles(this.phase.id)
		} catch (err) {
			console.error('PhaseCloseDialog: could not list the files', err)
		}
		this.loading = false
	},

	methods: {
		/**
		 * Attach the chosen file and pick it as the concluding document.
		 *
		 * @param {Event} event The file input change.
		 *
		 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.4
		 */
		async upload(event) {
			const file = event.target?.files?.[0]
			if (!file) {
				return
			}
			this.uploading = true
			this.error = ''
			try {
				const before = new Set(this.files.map((one) => String(one.id)))
				this.files = await uploadPhaseFile(this.phase.id, file)
				const added = this.files.find((one) => !before.has(String(one.id)))
				this.picked = added ? String(added.id) : this.picked
			} catch (err) {
				console.error('PhaseCloseDialog: upload failed', err)
				this.error = this.t('planninq', 'The document was not uploaded. Please try again.')
			}
			this.uploading = false
		},

		/**
		 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.4
		 */
		async close() {
			this.saving = true
			this.error = ''
			const result = await useProjectsStore().closePhase(this.phase.id, this.picked)
			this.saving = false
			if (result.ok) {
				this.$emit('closed', result.phase)
				return
			}
			this.error = {
				guard: this.t('planninq', 'Upload the concluding document before you close this phase.'),
				transition: this.t('planninq', 'This phase cannot be closed from its current status.'),
			}[result.reason] || this.t('planninq', 'The phase was not closed. Please try again.')
		},
	},
}
</script>

<style scoped>
.phase-close-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: min(480px, 90vw);
}

.phase-close-dialog__files {
	border: none;
	margin: 0;
	padding: 0;
}

.phase-close-dialog__files legend {
	font-weight: 600;
	margin-bottom: 4px;
}

.phase-close-dialog__upload {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.phase-close-dialog__muted {
	color: var(--color-text-maxcontrast);
}

.phase-close-dialog__error {
	color: var(--color-error-text);
}

.phase-close-dialog__loading {
	display: flex;
	justify-content: center;
}
</style>
