<template>
	<NcDialog
		v-model:open="open"
		:name="t('planninq', 'Review project request')"
		:noClose="saving"
		@close="$emit('close')">
		<dl class="project-request-review__facts">
			<dt>{{ t('planninq', 'Requested by') }}</dt>
			<dd>{{ project.owner }}</dd>
			<dt>{{ t('planninq', 'Why it is needed') }}</dt>
			<dd>{{ project.requestReason || '' }}</dd>
			<template v-if="project.startDate">
				<dt>{{ t('planninq', 'Desired start date') }}</dt>
				<dd>{{ project.startDate }}</dd>
			</template>
			<template v-if="project.description">
				<dt>{{ t('planninq', 'Description') }}</dt>
				<dd>{{ project.description }}</dd>
			</template>
		</dl>

		<NcTextArea
			v-if="rejecting"
			v-model="reviewNote"
			:label="t('planninq', 'Why is it not approved?')"
			:error="noteMissing"
			:helperText="noteMissing ? t('planninq', 'Give a reason. The requester sees it.') : ''"
			rows="3"
			data-testid="project-request-note" />

		<p v-if="errorMessage" class="project-request-review__error" role="alert">
			{{ errorMessage }}
		</p>

		<template #actions>
			<template v-if="!rejecting">
				<NcButton
					v-if="canApprove"
					variant="primary"
					:disabled="saving"
					data-testid="project-request-approve"
					@click="review('approve')">
					{{ t('planninq', 'Approve') }}
				</NcButton>
				<NcButton
					v-if="canReject"
					:disabled="saving"
					data-testid="project-request-reject"
					@click="rejecting = true">
					{{ t('planninq', 'Reject') }}
				</NcButton>
			</template>
			<NcButton
				v-else
				variant="error"
				:disabled="saving"
				data-testid="project-request-confirm-reject"
				@click="review('reject')">
				{{ t('planninq', 'Reject request') }}
			</NcButton>
			<NcButton :disabled="saving" @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog, NcTextArea } from '@nextcloud/vue'
import { useProjectsStore } from '../store/projects.js'

/**
 * ProjectRequestReviewDialog.
 *
 * Shows a project request to a reviewer, who approves it or rejects it with
 * a reason. Both run as lifecycle transitions, so OpenRegister checks that
 * the reviewer may run them (projects-lifecycle-policy, section 3).
 *
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.4
 */
export default {
	name: 'ProjectRequestReviewDialog',

	components: { NcButton, NcDialog, NcTextArea },

	props: {
		/** The requested project. */
		project: {
			type: Object,
			required: true,
		},

		/** Whether the reviewer may approve. */
		canApprove: {
			type: Boolean,
			default: false,
		},

		/** Whether the reviewer may reject. */
		canReject: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['close', 'reviewed'],

	data() {
		return {
			open: true,
			rejecting: false,
			reviewNote: '',
			noteTouched: false,
			saving: false,
			errorMessage: '',
		}
	},

	computed: {
		/**
		 * Whether a rejection is being confirmed without a reason.
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.4
		 */
		noteMissing() {
			return this.noteTouched && !this.reviewNote.trim()
		},
	},

	methods: {
		/**
		 * Approve, or reject with the reason.
		 *
		 * @param {string} action approve or reject
		 *
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.4
		 */
		async review(action) {
			if (action === 'reject') {
				this.noteTouched = true
				if (!this.reviewNote.trim()) {
					return
				}
			}
			this.saving = true
			this.errorMessage = ''
			const store = useProjectsStore()
			const data = action === 'reject' ? { reviewNote: this.reviewNote.trim() } : undefined
			const updated = await store.runProjectTransition(this.project.id, action, data)
			this.saving = false
			if (!updated) {
				this.errorMessage = this.t('planninq', 'The review was not saved. Try again.')
				return
			}
			this.$emit('reviewed', updated)
		},
	},
}
</script>

<style scoped>
.project-request-review__facts {
	display: grid;
	grid-template-columns: max-content 1fr;
	gap: 4px 16px;
	margin: 0 0 12px;
}

.project-request-review__facts dt {
	color: var(--color-text-maxcontrast);
}

.project-request-review__facts dd {
	margin: 0;
	white-space: pre-wrap;
}

.project-request-review__error {
	color: var(--color-error);
}
</style>
