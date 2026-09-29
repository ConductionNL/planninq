<template>
	<!-- `no-close` replaces the deprecated `can-close`, and it is the INVERSE of it:
	     the dialog must refuse to close WHILE loading, which is what the old
	     `:canClose="!loading"` said. -->
	<NcDialog
		v-model:open="open"
		:name="t('planninq', 'New project')"
		:noClose="loading"
		@close="$emit('close')">
		<template #default>
			<form class="project-creation-dialog__form" @submit.prevent="submit">
				<!-- Title (required) -->
				<div class="project-creation-dialog__field">
					<!-- `.native` was removed in Vue 3: a plain @focusout on a
					     component falls through to its root element via $attrs,
					     which is exactly what the modifier used to force. -->
					<NcTextField
						ref="titleField"
						v-model="form.title"
						:label="t('planninq', 'Project title')"
						:placeholder="t('planninq', 'Enter project title…')"
						:error="titleTouched && !form.title.trim()"
						required
						@focusout="titleTouched = true" />
					<span
						v-if="titleTouched && !form.title.trim()"
						class="project-creation-dialog__error"
						role="alert">
						{{ t('planninq', 'Title is required') }}
					</span>
				</div>

				<!-- Project key: the prefix of every task key, such as VERG-42 (tasks-readable-keys) -->
				<div class="project-creation-dialog__field">
					<NcTextField
						v-model="form.key"
						:label="t('planninq', 'Project key')"
						:error="!!keyMessage"
						:helperText="keyMessage || t('planninq', 'Every task number starts with it, such as VERG-42.')"
						maxlength="10"
						data-testid="project-creation-key"
						@update:modelValue="keyTouched = true" />
				</div>

				<!-- The case or client this project starts from (read-only, from the link) -->
				<p v-if="prefill.caseReference" class="project-creation-dialog__linked" data-testid="project-creation-linked-case">
					{{ prefill.title
						? t('planninq', 'Linked case: {title}', { title: prefill.title })
						: t('planninq', 'Linked to a case') }}
				</p>

				<!-- Description (optional) -->
				<div class="project-creation-dialog__field">
					<NcTextArea
						v-model="form.description"
						:label="t('planninq', 'Description')"
						:placeholder="t('planninq', 'Optional description…')"
						rows="3" />
				</div>

				<!-- Color (optional) -->
				<div class="project-creation-dialog__field">
					<label class="project-creation-dialog__label" for="project-color">
						{{ t('planninq', 'Color') }}
					</label>
					<input
						id="project-color"
						v-model="form.color"
						type="color"
						class="project-creation-dialog__color-input"
						:aria-label="t('planninq', 'Project color picker')">
				</div>

				<!-- Icon / emoji (optional) -->
				<div class="project-creation-dialog__field">
					<NcTextField
						v-model="form.icon"
						:label="t('planninq', 'Icon (emoji)')"
						:placeholder="t('planninq', 'e.g. 📁 🚀 ✅')" />
				</div>
			</form>
		</template>

		<template #actions>
			<NcButton
				:disabled="loading || !isValid"
				variant="primary"
				type="submit"
				@click="submit">
				<template v-if="loading" #icon>
					<NcLoadingIcon :size="20" />
				</template>
				{{ loading ? t('planninq', 'Creating…') : t('planninq', 'Create project') }}
			</NcButton>
			<NcButton :disabled="loading" @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
/**
 * ProjectCreationDialog.
 *
 * NcDialog wrapping the new-project form with title/description/color/icon
 * fields, required-title validation, and submit→createProject wiring.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-12
 */
import { NcButton, NcDialog, NcLoadingIcon, NcTextArea, NcTextField } from '@nextcloud/vue'
import { useProjectsStore } from '../store/projects.js'
import { isValidProjectKey, keyRefusal, normaliseProjectKey, suggestProjectKey } from '../utils/workItemKeys.js'

