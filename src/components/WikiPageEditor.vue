<template>
	<div class="wiki-page-editor" data-testid="wiki-editor">
		<div v-if="useText"
			ref="host"
			class="wiki-page-editor__text"
			data-testid="wiki-editor-text" />
		<template v-else>
			<label class="wiki-page-editor__label" for="wiki-body">{{ t('planninq', 'Content (Markdown)') }}</label>
			<textarea
				id="wiki-body"
				:value="modelValue"
				class="wiki-page-editor__field"
				rows="16"
				data-testid="wiki-editor-markdown"
				@input="$emit('update:modelValue', $event.target.value)" />
			<h3 class="wiki-page-editor__preview-title">
				{{ t('planninq', 'Preview') }}
			</h3>
			<NcRichText :text="modelValue" :useMarkdown="true" class="wiki-page-editor__preview" />
		</template>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcRichText } from '@nextcloud/vue'
import { openTextEditor, textEditorAvailable } from '../utils/wiki.js'

/**
 * WikiPageEditor: edits a page body with the Nextcloud Text app's editor, or a
 * Markdown field with a preview when that editor is not available.
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-2.2
 */
export default {
	name: 'WikiPageEditor',

	components: { NcRichText },

	props: {
		/** The Markdown body (v-model). */
		modelValue: {
			type: String,
			default: '',
		},
	},

	emits: ['update:modelValue'],

	data() {
		return {
			useText: textEditorAvailable(window),
			editor: null,
		}
	},

	async mounted() {
		if (!this.useText) {
			return
		}
		await this.$nextTick()
		this.editor = await openTextEditor(window, {
			el: this.$refs.host,
			content: this.modelValue,
			readOnly: false,
			onUpdate: (markdown) => this.$emit('update:modelValue', markdown),
		})
		// The editor refused to start: fall back to the Markdown field.
		if (!this.editor) {
			this.useText = false
		}
	},

	beforeUnmount() {
		this.editor?.destroy()
	},

	methods: { t },
}
</script>

<style scoped>
.wiki-page-editor__label,
.wiki-page-editor__preview-title {
	display: block;
	font-weight: 600;
	margin-block: calc(var(--default-grid-baseline) * 2);
}

.wiki-page-editor__field {
	width: 100%;
	font-family: var(--font-face-monospace, monospace);
}

.wiki-page-editor__preview {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: calc(var(--default-grid-baseline) * 3);
}

.wiki-page-editor__text {
	min-height: 320px;
}
</style>
