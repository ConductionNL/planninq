<template>
	<div class="project-request-banner" data-testid="project-request-banner">
		<NcNoteCard :type="banner.kind === 'waiting' ? 'info' : 'warning'">
			<p class="project-request-banner__title">
				{{ banner.kind === 'waiting'
					? t('planninq', 'This project is waiting for review')
					: t('planninq', 'This request was not approved') }}
			</p>
			<p v-if="banner.note" data-testid="project-request-reason">
				{{ t('planninq', 'Reason: {note}', { note: banner.note }) }}
			</p>
		</NcNoteCard>
		<NcButton
			v-if="buttons.approve || buttons.reject"
			variant="primary"
			data-testid="project-request-review"
			@click="reviewing = true">
			{{ t('planninq', 'Review request') }}
		</NcButton>
		<ProjectRequestReviewDialog
			v-if="reviewing"
			:project="project"
			:canApprove="buttons.approve"
			:canReject="buttons.reject"
			@close="reviewing = false"
			@reviewed="onReviewed" />
	</div>
</template>

<script>
import { NcButton, NcNoteCard } from '@nextcloud/vue'
import ProjectRequestReviewDialog from '../dialogs/ProjectRequestReviewDialog.vue'
import { useProjectsStore } from '../store/projects.js'
import { requestBanner, reviewButtons } from '../utils/projectRequests.js'

/**
 * ProjectRequestBanner.
 *
 * Shown on the board of a requested or rejected project instead of its
 * columns; a reviewer opens the review from here
 * (projects-lifecycle-policy, section 3).
 *
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.4
 */
export default {
	name: 'ProjectRequestBanner',

	components: { NcButton, NcNoteCard, ProjectRequestReviewDialog },

	props: {
		/** The requested or rejected project. */
		project: {
			type: Object,
			required: true,
		},
	},

	emits: ['reviewed'],

	data() {
		return {
			actions: null,
			reviewing: false,
		}
	},

	computed: {
		/**
		 * The banner for the project's status.
		 *
		 * @return {{kind: string, note: string}}
		 *
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.4
		 */
		banner() {
			return requestBanner(this.project) || { kind: 'waiting', note: '' }
		},

		/**
		 * The review buttons the viewer gets.
		 *
		 * @return {{approve: boolean, reject: boolean}}
		 *
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.4
		 */
		buttons() {
			return reviewButtons(this.actions)
		},
	},

	/**
	 * Read which review actions the viewer may run.
	 *
	 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.4
	 */
	async mounted() {
		this.actions = await useProjectsStore().fetchProjectActions(this.project.id)
	},

	methods: {
		/**
		 * Close the review and hand the reviewed project up.
		 *
		 * @param {object} project The reviewed project.
		 *
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.4
		 */
		onReviewed(project) {
			this.reviewing = false
			this.$emit('reviewed', project)
		},
	},
}
</script>

<style scoped>
.project-request-banner {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: 12px;
	margin: 16px 0;
}

.project-request-banner__title {
	font-weight: 600;
}
</style>
