const {
	defineConfig,
} = require('@eslint/config-helpers')

const js = require('@eslint/js')

const {
	FlatCompat,
} = require('@eslint/eslintrc')

// Shared Vue 3 correction layer from @conduction/nextcloud-vue.
//
// `@nextcloud/eslint-config@8` (pulled in via FlatCompat below) resolves
// eslint-plugin-vue's **Vue 2** preset. That is not merely stale: two of its
// rules are INVERTED under Vue 3, and NONE of the `vue/no-deprecated-*` rules
// are active — verified on this repo before the migration, where
// `eslint --print-config src/App.vue` listed **zero** `vue/no-deprecated-*`
// rules while `vue/no-v-model-argument` and `vue/no-v-for-template-key` were
// both at severity 2. So Vue 2 idioms survive a migration silently:
// `beforeDestroy` is the dangerous case, because Vue 3 never calls that hook,
// and planix cleaned up live-update subscriptions, a debounce timer and an
// AbortController there — four real leaks with zero console output.
//
// `conductionVue3Fixes` is an ARRAY of three flat-config objects (language
// level, SFC parser, deprecation rules). It deliberately registers no plugins,
// so it layers cleanly onto the `@nextcloud` base — and must be spread **last**
// to win over the preset it is correcting.
//
// It enables `vue/v-on-event-hyphenation` with `ignore: ['update:modelValue']`.
// That exception is load-bearing — Nextcloud Vue 3 field components read
// `onUpdate:modelValue` directly via `useModel`, so the hyphenated
// `@update:model-value` form is silently dead.
const {
	conductionVue3Fixes,
} = require('@conduction/nextcloud-vue/eslint')

const compat = new FlatCompat({
	baseDirectory: __dirname,
	recommendedConfig: js.configs.recommended,
	allConfig: js.configs.all,
})

module.exports = defineConfig([{
	extends: compat.extends('@nextcloud'),

	settings: {
		'import/resolver': {
			alias: {
				map: [
					['@', './src'],
					['@floating-ui/dom-actual', './node_modules/@floating-ui/dom'],
					['@conduction/nextcloud-vue', '../nextcloud-vue/src'],
				],
				extensions: ['.js', '.ts', '.vue', '.json', '.css'],
			},
		},
	},

	rules: {
		// Allow unused i18n functions (t, n) — imported for future translation wiring
		'no-unused-vars': ['error', { varsIgnorePattern: '^(t|n)$', argsIgnorePattern: '^_' }],
		'jsdoc/require-jsdoc': 'off',
		// Allow the ADR-008 spec-coverage annotation tag (@spec) in JSDoc blocks
		'jsdoc/check-tag-names': ['warn', { definedTags: ['spec'] }],
		'vue/first-attribute-linebreak': 'off',
		'@typescript-eslint/no-explicit-any': 'off',
		'n/no-missing-import': 'off',
		'import/namespace': 'off', // disable namespace checking to avoid parser requirement
		'import/default': 'off', // disable default import checking to avoid parser requirement
		'import/no-named-as-default': 'off', // disable named-as-default checking to avoid parser requirement
		'import/no-named-as-default-member': 'off', // disable named-as-default-member checking to avoid parser requirement
	},
}, ...conductionVue3Fixes])
