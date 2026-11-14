<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<NcDialog
		:name="t('planninq', 'Move {title}', { title: pageTitle })"
		data-testid="wiki-move-dialog"
		@closing="$emit('close')">
		<NcSelect
			v-model="target"
			:inputLabel="t('planninq', 'New parent')"
			:options="options"
			:clearable="false"
			data-testid="wiki-move-target" />
		<template #actions>
			<NcButton @click="$emit('close')">
				{{ t('planninq', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				data-testid="wiki-move-confirm"
				:disabled="target === null"
				@click="confirm">
				{{ t('planninq', 'Move') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
/**
 * WikiPageMoveDialog.
 *
 * Picks a new parent for a wiki page: the top level or any page of the
 * project except the page itself and its subpages, which the parent guard
 * would refuse. Each option reads as its breadcrumb, so pages with the same
 * title can be told apart.
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-3.2
 */
import { NcButton, NcDialog, NcSelect } from '@nextcloud/vue'
import { breadcrumb, moveTargets, nextOrder, pageId, parentOf } from '../utils/wikiTree.js'

export default {
	name: 'WikiPageMoveDialog',

	components: {
		NcButton,
		NcDialog,
		NcSelect,
	},

	props: {
		/** The project's pages. */
		pages: {
			type: Array,
			required: true,
		},

		/** The page to move. */
		pageId: {
			type: String,
			required: true,
		},
	},

	emits: ['close', 'move'],

	data() {
		return {
			target: null,
		}
	},

	computed: {
		/** @return {string} */
		pageTitle() {
			return String(this.pages.find((page) => pageId(page) === this.pageId)?.title || '')
		},

		/**
		 * The top level and every page the guard allows, except the current parent.
		 *
		 * @return {Array<{id: string, label: string}>}
		 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-a-page-cannot-move-under-its-own-subpage
		 */
		options() {
			const current = parentOf(this.pages.find((page) => pageId(page) === this.pageId))
			const top = { id: '', label: this.t('planninq', 'Top level') }
			const pages = moveTargets(this.pages, this.pageId).map((page) => ({
				id: pageId(page),
				label: breadcrumb(this.pages, pageId(page)),
			}))
			return [top, ...pages.sort((a, b) => a.label.localeCompare(b.label))].filter((option) => option.id !== current)
		},
	},

	methods: {
		/**
		 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-moving-a-page-under-another-page
		 */
		confirm() {
			if (this.target === null) {
				return
			}
			const parent = this.target.id
			const others = this.pages.filter((page) => pageId(page) !== this.pageId)
			this.$emit('move', { id: this.pageId, parent: parent === '' ? null : parent, order: nextOrder(others, parent) })
		},
	},
}
</script>
