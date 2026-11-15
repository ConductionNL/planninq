<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<div class="wiki-tree">
		<ul
			ref="tree"
			role="tree"
			class="wiki-tree__list"
			:aria-label="t('planninq', 'Wiki')"
			data-testid="wiki-tree"
			@keydown="onKeydown">
			<li
				v-for="(row, index) in rows"
				:key="row.id"
				role="treeitem"
				class="wiki-tree__item"
				:class="{ 'wiki-tree__item--current': row.id === currentId }"
				:style="{ paddingInlineStart: `${(row.level - 1) * 16}px` }"
				:aria-level="row.level"
				:aria-expanded="row.hasChildren ? String(row.expanded) : undefined"
				:aria-selected="String(row.id === currentId)"
				:tabindex="index === focusIndex ? 0 : -1"
				:data-testid="`wiki-tree-item-${row.id}`"
				@click="open(row)"
				@focus="focusIndex = index">
				<span class="wiki-tree__toggle" aria-hidden="true" @click.stop="toggle(row)">
					{{ row.hasChildren ? (row.expanded ? '▾' : '▸') : '' }}
				</span>
				<span class="wiki-tree__title">{{ row.title }}</span>
				<NcActions v-if="canWrite" class="wiki-tree__actions" :aria-label="t('planninq', 'Actions for {title}', { title: row.title })">
					<NcActionButton @click="$emit('addSubpage', row.id)">
						{{ t('planninq', 'Add subpage') }}
					</NcActionButton>
					<NcActionButton :disabled="isFirst(row)" @click="$emit('reorder', { id: row.id, direction: -1 })">
						{{ t('planninq', 'Move up') }}
					</NcActionButton>
					<NcActionButton :disabled="isLast(row)" @click="$emit('reorder', { id: row.id, direction: 1 })">
						{{ t('planninq', 'Move down') }}
					</NcActionButton>
					<NcActionButton @click="$emit('move', row.id)">
						{{ t('planninq', 'Move') }}
					</NcActionButton>
				</NcActions>
			</li>
		</ul>
	</div>
</template>

<script>
/**
 * WikiTree.
 *
 * The page tree of a project wiki as an ARIA tree: one tab stop, Up and Down
 * move between visible pages, Right opens a page's subpages or moves into
 * them, Left closes them or moves to the parent, Home and End go to the first
 * and last page, Enter opens the page. The path to the current page is open.
 * For writers each page offers "Add subpage", "Move up", "Move down" and
 * "Move".
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
 */
import { NcActionButton, NcActions } from '@nextcloud/vue'
import { childrenOf, pageId, pathTo, treeKey, visibleRows } from '../utils/wikiTree.js'

export default {
	name: 'WikiTree',

	components: {
		NcActionButton,
		NcActions,
	},

	props: {
		/** The project's pages. */
		pages: {
			type: Array,
			required: true,
		},

		/** The open page, '' for none. */
		currentId: {
			type: String,
			default: '',
		},

		/** Whether the person may write pages. */
		canWrite: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['open', 'addSubpage', 'reorder', 'move'],

	data() {
		return {
			expanded: new Set(),
			focusIndex: 0,
		}
	},

	computed: {
		/**
		 * @return {Array<object>}
		 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
		 */
		rows() {
			return visibleRows(this.pages, this.expanded)
		},
	},

	watch: {
		currentId: {
			immediate: true,
			handler(id) {
				if (!id) {
					return
				}
				const path = pathTo(this.pages, id).slice(0, -1)
				if (path.some((step) => !this.expanded.has(step))) {
					this.expanded = new Set([...this.expanded, ...path])
				}
				const index = this.rows.findIndex((row) => row.id === id)
				if (index >= 0) {
					this.focusIndex = index
				}
			},
		},
	},

	methods: {
		/**
		 * @param {object} row A tree row.
		 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
		 */
		open(row) {
			this.$emit('open', row.id)
		},

		/**
		 * @param {object} row A tree row.
		 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
		 */
		toggle(row) {
			if (!row.hasChildren) {
				return
			}
			const next = new Set(this.expanded)
			if (next.has(row.id)) {
				next.delete(row.id)
			} else {
				next.add(row.id)
			}
			this.expanded = next
		},

		/**
		 * @param {object} row A tree row.
		 * @return {boolean}
		 */
		isFirst(row) {
			return pageId(childrenOf(this.pages, row.parent)[0]) === row.id
		},

		/**
		 * @param {object} row A tree row.
		 * @return {boolean}
		 */
		isLast(row) {
			const siblings = childrenOf(this.pages, row.parent)
			return pageId(siblings[siblings.length - 1]) === row.id
		},

		/**
		 * Move the focus to a row and onto its element.
		 *
		 * @param {number} index The row.
		 */
		focusRow(index) {
			if (index < 0 || index >= this.rows.length) {
				return
			}
			this.focusIndex = index
			this.$nextTick(() => {
				this.$refs.tree?.querySelectorAll('[role="treeitem"]')[index]?.focus()
			})
		},

		/**
		 * The tree's keyboard handling (WAI-ARIA tree view pattern).
		 *
		 * @param {KeyboardEvent} event The key.
		 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-browsing-the-tree-by-keyboard
		 */
		onKeydown(event) {
			if (event.target?.getAttribute?.('role') !== 'treeitem') {
				return
			}
			const row = this.rows[this.focusIndex]
			if (!row) {
				return
			}
			const handled = this.handleKey(event.key)
			if (handled) {
				event.preventDefault()
			}
		},

		/**
		 * Apply what a key does to the tree.
		 *
		 * @param {string} key The key name.
		 * @return {boolean} Whether the key was handled.
		 */
		handleKey(key) {
			const action = treeKey(this.rows, this.focusIndex, key)
			if (action === null) {
				return false
			}
			if (action.toggle !== null) {
				this.toggle(this.rows.find((row) => row.id === action.toggle))
			}
			if (action.focus !== null) {
				this.focusRow(action.focus)
			}
			if (action.open !== null) {
				this.$emit('open', action.open)
			}
			return true
		},
	},
}

</script>

<style scoped>
.wiki-tree__list {
	margin: 0;
	padding: 0;
	list-style: none;
}

.wiki-tree__item {
	display: flex;
	align-items: center;
	gap: 4px;
	min-height: 36px;
	border-radius: var(--border-radius);
	cursor: pointer;
}

.wiki-tree__item:hover {
	background-color: var(--color-background-hover);
}

.wiki-tree__item:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -2px;
}

.wiki-tree__item--current {
	background-color: var(--color-primary-element-light);
	font-weight: 600;
}

.wiki-tree__toggle {
	display: inline-block;
	width: 16px;
	text-align: center;
	color: var(--color-text-maxcontrast);
}

.wiki-tree__title {
	flex: 1;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
</style>
