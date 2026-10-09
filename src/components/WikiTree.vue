<template>
	<ul
		v-if="items.length"
		class="wiki-tree"
		role="tree"
		:aria-label="t('planninq', 'Wiki pages')"
		data-testid="wiki-tree">
		<li
			v-for="item in items"
			:key="item.id"
			:ref="(el) => setItemRef(item.id, el)"
			class="wiki-tree__item"
			:class="{ 'wiki-tree__item--selected': item.id === selectedId }"
			role="treeitem"
			:aria-level="item.level"
			:aria-expanded="item.hasChildren ? String(item.expanded) : undefined"
			:aria-selected="item.id === selectedId"
			:tabindex="item.id === tabStop ? 0 : -1"
			:style="{ paddingInlineStart: `${(item.level - 1) * 16 + 8}px` }"
			:data-testid="'wiki-tree-item-' + item.id"
			@focus="focused = item.id"
			@keydown="onKeydown"
			@click="onClick(item)">
			<span class="wiki-tree__title">{{ titles[item.id] }}</span>
		</li>
	</ul>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { buildTree, pageId, treeKeyAction, visibleItems } from '../utils/wiki.js'

/**
 * WikiTree: the project's wiki pages as an ARIA tree. Arrow keys move, Right
 * and Left open and close, Enter opens the page.
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
 */
export default {
	name: 'WikiTree',

	props: {
		/** The pages to show, flat; the tree is built from `parent` and `order`. */
		pages: {
			type: Array,
			default: () => [],
		},

		/** Id of the open page. */
		selectedId: {
			type: String,
			default: '',
		},

		/** Ids of the pages whose subpages are shown. */
		openIds: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['select', 'update:openIds'],

	data() {
		return {
			focused: '',
			refs: {},
		}
	},

	computed: {
		/**
		 * @spec exclude Display helper — the tree built from the flat pages.
		 */
		tree() {
			return buildTree(this.pages)
		},

		/**
		 * @spec exclude Display helper — the items in the order they are shown.
		 */
		items() {
			return visibleItems(this.tree, new Set(this.openIds))
		},

		/**
		 * @spec exclude Display helper — page id to title.
		 */
		titles() {
			return Object.fromEntries(this.pages.map((page) => [pageId(page), String(page.title || this.t('planninq', 'Untitled page'))]))
		},

		/**
		 * The one item the Tab key reaches: the focused one, else the open page, else the first.
		 *
		 * @return {string}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
		 */
		tabStop() {
			const ids = this.items.map((item) => item.id)
			return [this.focused, this.selectedId].find((id) => ids.includes(id)) || ids[0] || ''
		},
	},

	methods: {
		t,

		/**
		 * @param {string} id The item id.
		 * @param {HTMLElement|null} element Its element.
		 * @spec exclude Template ref glue — remembers the element so focus can move to it.
		 */
		setItemRef(id, element) {
			if (element) {
				this.refs[id] = element
			}
		},

		/**
		 * Open or close a page's subpages.
		 *
		 * @param {string} id The page.
		 * @param {boolean} open Whether to open it.
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
		 */
		setOpen(id, open) {
			const ids = new Set(this.openIds)
			if (open) {
				ids.add(id)
			} else {
				ids.delete(id)
			}
			this.$emit('update:openIds', [...ids])
		},

		/**
		 * Act on a key press by the ARIA tree pattern.
		 *
		 * @param {KeyboardEvent} event The key event.
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
		 */
		onKeydown(event) {
			const action = treeKeyAction(this.items, this.focused, event.key)
			if (!action) {
				return
			}
			event.preventDefault()
			if (action.expand) {
				this.setOpen(action.expand, true)
			}
			if (action.collapse) {
				this.setOpen(action.collapse, false)
			}
			if (action.focus) {
				this.focused = action.focus
				this.$nextTick(() => this.refs[action.focus]?.focus())
			}
			if (action.open) {
				this.$emit('select', action.open)
			}
		},

		/**
		 * A click opens the page and toggles its subpages.
		 *
		 * @param {{id: string, hasChildren: boolean, expanded: boolean}} item The item.
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
		 */
		onClick(item) {
			this.focused = item.id
			if (item.hasChildren) {
				this.setOpen(item.id, !item.expanded)
			}
			this.$emit('select', item.id)
		},
	},
}
</script>

<style scoped>
.wiki-tree {
	list-style: none;
	margin: 0;
	padding: 0;
}

.wiki-tree__item {
	padding-block: 6px;
	padding-inline-end: 8px;
	border-radius: var(--border-radius-large);
	cursor: pointer;
}

.wiki-tree__item:hover {
	background-color: var(--color-background-hover);
}

.wiki-tree__item:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -2px;
}

.wiki-tree__item--selected {
	background-color: var(--color-primary-element-light);
	font-weight: 600;
}

.wiki-tree__item[aria-expanded]::before {
	content: '\25B8';
	display: inline-block;
	width: 1em;
	margin-inline-end: 4px;
	color: var(--color-text-maxcontrast);
}

.wiki-tree__item[aria-expanded='true']::before {
	content: '\25BE';
}
</style>
