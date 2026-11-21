/**
 * The case link of a project and a task (case-link-editing): the helpers in
 * src/utils/caseLink.js, and the logic of CaseLinkField and CaseBadge read
 * without mounting them. Only HTTP (axios), the router and the Nextcloud
 * globals are stood in for.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { loadSfcWithImports } from './support/sfc.js'

const requests = []
let answer = () => Promise.resolve({ data: {} })

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: (url, config) => {
			requests.push({ url, params: config?.params, signal: config?.signal })
			return answer(url, config)
		},
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path, params = {}) => '/index.php' + path.replace(/\{(\w+)\}/g, (_, key) => encodeURIComponent(params[key])),
}))

const {
	canEditCaseLink,
	caseAppInstalled,
	caseLabel,
	caseTitle,
	caseUrl,
	caseValue,
	forgetCaseTitles,
	searchCases,
} = await import('../../src/utils/caseLink.js')

beforeEach(() => {
	requests.length = 0
	answer = () => Promise.resolve({ data: {} })
	forgetCaseTitles()
})

describe('caseAppInstalled', () => {
	it('is true only when the case app has a web root', () => {
		expect(caseAppInstalled({ appswebroots: { dossiq: '/apps/dossiq', planninq: '/apps/planninq' } })).toBe(true)
		expect(caseAppInstalled({ appswebroots: { planninq: '/apps/planninq' } })).toBe(false)
		expect(caseAppInstalled({})).toBe(false)
		expect(caseAppInstalled(undefined)).toBe(false)
	})
})

describe('canEditCaseLink', () => {
	it('lets the owner, a manager and an administrator change the link', () => {
		expect(canEditCaseLink('owner', false)).toBe(true)
		expect(canEditCaseLink('manager', false)).toBe(true)
		expect(canEditCaseLink('none', true)).toBe(true)
	})

	it('keeps other members to looking at it', () => {
		expect(canEditCaseLink('member', false)).toBe(false)
		expect(canEditCaseLink('viewer', false)).toBe(false)
		expect(canEditCaseLink('none', false)).toBe(false)
	})
})

describe('caseUrl, caseLabel and caseValue', () => {
	it('addresses the case in the case app', () => {
		expect(caseUrl('c-1')).toBe('/index.php/apps/dossiq/cases/c-1')
		expect(caseUrl('a/b')).toBe('/index.php/apps/dossiq/cases/a%2Fb')
	})

	it('names a case by its title, else its identifier, else its id', () => {
		expect(caseLabel({ id: 'c-1', identifier: 'Z-1', title: ' Omgevingsvergunning Markt 12 ' })).toBe('Omgevingsvergunning Markt 12')
		expect(caseLabel({ id: 'c-1', identifier: 'Z-1', title: '' })).toBe('Z-1')
		expect(caseLabel({ '@self': { id: 'c-2' } })).toBe('c-2')
		expect(caseLabel(null)).toBe('')
	})

	it('writes the picked id, and null for a cleared field', () => {
		expect(caseValue({ id: 'c-1', label: 'x' })).toBe('c-1')
		expect(caseValue({ id: '  ' })).toBeNull()
		expect(caseValue(null)).toBeNull()
	})
})

describe('searchCases', () => {
	it('asks nothing for fewer than two characters', async () => {
		expect(await searchCases(' a ')).toEqual([])
		expect(await searchCases(null)).toEqual([])
		expect(requests).toEqual([])
	})

	it('searches the case register and offers the readable cases as options', async () => {
		const controller = new AbortController()
		answer = () => Promise.resolve({ data: { results: [{ id: 'c-1', title: 'Omgevingsvergunning Markt 12' }, { identifier: 'no id' }, { '@self': { id: 'c-3' }, identifier: 'Z-3' }] } })

		const options = await searchCases(' markt ', { signal: controller.signal })

		expect(options).toEqual([{ id: 'c-1', label: 'Omgevingsvergunning Markt 12' }, { id: 'c-3', label: 'Z-3' }])
		expect(requests).toEqual([{ url: '/index.php/apps/openregister/api/objects/dossiq/case', params: { _search: 'markt', _limit: 20 }, signal: controller.signal }])
	})

	it('offers nothing when the answer holds no results list', async () => {
		answer = () => Promise.resolve({ data: { message: 'no' } })
		expect(await searchCases('markt')).toEqual([])
	})
})

describe('caseTitle', () => {
	it('reads a case once and names it', async () => {
		answer = () => Promise.resolve({ data: { id: 'c-1', title: 'Omgevingsvergunning Markt 12' } })

		expect(await caseTitle('c-1')).toBe('Omgevingsvergunning Markt 12')
		expect(await caseTitle('c-1')).toBe('Omgevingsvergunning Markt 12')
		expect(requests.map((request) => request.url)).toEqual(['/index.php/apps/openregister/api/objects/dossiq/case/c-1'])
	})

	it('answers null for a case it cannot read, and asks again next time', async () => {
		answer = () => Promise.reject(new Error('404'))

		expect(await caseTitle('gone')).toBeNull()
		expect(await caseTitle('gone')).toBeNull()
		expect(requests).toHaveLength(2)
	})

	it('answers null without asking for no id', async () => {
		expect(await caseTitle('')).toBeNull()
		expect(requests).toEqual([])
	})
})

/**
 * A component instance stand-in: data, methods, computed getters and the
 * given props, with recorders for $emit.
 *
 * @param {object} options - the component options
 * @param {object} props - the props
 * @return {object} the instance
 */
