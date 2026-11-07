<template>
	<NcDialog
		v-model:open="open"
		:name="t('planninq', 'Request a project')"
		:noClose="saving"
		@close="$emit('close')">
		<form class="project-request-dialog__form" @submit.prevent="next">
			<p class="project-request-dialog__step" aria-live="polite">
				{{ t('planninq', 'Step {step} of 2', { step }) }}
			</p>

			<!-- Step 1: what the project is -->
			<template v-if="step === 1">
				<NcTextField
					v-model="form.title"
					:label="t('planninq', 'Project title')"
					:error="touched && !stepValid"
					:helperText="touched && !stepValid ? t('planninq', 'Title is required') : ''"
					required
					data-testid="project-request-title" />
				<NcTextArea
					v-model="form.description"
					:label="t('planninq', 'Description')"
					rows="3"
					data-testid="project-request-description" />
			</template>

			<!-- Step 2: why it is needed -->
			<template v-else>
				<NcTextArea
					v-model="form.requestReason"
					:label="t('planninq', 'Why is this project needed?')"
					:error="touched && !stepValid"
					:helperText="touched && !stepValid ? t('planninq', 'Give a reason. The reviewer reads it.') : ''"
					rows="4"
					data-testid="project-request-reason-input" />
				<NcTextField
					v-model="form.startDate"
					type="date"
					:label="t('planninq', 'Desired start date')"
					data-testid="project-request-start" />
			</template>

			<p v-if="errorMessage" class="project-request-dialog__error" role="alert">
				{{ errorMessage }}
			</p>
		</form>

		<template #actions>
			<NcButton v-if="step === 2" :disabled="saving" @click="step = 1">
				{{ t('planninq', 'Back') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="saving"
				data-testid="project-request-next"
				@click="next">
				{{ step === 1 ? t('planninq', 'Next') : t('planninq', 'Send request') }}
			</NcButton>
			<NcButton :disabled="saving" @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog, NcTextArea, NcTextField } from '@nextcloud/vue'
import { useProjectsStore } from '../store/projects.js'
import { requestPayload, requestStepValid } from '../utils/projectRequests.js'

/**
 * ProjectRequestDialog.
 *
 * The two-step form for someone who may not create a project: what the
 * project is, then why it is needed. The server stores it as a request
 * (projects-lifecycle-policy, section 3).
 *
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.3
 */
export default {
	name: 'ProjectRequestDialog',

	components: { NcButton, NcDialog, NcTextArea, NcTextField },

	emits: ['close', 'requested'],

	data() {
		return {
			open: true,
			step: 1,
			touched: false,
			saving: false,
			errorMessage: '',
			form: { title: '', description: '', requestReason: '', startDate: '' },
		}
	},

	computed: {
		/**
		 * Whether the current step may go on.
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.3
		 */
		stepValid() {
			return requestStepValid(this.step, this.form)
		},
	},

	methods: {
		/**
		 * Go to step 2, or send the request from step 2.
		 *
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-3.3
		 */
		async next() {
			this.touched = true
			if (!this.stepValid || this.saving) {
				return
			}
			if (this.step === 1) {
				this.step = 2
				this.touched = false
				return
			}
			this.saving = true
			this.errorMessage = ''
			try {
				const project = await useProjectsStore().createProject(requestPayload(this.form))
				this.$emit('requested', project)
			} catch {
				this.errorMessage = this.t('planninq', 'The request was not sent. Try again.')
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.project-request-dialog__form {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.project-request-dialog__step {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.project-request-dialog__error {
	color: var(--color-error);
}
</style>
