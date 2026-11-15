<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->
<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<a
		v-if="caseId"
		class="case-badge"
		:href="href"
		data-testid="case-badge"
		@click.stop>
		{{ t('planninq', 'Case: {name}', { name: name }) }}
	</a>
</template>

<script>
/**
 * CaseBadge.
 *
 * The case a project or task is linked to, as a badge that opens the case in
 * the case app. Shows the case's name once it is read, its id until then or
 * when the case cannot be read.
 *
 * @spec openspec/changes/case-link-editing/tasks.md#task-1.2
 */
import { caseTitle, caseUrl } from '../utils/caseLink.js'

export default {
	name: 'CaseBadge',

	props: {
		caseId: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			title: null,
		}
	},

	computed: {
		/**
		 * @spec exclude Trivial getter: the case's name, or its id until it is read.
		 */
		name() {
			return this.title || this.caseId
		},

		/**
		 * @spec exclude Trivial getter: the case's address in the case app.
		 */
		href() {
			return caseUrl(this.caseId)
		},
	},

	watch: {
		caseId: {
			immediate: true,
			/**
			 * @spec exclude Lifecycle glue: reads the name of the linked case.
			 */
			handler() {
				this.readTitle()
			},
		},
	},

	methods: {
		/**
		 * Read the linked case's name; a newer link wins over a slower read.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/case-link-editing/tasks.md#task-1.2
		 */
		async readTitle() {
			const id = this.caseId
			this.title = null
			if (!id) {
				return
			}
			const title = await caseTitle(id)
			if (this.caseId === id) {
				this.title = title
			}
		},
	},
}
</script>

<style scoped>
.case-badge {
	display: inline-block;
	padding: 2px 8px;
	border-radius: var(--border-radius-pill);
	background-color: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
	font-size: 0.9em;
	text-decoration: none;
	white-space: nowrap;
}

.case-badge:hover,
.case-badge:focus-visible {
	text-decoration: underline;
}
</style>
