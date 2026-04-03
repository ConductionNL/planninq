<template>
	<NcDialog
		:name="t('planix', 'Delete project')"
		:open.sync="open"
		@close="$emit('close')">
		<template #default>
			<div class="project-delete-dialog__body">
				<NcLoadingIcon v-if="countLoading" :size="24" />
				<p v-else>
					{{ t('planix', 'This will permanently delete {count} tasks and all their time entries. This cannot be undone.', { count: taskCount }) }}
				</p>
			</div>
		</template>

		<template #actions>
			<NcButton
				:disabled="loading || countLoading"
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
import { NcButton, NcDialog, NcLoadingIcon } from '@nextcloud/vue'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { useProjectsStore } from '../../store/projects.js'

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
			open: true,
			loading: false,
			countLoading: true,
			taskCount: 0,
		}
	},

	async mounted() {
		const store = useProjectsStore()
		this.taskCount = await store.getTaskCount(this.project.id)
		this.countLoading = false
	},

	methods: {
		async confirm() {
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
</style>
