<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<div class="case-link-field" :data-testid="testid">
		<NcSelect
			v-if="editable"
			:modelValue="selected"
			:options="options"
			:inputLabel="t('planninq', 'Case')"
			:placeholder="t('planninq', 'Search cases')"
			:filterable="false"
			:loading="loading"
			label="label"
			:data-testid="`${testid}-select`"
			@search="onSearch"
			@update:modelValue="onPick" />
		<template v-else>
			<span class="case-link-field__label">{{ t('planninq', 'Case') }}</span>
			<CaseBadge v-if="modelValue" :caseId="modelValue" />
			<span v-else class="case-link-field__empty">{{ t('planninq', 'No case linked') }}</span>
		</template>
		<a
			v-if="editable && modelValue"
			class="case-link-field__open"
			:href="href"
			:data-testid="`${testid}-link`">
			{{ t('planninq', 'Open {name} in the case app', { name: selected ? selected.label : modelValue }) }}
		</a>
	</div>
</template>

<script>
/**
 * CaseLinkField.
 *
 * Picks, changes and clears the case a project or task is linked to. The
 * field searches the case app's cases as the person types; a person who may
 * not change the link sees it as a link without an edit control.
 *
 * @spec openspec/changes/case-link-editing/tasks.md#task-1.1
 * @spec openspec/changes/case-link-editing/tasks.md#task-2.1
 */
import { NcSelect } from '@nextcloud/vue'
import CaseBadge from './CaseBadge.vue'
import { caseTitle, caseUrl, caseValue, MIN_SEARCH_LENGTH, searchCases } from '../utils/caseLink.js'

export default {
	name: 'CaseLinkField',

	components: { CaseBadge, NcSelect },

	props: {
		modelValue: {
			type: String,
			default: null,
		},

		editable: {
			type: Boolean,
			default: false,
		},

		testid: {
			type: String,
			default: 'case-link',
		},
	},

	emits: ['update:modelValue'],

	data() {
		return {
			options: [],
			loading: false,
			title: null,
			controller: null,
		}
	},

	computed: {
		/**
		 * @spec exclude Trivial getter: the linked case as a select option.
		 */
		selected() {
			return this.modelValue ? { id: this.modelValue, label: this.title || this.modelValue } : null
		},

		/**
		 * @spec exclude Trivial getter: the linked case's address in the case app.
		 */
		href() {
			return caseUrl(this.modelValue)
		},
	},

	watch: {
		modelValue: {
			immediate: true,
			/**
			 * @spec exclude Lifecycle glue: reads the name of the linked case.
			 */
			handler() {
				this.readTitle()
			},
		},
	},

	/**
	 * @spec exclude Lifecycle glue: stops a search still running.
	 */
	beforeUnmount() {
		this.controller?.abort()
	},

	methods: {
		/**
		 * Read the linked case's name; a newer link wins over a slower read.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/case-link-editing/tasks.md#task-1.1
		 */
		async readTitle() {
			const id = this.modelValue
			this.title = null
			if (!id) {
				return
			}
			const title = await caseTitle(id)
			if (this.modelValue === id) {
				this.title = title
			}
		},

		/**
		 * Search the cases for what the person typed. A newer search aborts
		 * the one before it; a failed search offers nothing.
		 *
		 * @param {string} term What the person typed.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/case-link-editing/tasks.md#task-1.1
		 */
		async onSearch(term) {
			this.controller?.abort()
			if (String(term || '').trim().length < MIN_SEARCH_LENGTH) {
				this.controller = null
				this.options = []
				this.loading = false
				return
			}
			const controller = new AbortController()
			this.controller = controller
			this.loading = true
			try {
				this.options = await searchCases(term, { signal: controller.signal })
			} catch (err) {
				if (err?.name !== 'AbortError' && err?.name !== 'CanceledError') {
					console.error('CaseLinkField: search failed', err)
					this.options = []
				}
			}
			if (this.controller === controller) {
				this.controller = null
				this.loading = false
			}
		},

		/**
		 * Take the picked case, or none when the field is cleared.
		 *
		 * @param {{id: string, label: string}|null} option The picked case.
		 *
		 * @spec openspec/changes/case-link-editing/tasks.md#task-1.1
		 */
		onPick(option) {
			if (option?.label) {
				this.title = option.label
			}
			this.$emit('update:modelValue', caseValue(option))
		},
	},
}
</script>

<style scoped>
.case-link-field {
	display: flex;
	flex-direction: column;
	gap: 4px;
	align-items: flex-start;
}

.case-link-field__label {
	font-weight: bold;
}

.case-link-field__empty {
	color: var(--color-text-maxcontrast);
}

.case-link-field__open {
	color: var(--color-primary-element);
	text-decoration: underline;
}
</style>
