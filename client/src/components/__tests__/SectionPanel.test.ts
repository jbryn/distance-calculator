import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import SectionPanel from '../SectionPanel.vue'
import { useRiverStore } from '../../stores/river'
import { useSectionStore } from '../../stores/section'
import {
  controlledFetch,
  failure,
  flushPromises,
  gwda,
  gwdaSection,
  success
} from '../../test-utils/fetch'
import type { LatLng } from '../../types/river'

const byTestId = (id: string) => `[data-testid="${id}"]`

const A: LatLng = [53.5, 16.9]
const B: LatLng = [53.2, 16.7]

describe('SectionPanel', () => {
  let api: ReturnType<typeof controlledFetch>
  let requests = 0

  const respond = async (body: Response) => {
    api.respond(requests++, body)
    await flushPromises()
  }

  beforeEach(async () => {
    setActivePinia(createPinia())
    api = controlledFetch()
    requests = 0

    const loading = useRiverStore().select(gwda.id)
    await respond(success(gwda))
    await loading
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  const pickStartAndEnd = async (length = 57810.7, downstream = true) => {
    const store = useSectionStore()
    void store.pick(A)
    await respond(success(gwdaSection(A, A, 0)))
    void store.pick(B)
    await respond(success({ ...gwdaSection(A, B, length), downstream }))
  }

  it('asks for a start first', () => {
    const wrapper = mount(SectionPanel)

    expect(wrapper.find(byTestId('section-hint')).text()).toBe(
      'Click the river on the map to set where you start.'
    )
    expect(wrapper.find(byTestId('section-clear')).exists()).toBe(false)
  })

  it('asks for the finish once the start is set', async () => {
    const wrapper = mount(SectionPanel)

    void useSectionStore().pick(A)
    await respond(success(gwdaSection(A, A, 0)))

    expect(wrapper.find(byTestId('section-hint')).text()).toContain('Click the river again')
    expect(wrapper.find(byTestId('section-clear')).exists()).toBe(true)
    expect(wrapper.find(byTestId('section-swap')).exists()).toBe(false)
  })

  it('shows the length of the section in kilometers', async () => {
    const wrapper = mount(SectionPanel)

    await pickStartAndEnd(57810.7)

    expect(wrapper.find(byTestId('section-length')).text()).toBe('57.8 km')
    expect(wrapper.find(byTestId('section-upstream')).exists()).toBe(false)
  })

  it('warns when the finish is upstream of the start', async () => {
    const wrapper = mount(SectionPanel)

    await pickStartAndEnd(57810.7, false)

    expect(wrapper.find(byTestId('section-upstream')).text()).toContain('against the current')
  })

  it('shows that it is measuring', async () => {
    const wrapper = mount(SectionPanel)

    void useSectionStore().pick(A)
    await flushPromises()

    expect(wrapper.find(byTestId('section-loading')).exists()).toBe(true)
  })

  it('shows why a point was refused', async () => {
    const wrapper = mount(SectionPanel)

    void useSectionStore().pick([52.2, 21.0])
    await respond(
      failure("The 'from' point is 301.0 km from the river Gwda; it must be within 2 km", 422)
    )

    expect(wrapper.find(byTestId('section-error')).text()).toContain('301.0 km from the river Gwda')
  })

  it('swaps and clears through its buttons', async () => {
    const wrapper = mount(SectionPanel)
    await pickStartAndEnd()

    await wrapper.find(byTestId('section-swap')).trigger('click')
    expect(api.urls()[3]).toBe(
      `http://localhost:8080/api/rivers/${gwda.id}/section?from=${B.join(',')}&to=${A.join(',')}`
    )

    await wrapper.find(byTestId('section-clear')).trigger('click')
    expect(wrapper.find(byTestId('section-hint')).text()).toContain('set where you start')
  })
})
