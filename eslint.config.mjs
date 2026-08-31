// Flat config for eslint 10.
//
// WHY THIS REPLACED eslint.config.js
// ----------------------------------
// planninq was the last app in the fleet still on eslint 8. `@nextcloud/eslint-config@9`
// declares `peer eslint: ">=10"`, so the dependabot bump to 9.0.1 could not install at
// all: twelve checks went red at `npm ci`, before a single file was linted. Bumping the
// config alone was never going to work, because the eslint 8 constraint was held in three
// more places at once:
//
//   @typescript-eslint/parser@7  peers eslint ^8.56.0
//   eslint-plugin-import@2.32.0  peers eslint up to ^9
//   @eslint/eslintrc             exists only to bridge the eslintrc era
//
// So the stack moved together, to the versions hermiq, versioniq and keepiq already run
// green: eslint ^10.9.1, @nextcloud/eslint-config ^9.0.1, @typescript-eslint/parser
// ^8.68.0.
//
// `eslint-plugin-vue` is deliberately NOT a dependency any more. v9 bundles its own
// `eslint-plugin-vue@10`, and a stale `^9` left in devDependencies hoists over the
// bundled copy, which makes `**/*.vue` parse as TypeScript and fails every SFC.
// `vue-eslint-parser` stays, at `^10`, because it is a peer the app must supply.
//
// `conductionVue3Fixes` is gone too. That preset existed to patch a Vue-2 ruleset, and
// against v9's `recommended` every one of its jobs is already done: all 21
// `vue/no-deprecated-*` rules are enabled, and the two it had to switch off because they
// are inverted under Vue 3 are not enabled at all. Spreading it here is not merely
// redundant, it BREAKS the run: it names `vue/*` rules while registering no plugin, and
// eslint aborts with "could not find plugin vue" — exit 2, nothing linted.
//
// 🔴 THE FAILURE MODE THIS FILE MUST AVOID. An eslint 10 install pointed at a config it
// cannot read does not report zero problems loudly. It lints NOTHING and exits non-zero
// in a way that reads as a lint failure, which is how versioniq's entire Vue and
// TypeScript layer went unchecked with 266 real problems behind it. So the check after
// any change here is the FILE COUNT, never the error count: `npx eslint src --format
// json` must report the same 46 files it reported under eslint 8, not 0.
import { recommended } from '@nextcloud/eslint-config'

export default [
	...recommended,
	{
		// 🔴 SCOPED, and it must be. Flat config resolves a rule's plugin from the config
		// object the rule sits in, and v9 also lints `.json`, where `jsdoc` is not
		// registered. An unscoped `jsdoc/*` override aborts the whole run with "The jsdoc
		// plugin is not defined in your configuration file" — a config error, not a
		// finding. The ignores are part of that scope rather than an opinion: v9 registers
		// jsdoc only inside `nextcloud/documentation/*`, and those blocks carry exactly
		// this list, because Nextcloud does not require JSDoc in tests.
		files: ['**/*.js', '**/*.mjs', '**/*.ts', '**/*.tsx', '**/*.vue'],
		ignores: [
			'**/*.test.*',
			'**/*.spec.*',
			'**/*.cy.*',
			'**/test/**',
			'**/tests/**',
			'**/__tests__/**',
			'**/__mocks__/**',
		],

		settings: {
			// `@conduction/nextcloud-vue` resolves to the sibling checkout when one is
			// present, which is how a local nextcloud-vue is linted against this app.
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
			// `t` and `n` are imported ahead of the translation wiring that will use them.
			'no-unused-vars': [
				'error',
				{ varsIgnorePattern: '^(t|n)$', argsIgnorePattern: '^_' },
			],
			'jsdoc/require-jsdoc': 'off',
			// ADR-008 spec-coverage annotation.
			'jsdoc/check-tag-names': ['warn', { definedTags: ['spec'] }],
			'vue/first-attribute-linebreak': 'off',
			'@typescript-eslint/no-explicit-any': 'off',
			'n/no-missing-import': 'off',

			// `error` and `warn` are how this app reports failures it cannot show in
			// the UI, and all 21 existing calls are one of those two. Deleting them to
			// satisfy the rule would remove diagnostics, so they are allowed by name
			// rather than the rule being switched off: a stray `console.log` left
			// behind while debugging is still an error, which is what the rule is for.
			'no-console': ['error', { allow: ['error', 'warn'] }],
		},
	},
	{
		// Route views are mounted by the router under their path, never written as a
		// tag in a template, so they cannot collide with a current or future HTML
		// element — which is the entire reason `vue/multi-word-component-names`
		// exists. Renaming `Dashboard` to `PlanninqDashboard` would churn five files
		// and every import of them to satisfy a rule that is not describing a risk
		// here. Scoped to `views/` so ordinary components keep the rule.
		files: ['src/views/**/*.vue'],
		rules: {
			'vue/multi-word-component-names': 'off',
		},
	},
]
