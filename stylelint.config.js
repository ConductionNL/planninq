/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * platform-baseline REQ-PLB-002 / REQ-PLB-003: a literal hex colour only as
 * a var() fallback (the in-repo planninq/no-hex-outside-var-fallback rule),
 * and logical instead of physical left/right properties (Nextcloud's own
 * csstools/use-logical rule, from stylelint-use-logical, raised from a
 * warning to an error with the same exceptions), so the theme reaches every
 * colour and a right-to-left page mirrors.
 */
const nextcloud = require('@nextcloud/stylelint-config')

const [useLogical, useLogicalOptions] = nextcloud.rules['csstools/use-logical']

module.exports = {
	extends: '@nextcloud/stylelint-config',
	plugins: ['./scripts/stylelint/no-hex-outside-var-fallback.mjs'],
	rules: {
		'selector-pseudo-element-no-unknown': [true, {
			ignorePseudoElements: ['v-deep'],
		}],
		'planninq/no-hex-outside-var-fallback': true,
		'csstools/use-logical': [useLogical, { ...useLogicalOptions, severity: 'error' }],
	},
}
