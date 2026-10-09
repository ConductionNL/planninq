<template>
	<NcDialog
		v-model:open="open"
		:name="t('planninq', 'Copy project')"
		:noClose="loading"
		@close="$emit('close')">
		<form class="project-copy-dialog__form" @submit.prevent="submit">
			<NcTextField
				v-model="form.title"
				:label="t('planninq', 'Project title')"
				required
				data-testid="project-copy-title" />
			<NcTextField
				v-model="form.key"
				:label="t('planninq', 'Project key')"
				:error="!!keyMessage"
				:helperText="keyMessage || t('planninq', 'Every task number starts with it, such as VERG-42.')"
				maxlength="10"
				data-testid="project-copy-key" />
			<div class="project-copy-dialog__field">
				<label for="project-copy-start">{{ t('planninq', 'Start date') }}</label>
				<input
					id="project-copy-start"
					v-model="form.startDate"
					type="date"
					data-testid="project-copy-start">
				<span class="project-copy-dialog__hint">
					{{ t('planninq', 'Dates in the copy move along with the new start date.') }}
				</span>
			</div>
			<ProjectCopyParts v-model:value="form.parts" />
		</form>

		<template #actions>
			<NcButton :disabled="loading || !isValid"
				variant="primary"
				data-testid="project-copy-submit"
				@click="submit">
				<template v-if="loading" #icon>
					<NcLoadingIcon :size="20" />
				</template>
				{{ loading ? t('planninq', 'Copying…') : t('planninq', 'Copy project') }}
			</NcButton>
			<NcButton :disabled="loading" @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { NcButton, NcDialog, NcLoadingIcon, NcTextField } from '@nextcloud/vue'
import ProjectCopyParts from '../components/ProjectCopyParts.vue'
import { useProjectsStore } from '../store/projects.js'
import { copyPayload, defaultCopyParts } from '../utils/projectCopy.js'
import { isValidProjectKey, keyRefusal, normaliseProjectKey } from '../utils/workItemKeys.js'

/**
 * ProjectCopyDialog: copy a project into a new one, choosing which parts
 * come along. The server remaps the references and shifts the dates.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.3
 */
export default {
	name: 'ProjectCopyDialog',

	components: {
		NcButton,
		NcDialog,
		NcLoadingIcon,
		NcTextField,
		ProjectCopyParts,
	},

	props: {
		/** The project to copy. */
		project: {
			type: Object,
			required: true,
		},
	},

	emits: ['close', 'copied'],

	data() {
		return {
			open: true,
			keyTaken: '',
			form: {
				title: this.t('planninq', 'Copy of {title}', { title: this.project.title || '' }),
				key: '',
				startDate: this.project.startDate ? String(this.project.startDate).slice(0, 10) : '',
				parts: defaultCopyParts(),
			},
		}
	},

	computed: {
		/**
		 * @spec exclude Store passthrough — the projects Pinia store.
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
		 * A title is needed, and a key when given must be well formed and free.
		 *
		 * @return {boolean}
		 *
		 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.3
		 */
		isValid() {
			const key = normaliseProjectKey(this.form.key)
			return this.form.title.trim() !== '' && (key === '' || isValidProjectKey(key)) && this.keyTaken !== 'used'
		},

		/**
		 * @spec exclude Display helper — the message under the key field.
		 */
		keyMessage() {
			if (this.keyTaken === 'used') {
				return this.t('planninq', 'This key is already used by another project.')
			}
			const key = normaliseProjectKey(this.form.key)
			if (key !== '' && !isValidProjectKey(key)) {
				return this.t('planninq', 'Use 2 to 10 letters and digits, starting with a letter.')
			}
			return ''
		},
	},

	watch: {
		/**
		 * @spec exclude Watcher glue — forgets a refused key once it changes.
		 */
		'form.key': function() {
			this.keyTaken = ''
		},
	},

	methods: {
		/**
		 * Copy the project and hand the new id to the parent.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.3
		 */
		async submit() {
			if (!this.isValid || this.loading) {
				return
			}
			try {
				const copy = await this.projectsStore.copyProject(this.project.id, copyPayload(this.form))
				this.$emit('copied', copy)
			} catch (error) {
				if (keyRefusal(error?.message) === 'used' || error?.code === 'planninq-project-key-used') {
					this.keyTaken = 'used'
					return
				}
				showError(this.t('planninq', 'The project could not be copied. Nothing was changed.'))
			}
		},
	},
}
</script>

<style scoped>
.project-copy-dialog__form {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 4);
	padding: var(--default-grid-baseline) 0;
}

.project-copy-dialog__field {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
}

.project-copy-dialog__hint {
	color: var(--color-text-maxcontrast);
}
</style>
