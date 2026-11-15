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
				data-testid="wiki-page-markdown"
				@paste="onPaste"
				@dragover="onDragOver"
				@drop="onDrop"
				@blur="rememberCursor" />
			<h3 class="wiki-editor__preview-label">
				{{ t('planninq', 'Preview') }}
			</h3>
			<!-- eslint-disable-next-line vue/no-v-html -- sanitised by renderBody -->
			<div class="wiki-editor__preview" data-testid="wiki-page-preview" v-html="preview" />
		</div>

		<div class="wiki-editor__attach">
			<input ref="fileInput"
				type="file"
				multiple
				class="hidden-visually"
				tabindex="-1"
				aria-hidden="true"
				data-testid="wiki-page-file-input"
				@change="onPick">
			<NcButton data-testid="wiki-page-attach" :disabled="attaching" @click="pick">
				{{ t('planninq', 'Attach file') }}
			</NcButton>
		</div>

		<NcNoteCard v-if="attachNotice" type="warning" data-testid="wiki-page-attach-notice">
			{{ attachNotice }}
		</NcNoteCard>

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
 * overwriting it. Files picked, pasted or dropped are stored as files of the
 * page through OpenRegister and linked in the body: an image is shown, any
 * other file is a download link.
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-2.2
 * @spec openspec/changes/projects-wiki/tasks.md#task-4.2
 * @spec openspec/changes/projects-wiki/tasks.md#task-2.3
 */
import { NcButton, NcNoteCard, NcTextArea, NcTextField } from '@nextcloud/vue'
import { lockWikiPage, saveWikiPage, unlockWikiPage, uploadWikiFile } from '../api/wikiPages.js'
import { filesIn } from '../utils/wikiAttachments.js'
import { attachFiles, createEditingSession, insertMarkdown, startTextEditor } from '../utils/wikiEditing.js'
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
			attaching: false,
			attachNotice: '',
			cursor: null,
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
		 * Open the file picker.
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-2.3
		 */
		pick() {
			this.$refs.fileInput?.click()
		},

		/**
		 * Attach the picked files and clear the picker for the next pick.
		 *
		 * @param {Event} event The picker's change.
		 * @spec openspec/changes/projects-wiki/tasks.md#task-2.3
		 */
		async onPick(event) {
			const files = filesIn(event?.target)
			if (event?.target) {
				event.target.value = ''
			}
			await this.attach(files)
		},

		/**
		 * Attach the files of a paste; a paste of text is left to the field.
		 *
		 * @param {ClipboardEvent} event The paste.
		 * @spec openspec/changes/projects-wiki/tasks.md#task-2.3
		 */
		onPaste(event) {
			const files = filesIn(event?.clipboardData)
			if (files.length === 0) {
				return
			}
			event.preventDefault()
			this.rememberCursor(event)
			return this.attach(files)
		},

		/**
		 * Let files be dropped on the field.
		 *
		 * @param {DragEvent} event The drag.
		 * @spec openspec/changes/projects-wiki/tasks.md#task-2.3
		 */
		onDragOver(event) {
			if (Array.from(event?.dataTransfer?.types ?? []).includes('Files')) {
				event.preventDefault()
			}
		},

		/**
		 * Attach the dropped files.
		 *
		 * @param {DragEvent} event The drop.
		 * @spec openspec/changes/projects-wiki/tasks.md#task-2.3
		 */
		onDrop(event) {
			const files = filesIn(event?.dataTransfer)
			if (files.length === 0) {
				return
			}
			event.preventDefault()
			this.rememberCursor(event)
			return this.attach(files)
		},

		/**
		 * Keep where the cursor was in the Markdown field, for the insert.
		 *
		 * @param {Event} event An event of the field.
		 */
		rememberCursor(event) {
			const at = event?.target?.selectionStart
			this.cursor = Number.isInteger(at) ? at : null
		},

		/**
		 * Store the files on the page and link each one in the body. A new
		 * page has nowhere to keep files yet, so it is saved first.
		 *
		 * @param {File[]} files The files.
		 * @spec openspec/changes/projects-wiki/tasks.md#task-2.3
		 */
		async attach(files) {
			if (files.length === 0 || this.attaching) {
				return
			}
			this.attaching = true
			this.attachNotice = ''
			const result = await attachFiles(this.page.id, files, uploadWikiFile, (markdown) => {
				const before = this.body
				this.body = insertMarkdown(this.editor, before, this.cursor, markdown)
				if (Number.isInteger(this.cursor)) {
					this.cursor += this.body.length - before.length
				}
			})
			this.attaching = false
			if (result.unsaved) {
				this.attachNotice = this.t('planninq', 'Save the page first, then attach files.')
			} else if (result.failed > 0) {
				this.attachNotice = this.t('planninq', 'The file could not be uploaded.')
			}
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

.wiki-editor__attach {
	display: flex;
	gap: 8px;
}

.wiki-editor__actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
}
</style>