export default {
	name: 'ProjectCreationDialog',

	components: {
		NcButton,
		NcDialog,
		NcTextField,
		NcTextArea,
		NcLoadingIcon,
	},

	props: {
		/**
		 * What the link that opened the dialog carries: a case (with its
		 * title) or a client. The case link is saved on the project.
		 */
		prefill: {
			type: Object,
			default: () => ({}),
		},
	},

	emits: ['close', 'created'],

	data() {
		return {
			open: true,
			titleTouched: false,
			keyTouched: false,
			// 'used' from the availability check or the server; '' otherwise.
			keyTaken: '',
			keyCheckTimer: null,
			form: {
				title: this.prefill?.title || '',
				key: suggestProjectKey(this.prefill?.title || ''),
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

		/**
		 * Whether the form can be sent: a title and a free, well-formed key.
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.1
		 */
		isValid() {
			return this.form.title.trim().length > 0 && isValidProjectKey(normaliseProjectKey(this.form.key)) && this.keyTaken !== 'used'
		},

		/**
		 * The message under the key field when the key cannot be used.
		 *
		 * @return {string}
		 *
		 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.1
		 */
		keyMessage() {
			const key = normaliseProjectKey(this.form.key)
			if (this.keyTaken === 'used') {
				return this.t('planninq', 'This key is already used by another project.')
			}
			if ((this.keyTouched || this.titleTouched) && !isValidProjectKey(key)) {
				return this.t('planninq', 'Use 2 to 10 letters and digits, starting with a letter.')
			}
			return ''
		},
	},

	watch: {
		/**
		 * Suggest a key from the title until the user types one.
		 *
		 * @param {string} title The new title.
		 *
		 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.1
		 */
		'form.title': function(title) {
			if (!this.keyTouched) {
				this.form.key = suggestProjectKey(title)
			}
		},

		/**
		 * Ask the server whether the key is free, a moment after typing stops.
		 *
		 * @param {string} key The new key.
		 *
		 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.2
		 */
		'form.key': function(key) {
			this.keyTaken = ''
			clearTimeout(this.keyCheckTimer)
			const normalised = normaliseProjectKey(key)
			if (!isValidProjectKey(normalised)) {
				return
			}
			this.keyCheckTimer = setTimeout(async () => {
				const answer = await this.projectsStore.checkProjectKey(normalised)
				if (answer && answer.available === false && normaliseProjectKey(this.form.key) === normalised) {
					this.keyTaken = 'used'
				}
			}, 300)
		},
	},

	/**
	 * @spec exclude Lifecycle glue — stops a pending key check.
	 */
	beforeUnmount() {
		clearTimeout(this.keyCheckTimer)
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
		 * Posts to the Planninq server-side project proxy (ProjectController::create)
		 * which enforces the allow_project_creation policy before writing to OR.
		 * A 403 response means creation is restricted to administrators.
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-12
		 */
		async submit() {
			this.titleTouched = true
			if (!this.isValid || this.loading) {
				return
			}

			try {
				const project = await this.projectsStore.createProject({
					title: this.form.title.trim(),
					key: normaliseProjectKey(this.form.key),
					description: this.form.description.trim() || undefined,
					color: this.form.color || undefined,
					icon: this.form.icon.trim() || undefined,
					caseReference: this.prefill?.caseReference || undefined,
					client: this.prefill?.client || undefined,
				})

				showSuccess(this.t('planninq', 'Project created'))
				this.$emit('created', project)

				// Warn if column creation had partial failures.
				// (Warnings are already shown inside createDefaultColumns via toast)
			} catch (err) {
				if (keyRefusal(err?.message) === 'used') {
					this.keyTaken = 'used'
					return
				}
				const message = err?.message?.includes('restricted to administrators')
					? this.t('planninq', 'Project creation is restricted to administrators.')
					: this.t('planninq', 'Could not create project. Please try again.')
				showError(message)
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

.project-creation-dialog__linked {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.project-creation-dialog__error {
	font-size: 12px;
	color: var(--color-error);
}
</style>
