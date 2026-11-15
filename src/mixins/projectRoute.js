/**
 * The project a project page shows: the id in the address (/projects/:id),
 * and a fresh load() whenever that id changes, including the first time.
 *
 * The component provides load().
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec exclude Shared route frame of the project pages; each page's spec covers what it loads.
 */
export default {
	computed: {
		/**
		 * @return {string}
		 *
		 * @spec exclude Shared route frame of the project pages; each page's spec covers what it loads.
		 */
		projectId() {
			return String(this.$route.params.id || '')
		},
	},

	watch: {
		projectId: {
			immediate: true,
			/**
			 * @spec exclude Shared route frame of the project pages; each page's spec covers what it loads.
			 */
			handler() {
				this.load()
			},
		},
	},
}
