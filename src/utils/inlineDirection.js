// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * Right-to-left helpers for pages that place things by measured offsets
 * (platform-baseline, REQ-PLB-003). Offsets are applied from the inline
 * start, so a page mirrors in a right-to-left language; what a pointer or an
 * arrow key means has to mirror with it.
 *
 * @spec openspec/changes/platform-baseline/specs/platform-baseline/spec.md#requirement-pages-mirror-in-a-right-to-left-language-req-plb-003
 */

/**
 * Whether an element is laid out right to left.
 *
 * @param {Element|null|undefined} el The element, or nothing for the document.
 * @param {Function} styleOf The getComputedStyle to ask; injectable for tests.
 *
 * @return {boolean} True for a right-to-left element.
 *
 * @spec openspec/changes/platform-baseline/specs/platform-baseline/spec.md#requirement-pages-mirror-in-a-right-to-left-language-req-plb-003
 */
export function isRtl(el, styleOf = globalThis.getComputedStyle) {
	const target = el || globalThis.document?.documentElement
	if (!target || typeof styleOf !== 'function') {
		return false
	}
	return styleOf(target)?.direction === 'rtl'
}

/**
 * A visual step (-1 left, +1 right) as a step along the inline axis: the
 * same in left-to-right, reversed in right-to-left.
 *
 * @param {number} visualStep The step on screen.
 * @param {boolean} rtl Whether the page is right to left.
 *
 * @return {number} The step from the inline start.
 *
 * @spec openspec/changes/platform-baseline/specs/platform-baseline/spec.md#requirement-pages-mirror-in-a-right-to-left-language-req-plb-003
 */
export function inlineStep(visualStep, rtl) {
	return rtl ? -visualStep : visualStep
}
