<template>
	<NcDialog
		:name="t('planix', 'New project')"
		:open.sync="open"
		:can-close="!loading"
		@close="$emit('close')">
		<template #default>
			<form class="project-creation-dialog__form" @submit.prevent="submit">
				<!-- Title (required) -->
				<div class="project-creation-dialog__field">
					<NcTextField
						ref="titleField"
						:value="form.title"
						:label="t('planix', 'Project title')"
						:placeholder="t('planix', 'Enter project title…')"
						:error="titleTouched && !form.title.trim()"
						required
						@update:value="form.title = $event"
						@focusout.native="titleTouched = true" />
					<span
						v-if="titleTouched && !form.title.trim()"
						class="project-creation-dialog__error"
						role="alert">
						{{ t('planix', 'Title is required') }}
					</span>
				</div>

				<!-- Description (optional) -->
				<div class="project-creation-dialog__field">
					<NcTextArea
						:value="form.description"
						:label="t('planix', 'Description')"
						:placeholder="t('planix', 'Optional description…')"
						rows="3"
						@update:value="form.description = $event" />
				</div>

				<!-- Color (optional) -->
				<div class="project-creation-dialog__field">
					<label class="project-creation-dialog__label" for="project-color">
						{{ t('planix', 'Color') }}
					</label>
					<input
						id="project-color"
						v-model="form.color"
						type="color"
						class="project-creation-dialog__color-input"
						:aria-label="t('planix', 'Project color picker')">
				</div>

				<!-- Icon / emoji (optional) -->
				<div class="project-creation-dialog__field">
					<NcTextField
						:value="form.icon"
						:label="t('planix', 'Icon (emoji)')"
						:placeholder="t('planix', 'e.g. 📁 🚀 ✅')"
						@update:value="form.icon = $event" />
				</div>
			</form>
		</template>

		<template #actions>
			<NcButton
				:disabled="loading || !isValid"
				type="primary"
				native-type="submit"
				@click="submit">
				<template v-if="loading" #icon>
					<NcLoadingIcon :size="20" />
				</template>
				{{ loading ? t('planix', 'Creating…') : t('planix', 'Create project') }}
			</NcButton>
			<NcButton :disabled="loading" @click="$emit('close')">
				{{ t('planix', 'Cancel') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
/**
 * ProjectCreationDialog.
 *
 * NcDialog wrapping the new-project form with title/description/color/icon
 * fields, required-title validation, and submit→createProject wiring.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-12
 */
import { NcButton, NcDialog, NcTextField, NcTextArea, NcLoadingIcon } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import { useProjectsStore } from '../../store/projects.js'

export default {
	name: 'ProjectCreationDialog',

	components: {
		NcButton,
		NcDialog,
		NcTextField,
		NcTextArea,
		NcLoadingIcon,
	},

	emits: ['close', 'created'],

	data() {
		return {
			open: true,
			titleTouched: false,
			form: {
				title: '',
				description: '',
				color: '#0082c9',
				icon: '',
			},
		}
	},

	computed: {
		/**
		 * @spec exclude Store passthrough — returns the projects Pinia store.
		 */
		projectsStore() {
			return useProjectsStore()
		},
		/**
		 * @spec exclude Store passthrough — proxies projectsStore.loading.
		 */
		loading() {
			return this.projectsStore.loading
		},
		isValid() {
			return this.form.title.trim().length > 0
		},
	},

	/**
	 * @spec exclude Lifecycle glue — autofocuses the title field on mount.
	 */
	mounted() {
		this.$nextTick(() => {
			this.$refs.titleField?.$el?.querySelector('input')?.focus()
		})
	},

	methods: {
		/**
		 * Validate the form and create the project.
		 *
		 * Calls the server-side policy check endpoint before delegating to OR.
		 * This enforces the allow_project_creation setting server-side even
		 * for users who bypass the client-side canCreateProject guard.
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-12
		 */
		async submit() {
			this.titleTouched = true
			if (!this.isValid || this.loading) return

			// Server-side policy gate: 403 = creation restricted to admins.
			try {
				const policyUrl = generateUrl('/apps/planix/api/projects/check-create-policy')
				const policyResp = await fetch(policyUrl, {
					headers: { requesttoken: OC.requestToken },
				})
				if (policyResp.status === 403) {
					showError(this.t('planix', 'Project creation is restricted to administrators.'))
					this.$emit('close')
					return
				}
				if (!policyResp.ok) {
					throw new Error(`Policy check failed: ${policyResp.status}`)
				}
			} catch (err) {
				showError(this.t('planix', 'Could not verify project creation policy. Please try again.'))
				return
			}

			try {
				const project = await this.projectsStore.createProject({
					title: this.form.title.trim(),
					description: this.form.description.trim() || undefined,
					color: this.form.color || undefined,
					icon: this.form.icon.trim() || undefined,
				})

				showSuccess(this.t('planix', 'Project created'))
				this.$emit('created', project)

				// Warn if column creation had partial failures.
				// (Warnings are already shown inside createDefaultColumns via toast)
			} catch {
				showError(this.t('planix', 'Could not create project. Please try again.'))
				// Keep dialog open and preserve form values.
			}
		},
	},
}
</script>

<style scoped>
.project-creation-dialog__form {
	display: flex;
	flex-direction: column;
	gap: 16px;
	padding: 4px 0;
}

.project-creation-dialog__field {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.project-creation-dialog__label {
	font-size: 14px;
	font-weight: 500;
	color: var(--color-main-text);
}

.project-creation-dialog__color-input {
	width: 48px;
	height: 36px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	cursor: pointer;
	padding: 2px;
	background: none;
}

.project-creation-dialog__error {
	font-size: 12px;
	color: var(--color-error);
}
</style>
