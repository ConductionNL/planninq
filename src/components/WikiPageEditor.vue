<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<form class="wiki-editor" data-testid="wiki-page-editor" @submit.prevent="save">
		<NcTextField
			v-model="title"
			:label="t('planninq', 'Title')"
			data-testid="wiki-page-title"
			required />

		<div v-show="mode === 'text'"
			ref="textHost"
			class="wiki-editor__text"
			data-testid="wiki-page-text-editor" />

		<div v-if="mode === 'markdown'" class="wiki-editor__markdown">
			<NcTextArea
				v-model="body"
				:label="t('planninq', 'Text')"
				resize="vertical"
				rows="14"
				data-testid="wiki-page-markdown" />
			<h3 class="wiki-editor__preview-label">
				{{ t('planninq', 'Preview') }}
			</h3>
			<!-- eslint-disable-next-line vue/no-v-html -- sanitised by renderBody -->
			<div class="wiki-editor__preview" data-testid="wiki-page-preview" v-html="preview" />
		</div>

		<NcNoteCard v-if="error" type="error" data-testid="wiki-page-error">
			{{ error }}
		</NcNoteCard>

		<div class="wiki-editor__actions">
			<NcButton data-testid="wiki-page-cancel" @click="cancel">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
			<NcButton
				type="submit"
				variant="primary"
				data-testid="wiki-page-save"
				:disabled="busy || title.trim() === ''">
				{{ t('planninq', 'Save') }}
			</NcButton>
		</div>
	</form>
</template>

<script>
/**
 * WikiPageEditor.
 *
 * Edits a wiki page's title and Markdown body. The body uses the Text app's
 * editor (`window.OCA.Text.createEditor`, which edits Markdown that is not a
 * file) when the Text app offers it, and otherwise a Markdown field with a
 * preview. Opening an existing page takes OpenRegister's lock on it; saving,
 * cancelling and closing release it. A save sends the `updated` time the
 * writer opened, so a save after someone else's is refused instead of
 * overwriting it.
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-2.2
 * @spec openspec/changes/projects-wiki/tasks.md#task-4.2
 */
import { NcButton, NcNoteCard, NcTextArea, NcTextField } from '@nextcloud/vue'
import { lockWikiPage, saveWikiPage, unlockWikiPage } from '../api/wikiPages.js'
import { createEditingSession, startTextEditor } from '../utils/wikiEditing.js'
import { renderBody, textEditorFactory } from '../utils/wikiTree.js'

export default {
	name: 'WikiPageEditor',

	components: {
		NcButton,
		NcNoteCard,
		NcTextArea,
		NcTextField,
	},

	props: {
		/** The page to edit, or the fields of a new one (`project`, `parent`, `order`). */
		page: {
			type: Object,
			required: true,
		},
	},

	emits: ['saved', 'cancel', 'locked'],

	data() {
		return {
			title: String(this.page.title || ''),
			body: String(this.page.body || ''),
			mode: textEditorFactory() ? 'text' : 'markdown',
			editor: null,
			busy: false,
			error: '',
		}
	},

	computed: {
		/**
		 * @return {string}
		 * @spec openspec/changes/projects-wiki/tasks.md#task-2.2
		 */
		preview() {
			return renderBody(this.body)
		},

	},

	created() {
		this.session = createEditingSession(this.page, { lock: lockWikiPage, unlock: unlockWikiPage, save: saveWikiPage })
	},

	async mounted() {
		const opened = await this.session.open()
		if (!opened.ok) {
			this.$emit('locked', opened.refusal)
			return
		}
		this.editor = await startTextEditor(textEditorFactory(), this.$refs.textHost, this.body, (markdown) => {
			this.body = markdown
		})
		if (this.editor === null) {
			this.mode = 'markdown'
		}
	},

	beforeUnmount() {
		this.session.release()
		this.editor?.destroy?.()
	},

	methods: {
		/**
		 * Save the page; on success the session releases the lock and the
		 * saved page goes up.
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-4.2
		 */
		async save() {
			if (this.busy || this.title.trim() === '') {
				return
			}
			this.busy = true
			this.error = ''
			const result = await this.session.save({ title: this.title.trim(), body: this.body })
			this.busy = false
			if (result.ok) {
				this.$emit('saved', result.saved)
				return
			}
			this.error = result.conflict
				? this.t('planninq', 'Someone saved this page after you opened it. Reload it and add your changes again.')
				: this.t('planninq', 'Please try again later.')
		},

		/**
		 * @spec openspec/changes/projects-wiki/tasks.md#task-4.2
		 */
		cancel() {
			this.session.cancel()
			this.$emit('cancel')
		},
	},
}
</script>

<style scoped>
.wiki-editor {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.wiki-editor__text {
	min-height: 320px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.wiki-editor__preview-label {
	margin: 8px 0 0;
	font-size: 1em;
	color: var(--color-text-maxcontrast);
}

.wiki-editor__preview {
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.wiki-editor__actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
}
</style>
