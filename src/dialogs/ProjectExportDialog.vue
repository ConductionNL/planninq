<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<NcDialog
		v-model:open="open"
		:name="t('planninq', 'Export project')"
		@close="$emit('close')">
		<template #default>
			<p>{{ t('planninq', 'Download everything in this project as a zip in your Files.') }}</p>
			<p v-if="error" class="project-export-dialog__error" role="alert">
				{{ error }}
			</p>
		</template>

		<template #actions>
			<NcButton
				:disabled="loading"
				variant="primary"
				data-testid="project-export-confirm"
				@click="confirm">
				<template v-if="loading" #icon>
					<NcLoadingIcon :size="16" />
				</template>
				{{ t('planninq', 'Export project') }}
			</NcButton>
			<NcButton :disabled="loading" @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { showSuccess } from '@nextcloud/dialogs'
/**
 * ProjectExportDialog.
 *
 * Confirms and queues the export of a whole project to a zip in the
 * caller's Files; the caller gets a notification when it is there.
 *
 * @spec openspec/changes/project-data-export/tasks.md#task-4.2
 */
import { NcButton, NcDialog, NcLoadingIcon } from '@nextcloud/vue'
import { exportErrorMessage, startProjectExport } from '../api/projectExport.js'

export default {
	name: 'ProjectExportDialog',

	components: { NcButton, NcDialog, NcLoadingIcon },

	props: {
		projectId: {
			type: String,
			required: true,
		},
	},

	emits: ['close', 'queued'],

	data() {
		return {
			open: true,
			loading: false,
			error: '',
		}
	},

	methods: {
		/**
		 * Queue the export, then close with the running message; keep the dialog open with the server's message on an error.
		 *
		 * @spec openspec/changes/project-data-export/tasks.md#task-4.2
		 */
		async confirm() {
			this.loading = true
			this.error = ''
			try {
				await startProjectExport(this.projectId)
				showSuccess(this.t('planninq', 'The export is running. You will get a notification when it is in your Files.'))
				this.$emit('queued')
				this.$emit('close')
			} catch (error) {
				this.error = exportErrorMessage(error, this.t('planninq', 'The export could not be started.'))
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.project-export-dialog__error {
	color: var(--color-error-text);
}
</style>
