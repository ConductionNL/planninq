/**
 * Load once the component is mounted, and again whenever one watched value
 * changes. The component provides load().
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec exclude Shared load frame; each component's spec covers what it loads.
 */

/**
 * The mixin that loads on mount and reloads when `key` changes.
 *
 * @param {string} key - the watched property or path, such as 'projectId' or '$route.params.id'
 * @return {object} the mixin
 *
 * @spec exclude Shared load frame; each component's spec covers what it loads.
 */
export default function reloadOn(key) {
	return {
		watch: {
			/**
			 * @spec exclude Shared load frame; each component's spec covers what it loads.
			 */
			[key]() {
				this.load()
			},
		},

		/**
		 * @spec exclude Shared load frame; each component's spec covers what it loads.
		 */
		mounted() {
			this.load()
		},
	}
}
