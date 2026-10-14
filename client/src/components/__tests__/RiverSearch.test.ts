import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import RiverSearch from '../RiverSearch.vue'
import { SEARCH_DELAY_MS } from '../../composables/useRiverSearch'
import { controlledFetch, failure, flushPromises, success } from '../../test-utils/fetch'

const byTestId = (id: string) => `[data-testid="${id}"]`

const RIVERS = [
  { id: 34392, name: 'Wisła' },
  { id: 2020696, name: 'Wisłok' }
]

describe('RiverSearch', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('lists the rivers found', async () => {
    const api = controlledFetch()
    const wrapper = mount(RiverSearch)

    api.respond(0, success(RIVERS))
    await flushPromises()

    expect(wrapper.findAll('li').map((item) => item.text())).toEqual(['Wisła', 'Wisłok'])
  })

  it('marks the list as busy while searching', async () => {
    const api = controlledFetch()
    const wrapper = mount(RiverSearch)

    expect(wrapper.find(byTestId('search-results')).attributes('aria-busy')).toBe('true')

    api.respond(0, success(RIVERS))
    await flushPromises()

    expect(wrapper.find(byTestId('search-results')).attributes('aria-busy')).toBe('false')
  })

  it('searches for what is typed', async () => {
    const api = controlledFetch()
    const wrapper = mount(RiverSearch)

    await wrapper.find(byTestId('river-query')).setValue('wis')
    vi.advanceTimersByTime(SEARCH_DELAY_MS)

    expect(api.urls()[1]).toBe('http://localhost:8080/api/rivers?q=wis')
  })

  it('emits the id of the river picked', async () => {
    const api = controlledFetch()
    const wrapper = mount(RiverSearch)
    api.respond(0, success(RIVERS))
    await flushPromises()

    await wrapper.find(byTestId('river-option-2020696')).trigger('click')

    expect(wrapper.emitted('select')).toEqual([[2020696]])
  })

  it('says so when nothing matches', async () => {
    const api = controlledFetch()
    const wrapper = mount(RiverSearch)

    await wrapper.find(byTestId('river-query')).setValue(' amazon ')
    vi.advanceTimersByTime(SEARCH_DELAY_MS)
    api.respond(1, success([]))
    await flushPromises()

    expect(wrapper.find(byTestId('search-empty')).text()).toBe('No rivers match “amazon”.')
  })

  it('shows an error with a way to try again', async () => {
    const api = controlledFetch()
    const wrapper = mount(RiverSearch)

    api.respond(0, failure('Internal server error', 500))
    await flushPromises()

    expect(wrapper.find(byTestId('search-error')).text()).toContain('Internal server error')

    await wrapper.find(byTestId('search-retry')).trigger('click')
    api.respond(1, success(RIVERS))
    await flushPromises()

    expect(wrapper.find(byTestId('search-error')).exists()).toBe(false)
    expect(wrapper.findAll('li')).toHaveLength(2)
  })

  it('labels the search field', () => {
    controlledFetch()
    const wrapper = mount(RiverSearch)

    expect(wrapper.find('label').attributes('for')).toBe('river-query')
    expect(wrapper.find(byTestId('river-query')).attributes('id')).toBe('river-query')
  })
})
