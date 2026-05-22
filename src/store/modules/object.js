import { defineStore } from 'pinia'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Generic OpenRegister object store.
 * Configure it with baseUrl and schemaBaseUrl, then register object types.
 *
 * @spec openspec/changes/task-quick-add/tasks.md#task-4
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
		configure({ baseUrl, schemaBaseUrl }) {
			this.baseUrl = baseUrl
			this.schemaBaseUrl = schemaBaseUrl
		},

		registerObjectType(type, schema, register) {
			this.objectTypes[type] = { schema, register }
			if (!this.objects[type]) {
				this.objects[type] = []
			}
		},

		async fetchObjects(type, params = {}) {
			if (!this.objectTypes[type]) {
				console.warn(`Object type "${type}" is not registered`)
				return []
			}

			this.loading[type] = true
			const { schema, register } = this.objectTypes[type]

			try {
				const url = new URL(this.baseUrl, window.location.origin)
				url.searchParams.set('register', register)
				url.searchParams.set('schema', schema)
				Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v))

				const response = await fetch(url.toString(), {
					headers: { requesttoken: OC.requestToken },
				})
				if (response.ok) {
					const data = await response.json()
					this.objects[type] = data.results || data
					return this.objects[type]
				}
			} catch (error) {
				console.error(`Failed to fetch ${type} objects:`, error)
			} finally {
				this.loading[type] = false
			}
			return []
		},

		/**
		 * Create a new object of the given type via OpenRegister.
		 *
		 * @param {string} type   Registered object type (e.g. 'task')
		 * @param {object} object Object properties to save
		 * @return {Promise<object|null>} Created object or null on failure
		 *
		 * @spec openspec/changes/task-quick-add/tasks.md#task-4
		 */
		async createObject(type, object) {
			if (!this.objectTypes[type]) {
				throw new Error(`Object type "${type}" is not registered`)
			}

			const { schema, register } = this.objectTypes[type]
			const url = generateUrl('/apps/openregister/api/objects')

			const response = await axios.post(url, { register, schema, object })
			const created = response.data

			if (!this.objects[type]) {
				this.objects[type] = []
			}
			this.objects[type].push(created)
			return created
		},
	},
})
