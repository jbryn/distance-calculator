import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import RiverDetails from '../RiverDetails.vue'
import { useRiverStore } from '../../stores/river'
import { controlledFetch, failure, flushPromises, gwda, success } from '../../test-utils/fetch'

const byTestId = (id: string) => `[data-testid="${id}"]`

describe('RiverDetails', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows that the river is loading', () => {
    controlledFetch()
    void useRiverStore().select(gwda.id)

    const wrapper = mount(RiverDetails)

    expect(wrapper.find(byTestId('river-loading')).text()).toBe('Loading river…')
  })

  it('shows the name, length and counts of obstacles and access points', async () => {
    const api = controlledFetch()
    const loading = useRiverStore().select(gwda.id)
    api.respond(0, success(gwda))
    await loading

    const wrapper = mount(RiverDetails)

    expect(wrapper.find(byTestId('river-name')).text()).toBe('Gwda')
    expect(wrapper.find(byTestId('river-length')).text()).toBe('142.9 km')
    expect(wrapper.find(byTestId('river-obstacles')).text()).toBe('2')
    expect(wrapper.find(byTestId('river-access-points')).text()).toBe('1')
  })

  it('shows an error and loads the river again on request', async () => {
    const api = controlledFetch()
    const store = useRiverStore()
    const loading = store.select(gwda.id)
    api.respond(0, failure('The data for this river is unavailable', 503))
    await loading

    const wrapper = mount(RiverDetails)
    expect(wrapper.find(byTestId('river-error')).text()).toContain(
      'The data for this river is unavailable'
    )

    await wrapper.find(byTestId('river-retry')).trigger('click')
    api.respond(1, success(gwda))
    await flushPromises()

    expect(wrapper.find(byTestId('river-name')).text()).toBe('Gwda')
  })

  it('asks to go back to choosing a river', async () => {
    controlledFetch()
    const wrapper = mount(RiverDetails)

    await wrapper.find(byTestId('change-river')).trigger('click')

    expect(wrapper.emitted('back')).toHaveLength(1)
  })
})
