<template>
	<NcDialog
		:name="t('planix', 'Delete project')"
		@closing="$emit('close')">
		<template #default>
			<div class="project-delete-dialog__body">
				<NcLoadingIcon v-if="countLoading" :size="24" />
				<div v-else-if="!canDelete" class="project-delete-dialog__forbidden" role="alert">
					<p>{{ t('planix', 'Only the project owner or an administrator can delete this project.') }}</p>
				</div>
				<p v-else>
					{{ t('planix', 'This will permanently delete {count} tasks and all their time entries. This cannot be undone.', { count: taskCount }) }}
				</p>
			</div>
		</template>

		<template #actions>
			<NcButton
				:disabled="loading || countLoading || !canDelete"
				type="error"
				@click="confirm">
				<template v-if="loading" #icon>
					<NcLoadingIcon :size="16" />
				</template>
				{{ t('planix', 'Delete project') }}
			</NcButton>
			<NcButton :disabled="loading" @click="$emit('close')">
				{{ t('planix', 'Cancel') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
/**
 * ProjectDeleteDialog.
 *
 * Confirms cascade-deletion of a project, showing the assigned-task warning.
 * Only the project owner or a Nextcloud admin may proceed.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-9
 */
import { NcButton, NcDialog, NcLoadingIcon } from '@nextcloud/vue'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { getCurrentUser } from '@nextcloud/auth'
import { useProjectsStore } from '../../store/projects.js'
import { useSettingsStore } from '../../store/modules/settings.js'

export default {
	name: 'ProjectDeleteDialog',

	components: { NcButton, NcDialog, NcLoadingIcon },

	props: {
		project: {
			type: Object,
			required: true,
		},
	},

	emits: ['close', 'deleted'],

	data() {
		return {
			loading: false,
			countLoading: true,
			taskCount: 0,
		}
	},

	computed: {
		/**
		 * @spec exclude Auth passthrough — returns the current user's UID.
		 */
		currentUid() {
			return getCurrentUser()?.uid || ''
		},

		/**
		 * True if the current user is the project owner or a Nextcloud admin.
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-9
		 */
		canDelete() {
			const settingsStore = useSettingsStore()
			return (
				this.project.owner === this.currentUid
				|| settingsStore.isAdmin
			)
		},
	},

	/**
	 * Load the project's task count to populate the cascade-delete warning.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-9
	 */
	async mounted() {
		const store = useProjectsStore()
		this.taskCount = await store.getTaskCount(this.project.id)
		this.countLoading = false
	},

	methods: {
		/**
		 * Confirm and execute the cascade deletion of the project.
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-9
		 */
		async confirm() {
			if (!this.canDelete) return
			this.loading = true
			try {
				const store = useProjectsStore()
				const ok = await store.deleteProject(this.project.id)
				if (ok) {
					showSuccess(this.t('planix', 'Project deleted'))
					this.$emit('deleted')
				}
			} catch {
				showError(this.t('planix', 'Could not delete project'))
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.project-delete-dialog__body {
	display: flex;
	align-items: center;
	gap: 12px;
}

.project-delete-dialog__forbidden {
	color: var(--color-error);
}
</style>
