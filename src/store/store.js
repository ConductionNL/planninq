import { generateUrl } from '@nextcloud/router'
import { useObjectStore } from './modules/object.js'
import { useObjectStore as useConductionObjectStore } from '@conduction/nextcloud-vue'
import { useSettingsStore } from './modules/settings.js'

const PLANIX_REGISTER = 'planix'

export async function initializeStores() {
	const settingsStore = useSettingsStore()
	const objectStore = useObjectStore()

	objectStore.configure({
		baseUrl: generateUrl('/apps/openregister/api/objects'),
		schemaBaseUrl: generateUrl('/apps/openregister/api/schemas'),
	})

	// Register board object types for ProjectBoard.vue
	objectStore.registerObjectType('column', 'column', PLANIX_REGISTER)
	objectStore.registerObjectType('task', 'task', PLANIX_REGISTER)

	// Configure the @conduction/nextcloud-vue objectStore used by the projects store.
	const conductionObjectStore = useConductionObjectStore()
	conductionObjectStore.configure({
		baseUrl: generateUrl('/apps/openregister/api/objects'),
	})

	await settingsStore.fetchSettings()

	return { settingsStore, objectStore }
}

export { useObjectStore, useSettingsStore }
