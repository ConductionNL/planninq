<template>
	<div class="quick-add-task">
		<!-- Collapsed state: trigger button -->
		<div v-if="!active" class="quick-add-task__trigger">
			<NcButton
				ref="addButton"
				type="tertiary"
				:aria-label="t('planix', 'Add task')"
				@click="activate">
				<template #icon>
					<PlusIcon :size="16" />
				</template>
				{{ t('planix', 'Add task') }}
			</NcButton>
		</div>

		<!-- Expanded state: inline form -->
		<div
			v-else
			class="quick-add-task__form"
			role="form"
			:aria-label="t('planix', 'Quick add task')">
			<label :for="inputId" class="sr-only">{{ t('planix', 'Task title') }}</label>
			<textarea
				:id="inputId"
				ref="inputRef"
				v-model="draft"
				class="quick-add-task__input"
				:placeholder="t('planix', 'Task title — press Enter to save, Escape to cancel')"
				:disabled="saving"
				rows="2"
				@keydown.enter.prevent="handleEnter"
				@keydown.esc="cancel" />
			<span
				v-if="errorMessage"
				role="alert"
				class="quick-add-task__error">
				{{ errorMessage }}
			</span>
			<div class="quick-add-task__actions">
				<NcButton
					type="primary"
					:disabled="saving || !draft.trim()"
					@click="submit">
					<template v-if="saving" #icon>
						<NcLoadingIcon :size="16" />
					</template>
					{{ saving ? t('planix', 'Saving…') : t('planix', 'Save') }}
				</NcButton>
				<NcButton
					type="tertiary"
					:disabled="saving"
					@click="cancel">
					{{ t('planix', 'Cancel') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import { useObjectStore } from '../store/modules/object.js'

/**
 * QuickAddTask — inline task creation at the footer of a kanban column.
 *
 * @spec openspec/changes/task-quick-add/tasks.md#task-2
 * @spec openspec/changes/task-quick-add/tasks.md#task-3
 * @spec openspec/changes/task-quick-add/tasks.md#task-4
 * @spec openspec/changes/task-quick-add/tasks.md#task-5
 * @spec openspec/changes/task-quick-add/tasks.md#task-6
 */
export default {
	name: 'QuickAddTask',

	components: {
		NcButton,
		NcLoadingIcon,
		PlusIcon,
	},

	props: {
		columnId: {
			type: String,
			required: true,
		},
		projectId: {
			type: String,
			required: true,
		},
	},

	emits: ['task-created', 'error'],

	data() {
		return {
			active: false,
			draft: '',
			saving: false,
			errorMessage: '',
		}
	},

	computed: {
		objectStore() {
			return useObjectStore()
		},
		inputId() {
			return `quick-add-task-input-${this.columnId}`
		},
	},

	methods: {
		/**
		 * Expand the inline form and focus the textarea.
		 *
		 * @spec openspec/changes/task-quick-add/tasks.md#task-2
		 */
		activate() {
			this.active = true
			this.errorMessage = ''
			this.$nextTick(() => {
				this.$refs.inputRef?.focus()
			})
		},

		/**
		 * Collapse the form, discard the draft, and return focus to the trigger.
		 *
		 * @spec openspec/changes/task-quick-add/tasks.md#task-2
		 */
		cancel() {
			this.active = false
			this.draft = ''
			this.errorMessage = ''
			this.saving = false
			this.$nextTick(() => {
				this.$refs.addButton?.$el?.focus()
			})
		},

		/**
		 * Handle Enter keydown: ignore Shift+Enter, ignore empty draft, otherwise submit.
		 *
		 * @param {KeyboardEvent} event
		 * @spec openspec/changes/task-quick-add/tasks.md#task-3
		 */
		handleEnter(event) {
			if (event.shiftKey) return
			if (!this.draft.trim() || this.saving) return
			this.submit()
		},

		/**
		 * POST the new task to OpenRegister, emit task-created on success.
		 *
		 * @spec openspec/changes/task-quick-add/tasks.md#task-4
		 * @spec openspec/changes/task-quick-add/tasks.md#task-5
		 */
		async submit() {
			if (!this.draft.trim() || this.saving) return

			this.saving = true
			this.errorMessage = ''

			try {
				const task = await this.objectStore.createObject('task', {
					title: this.draft.trim(),
					column: this.columnId,
					project: this.projectId,
				})

				this.$emit('task-created', { task })
				this.cancel()
			} catch (err) {
				this.errorMessage = t('planix', 'Failed to create task. Please try again.')
				this.$emit('error', { message: this.errorMessage })
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.quick-add-task {
	padding: 4px 0;
}

.quick-add-task__form {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.quick-add-task__input {
	width: 100%;
	resize: vertical;
	box-sizing: border-box;
	padding: 6px 8px;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 13px;
	font-family: inherit;
}

.quick-add-task__input:focus {
	outline: none;
	border-color: var(--color-primary-element);
	box-shadow: 0 0 0 2px var(--color-primary-element-light);
}

.quick-add-task__input:disabled {
	opacity: 0.6;
	cursor: not-allowed;
}

.quick-add-task__error {
	font-size: 12px;
	color: var(--color-error);
}

.quick-add-task__actions {
	display: flex;
	gap: 4px;
}

/* Visually hide the label while keeping it accessible */
.sr-only {
	position: absolute;
	width: 1px;
	height: 1px;
	padding: 0;
	margin: -1px;
	overflow: hidden;
	clip: rect(0, 0, 0, 0);
	white-space: nowrap;
	border: 0;
}
</style>
