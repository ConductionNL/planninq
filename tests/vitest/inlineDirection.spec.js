// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * @spec openspec/changes/platform-baseline/specs/platform-baseline/spec.md#requirement-pages-mirror-in-a-right-to-left-language-req-plb-003
 */
import { describe, expect, it } from 'vitest'
import { inlineStep, isRtl } from '../../src/utils/inlineDirection.js'

describe('isRtl', () => {
	it('reads the computed direction of the element', () => {
		const el = {}
		expect(isRtl(el, () => ({ direction: 'rtl' }))).toBe(true)
		expect(isRtl(el, () => ({ direction: 'ltr' }))).toBe(false)
	})

	it('is false without a style reader', () => {
		expect(isRtl({}, null)).toBe(false)
	})
})

describe('inlineStep', () => {
	it('keeps a step in a left-to-right page', () => {
		expect(inlineStep(1, false)).toBe(1)
		expect(inlineStep(-1, false)).toBe(-1)
	})

	it('reverses a step in a right-to-left page, so the left arrow moves toward the end', () => {
		expect(inlineStep(-1, true)).toBe(1)
		expect(inlineStep(40, true)).toBe(-40)
	})
})
