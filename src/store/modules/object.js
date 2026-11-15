/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
import { defineStore } from 'pinia'

/**
 * The objects of a collection answer: its `results`, or the answer itself.
 *
 * @param {object|Array} data The parsed answer.
 * @return {Array|object}
 *
 * @spec openspec/changes/retrofit-2026-05-25-app-shell-and-data-store/tasks.md#task-2
 */
function resultsOf(data) {
	return data.results || data
}

/**
 * Generic OpenRegister object store.
 * Configure it with baseUrl and schemaBaseUrl, then register object types.
 */
export const useObjectStore = defineStore('object', {
	state: () => ({
		baseUrl: '',
		schemaBaseUrl: '',
		objectTypes: {},
		objects: {},
		loading: {},
	}),

	actions: {
		/**
		 * Set the OpenRegister objects and schemas base URLs on the store.
		 *
		 * @param {object} options              Configuration options
		 * @param {string} options.baseUrl       OpenRegister objects endpoint
		 * @param {string} options.schemaBaseUrl OpenRegister schemas endpoint
		 *
		 * @spec openspec/changes/retrofit-2026-05-25-app-shell-and-data-store/tasks.md#task-2
		 */
		configure({ baseUrl, schemaBaseUrl }) {
			this.baseUrl = baseUrl
			this.schemaBaseUrl = schemaBaseUrl
		},

		/**
		 * Register a schema/register pair under a type key, initialising an
		 * empty objects array for the type if none exists.
		 *
		 * @param {string} type     Local type key
		 * @param {string} schema   OpenRegister schema slug
		 * @param {string} register OpenRegister register slug
		 *
		 * @spec openspec/changes/retrofit-2026-05-25-app-shell-and-data-store/tasks.md#task-2
		 */
		registerObjectType(type, schema, register) {
			this.objectTypes[type] = { schema, register }
			if (!this.objects[type]) {
				this.objects[type] = []
			}
		},

		/**
		 * The collection URL of a register and schema, with extra query parameters.
		 *
		 * @param {{register: string, schema: string}} source The register and schema.
		 * @param {object} params Extra query parameters
		 * @return {string}
		 *
		 * @spec openspec/changes/retrofit-2026-05-25-app-shell-and-data-store/tasks.md#task-2
		 */
		_collectionUrl(source, params) {
			const url = new URL(this.baseUrl, window.location.origin)
			url.searchParams.set('register', source.register)
			url.searchParams.set('schema', source.schema)
			Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v))
			return url.toString()
		},

		/**
		 * Fetch a typed object collection from OpenRegister. Returns an empty
		 * array for unregistered types or on error; toggles the per-type
		 * loading flag around the request.
		 *
		 * @param {string} type   Registered type key
		 * @param {object} params Extra query parameters
		 * @return {Promise<Array>}
		 *
		 * @spec openspec/changes/retrofit-2026-05-25-app-shell-and-data-store/tasks.md#task-2
		 */
		async fetchObjects(type, params = {}) {
			if (!this.objectTypes[type]) {
				console.warn(`Object type "${type}" is not registered`)
				return []
			}

			this.loading[type] = true
			const { schema, register } = this.objectTypes[type]

			try {
				const url = this._collectionUrl({ register, schema }, params)
				const response = await fetch(url, {
					headers: { requesttoken: OC.requestToken },
				})
				if (response.ok) {
					this.objects[type] = resultsOf(await response.json())
					return this.objects[type]
				}
			} catch (error) {
				console.error(`Failed to fetch ${type} objects:`, error)
			} finally {
				this.loading[type] = false
			}
			return []
		},
	},
})
