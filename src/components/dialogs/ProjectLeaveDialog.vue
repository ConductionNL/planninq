<template>
	<NcDialog
		:name="t('planix', 'Leave project')"
		:open.sync="open"
		@close="$emit('close')">
		<template #default>
			<p v-if="isLastMember" class="project-leave-dialog__warning" role="alert">
				{{ t('planix', 'You are the last member. Leaving will make this project inaccessible to all users.') }}
			</p>
			<p v-else>
				{{ t('planix', 'Are you sure you want to leave this project? You will lose access.') }}
			</p>
		</template>

		<template #actions>
			<NcButton
				:disabled="loading"
				type="error"
				@click="confirm">
				<template v-if="loading" #icon>
					<NcLoadingIcon :size="16" />
				</template>
				{{ t('planix', 'Leave project') }}
			</NcButton>
			<NcButton :disabled="loading" @click="$emit('close')">
				{{ t('planix', 'Cancel') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
/**
 * ProjectLeaveDialog.
 *
 * Leave-project flow with last-member warning.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-10
 */
import { NcButton, NcDialog, NcLoadingIcon } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { useProjectsStore } from '../../store/projects.js'

export default {
	name: 'ProjectLeaveDialog',

	components: { NcButton, NcDialog, NcLoadingIcon },

	props: {
		projectId: {
			type: String,
			required: true,
		},
	},

	emits: ['close', 'left'],

	data() {
		return {
			open: true,
			loading: false,
			isLastMember: false,
		}
	},

	async mounted() {
		const store = useProjectsStore()
		const result = await store.leaveProject(this.projectId)
		// Check if this would be last-member removal.
		if (result && typeof result === 'object' && result.isLastMember) {
			this.isLastMember = true
		}
	},

	methods: {
		async confirm() {
			this.loading = true
			try {
				const store = useProjectsStore()
				// Force removal even if last member.
				const project = await store.fetchProject(this.projectId)
				if (project) {
					const uid = store._currentUid()
					await store.removeMember(this.projectId, uid)
				}
				this.$emit('left')
			} catch {
				showError(this.t('planix', 'Could not leave project'))
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.project-leave-dialog__warning {
	font-weight: 500;
	color: var(--color-error);
}
</style>