function instance(options, props) {
	const vm = { ...props, emitted: [], t: (app, text) => text }
	vm.$emit = (name, payload) => vm.emitted.push([name, payload])
	for (const [name, method] of Object.entries(options.methods || {})) {
		vm[name] = method.bind(vm)
	}
	for (const [name, getter] of Object.entries(options.computed || {})) {
		Object.defineProperty(vm, name, { get: getter.bind(vm) })
	}
	Object.assign(vm, options.data ? options.data.call(vm) : {})
	return vm
}

describe('CaseLinkField', () => {
	let field
	let errors

	beforeEach(async () => {
		const { options } = await loadSfcWithImports(new URL('../../src/components/CaseLinkField.vue', import.meta.url))
		field = options
		errors = vi.spyOn(console, 'error').mockImplementation(() => {})
	})

	afterEach(() => {
		errors.mockRestore()
	})

	it('shows the edit control only to whoever may change the link', async () => {
		const { template } = await loadSfcWithImports(new URL('../../src/components/CaseLinkField.vue', import.meta.url))
		expect(template).toMatch(/<NcSelect\s+v-if="editable"/)
		expect(template).toMatch(/<CaseBadge v-if="modelValue"/)
		expect(field.props.editable.default).toBe(false)
	})

	it('emits the picked case id, and null when the field is cleared', () => {
		const vm = instance(field, { modelValue: null, editable: true })

		vm.onPick({ id: 'c-1', label: 'Omgevingsvergunning Markt 12' })
		vm.onPick(null)

		expect(vm.emitted).toEqual([['update:modelValue', 'c-1'], ['update:modelValue', null]])
		expect(vm.title).toBe('Omgevingsvergunning Markt 12')
	})

	it('shows the linked case by name once read', async () => {
		answer = () => Promise.resolve({ data: { id: 'c-1', title: 'Omgevingsvergunning Markt 12' } })
		const vm = instance(field, { modelValue: 'c-1', editable: true })

		expect(vm.selected).toEqual({ id: 'c-1', label: 'c-1' })
		await vm.readTitle()

		expect(vm.selected).toEqual({ id: 'c-1', label: 'Omgevingsvergunning Markt 12' })
		expect(vm.href).toBe('/index.php/apps/dossiq/cases/c-1')
	})

	it('has nothing selected without a link', () => {
		expect(instance(field, { modelValue: null }).selected).toBeNull()
	})

	it('offers the matching cases, and nothing for a short term', async () => {
		answer = () => Promise.resolve({ data: { results: [{ id: 'c-1', title: 'Markt 12' }] } })
		const vm = instance(field, { modelValue: null, editable: true })

		await vm.onSearch('markt')
		expect(vm.options).toEqual([{ id: 'c-1', label: 'Markt 12' }])
		expect(vm.loading).toBe(false)

		await vm.onSearch('m')
		expect(vm.options).toEqual([])
		expect(requests).toHaveLength(1)
	})

	it('aborts an overtaken search and keeps the newer answer', async () => {
		const pending = []
		answer = (url, config) => new Promise((resolve, reject) => {
			pending.push(resolve)
			config.signal.addEventListener('abort', () => reject(Object.assign(new Error('aborted'), { name: 'CanceledError' })))
		})
		const vm = instance(field, { modelValue: null, editable: true })

		const first = vm.onSearch('mark')
		const second = vm.onSearch('markt')
		await first
		expect(vm.loading).toBe(true)
		expect(requests[0].signal.aborted).toBe(true)

		pending[1]({ data: { results: [{ id: 'c-2', title: 'Markt 12' }] } })
		await second

		expect(vm.options).toEqual([{ id: 'c-2', label: 'Markt 12' }])
		expect(vm.loading).toBe(false)
		expect(errors).not.toHaveBeenCalled()
	})

	it('offers nothing and logs when a search fails', async () => {
		answer = () => Promise.reject(new Error('500'))
		const vm = instance(field, { modelValue: null, editable: true })
		vm.options = [{ id: 'old', label: 'old' }]

		await vm.onSearch('markt')

		expect(vm.options).toEqual([])
		expect(vm.loading).toBe(false)
		expect(errors).toHaveBeenCalledOnce()
	})
})

describe('CaseBadge', () => {
	it('names the case, and keeps the newer link when an older read finishes late', async () => {
		const { options } = await loadSfcWithImports(new URL('../../src/components/CaseBadge.vue', import.meta.url))
		const reads = {}
		answer = (url) => new Promise((resolve) => {
			reads[url.split('/').pop()] = resolve
		})
		const vm = instance(options, { caseId: 'c-1' })

		const older = vm.readTitle()
		vm.caseId = 'c-2'
		const newer = vm.readTitle()
		reads['c-2']({ data: { title: 'Nieuw' } })
		await newer
		reads['c-1']({ data: { title: 'Oud' } })
		await older

		expect(vm.title).toBe('Nieuw')
		expect(vm.name).toBe('Nieuw')
		expect(vm.href).toBe('/index.php/apps/dossiq/cases/c-2')
	})

	it('shows the id while the case cannot be read', async () => {
		const { options } = await loadSfcWithImports(new URL('../../src/components/CaseBadge.vue', import.meta.url))
		answer = () => Promise.reject(new Error('403'))
		const vm = instance(options, { caseId: 'c-9' })

		await vm.readTitle()

		expect(vm.name).toBe('c-9')
	})
})
