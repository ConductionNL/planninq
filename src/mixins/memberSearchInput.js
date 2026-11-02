/**
 * The search box of a people or group picker: it waits for a 300 ms pause
 * in typing, asks core autocomplete through searchMembers(), and aborts a
 * search that is overtaken or still running when the component unmounts.
 *
 * The component provides searchFilter(), the searchMembers() options other
 * than the signal, and onSearchError(error), which reports a failed search.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-3.1
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.1
 */
import { MIN_SEARCH_LENGTH, searchMembers } from '../utils/memberSearch.js'

export default {
	data() {
		return {
			query: '',
			results: [],
			loading: false,
			searched: false,
			minLength: MIN_SEARCH_LENGTH,
			debounceTimer: null,
			/** @type {AbortController|null} */
			abortController: null,
		}
	},

	/**
	 * @spec exclude Teardown glue: cancels the pending debounce timer and
	 *   aborts the in-flight request so neither resolves against a destroyed
	 *   component. It adds no behaviour of its own for a scenario to describe.
	 */
	beforeUnmount() {
		clearTimeout(this.debounceTimer)
		this.abortController?.abort()
	},

	methods: {
		/**
		 * Debounce what the person types and search after 300 ms.
		 *
		 * @param {string} value The current search text.
		 *
		 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-3.1
		 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.1
		 */
		onInput(value) {
			this.query = value || ''
			this.searched = false
			clearTimeout(this.debounceTimer)
			this.abortController?.abort()
			if (this.query.trim().length < MIN_SEARCH_LENGTH) {
				this.results = []
				return
			}
			this.debounceTimer = setTimeout(() => this.search(this.query), 300)
		},

		/**
		 * Ask core autocomplete for matches with the component's filter.
		 *
		 * @param {string} term The search text.
		 *
		 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-3.1
		 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.1
		 */
		async search(term) {
			this.abortController = new AbortController()
			this.loading = true
			try {
				this.results = await searchMembers(term, { ...this.searchFilter(), signal: this.abortController.signal })
			} catch (err) {
				if (err.name === 'AbortError') {
					return
				}
				this.onSearchError(err)
				this.results = []
			} finally {
				this.loading = false
				this.searched = true
			}
		},
	},
}
