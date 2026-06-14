import { defineConfig } from 'vitest/config'

/**
 * Vitest configuration for planix frontend unit tests.
 *
 * Tests live in tests/vitest/ and cover the pure derived-state helpers
 * (utils/taskHelpers.js) — no DOM/component mount required, so the default
 * node environment is used.
 */
export default defineConfig({
	test: {
		environment: 'node',
		include: ['tests/vitest/**/*.spec.js'],
	},
})
