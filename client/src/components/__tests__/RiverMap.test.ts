import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { gwda } from '../../test-utils/fetch'

/**
 * Leaflet needs a real layout to draw, which jsdom does not have. The tests replace it
 * with a stand-in that records what the component asks of it; the end-to-end tests
 * check the drawn map.
 */
const leaflet = vi.hoisted(() => {
  const line = { addTo: vi.fn(), remove: vi.fn(), getBounds: vi.fn(() => 'river bounds') }
  line.addTo.mockReturnValue(line)

  const map = {
    setView: vi.fn(),
    fitBounds: vi.fn(),
    invalidateSize: vi.fn(),
    remove: vi.fn()
  }
  map.setView.mockReturnValue(map)

  const tiles = { addTo: vi.fn() }

  return {
    line,
    map,
    tiles,
    module: {
      map: vi.fn(() => map),
      tileLayer: vi.fn(() => tiles),
      polyline: vi.fn(() => line)
    }
  }
})

vi.mock('leaflet', () => ({ default: leaflet.module }))

const { default: RiverMap } = await import('../RiverMap.vue')

describe('RiverMap', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    leaflet.line.addTo.mockReturnValue(leaflet.line)
    leaflet.map.setView.mockReturnValue(leaflet.map)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('opens on Poland with OpenStreetMap tiles and their attribution', () => {
    mount(RiverMap, { props: { river: null } })

    expect(leaflet.map.setView).toHaveBeenCalledWith([52.1, 19.4], 6)
    expect(leaflet.module.tileLayer).toHaveBeenCalledWith(
      'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
      expect.objectContaining({ attribution: expect.stringContaining('OpenStreetMap') })
    )
    expect(leaflet.tiles.addTo).toHaveBeenCalledWith(leaflet.map)
    expect(leaflet.module.polyline).not.toHaveBeenCalled()
  })

  it('draws the river and fits it in view', () => {
    mount(RiverMap, { props: { river: gwda } })

    expect(leaflet.module.polyline).toHaveBeenCalledWith(
      gwda.points,
      expect.objectContaining({ className: 'river-line' })
    )
    expect(leaflet.line.addTo).toHaveBeenCalledWith(leaflet.map)
    expect(leaflet.map.fitBounds).toHaveBeenCalledWith('river bounds', { padding: [24, 24] })
  })

  it('replaces the line when another river is chosen, and removes it when none is', async () => {
    const wrapper = mount(RiverMap, { props: { river: gwda } })

    await wrapper.setProps({ river: { ...gwda, id: 1, name: 'Other' } })
    expect(leaflet.line.remove).toHaveBeenCalledTimes(1)
    expect(leaflet.module.polyline).toHaveBeenCalledTimes(2)

    await wrapper.setProps({ river: null })
    expect(leaflet.line.remove).toHaveBeenCalledTimes(2)
    expect(leaflet.module.polyline).toHaveBeenCalledTimes(2)
  })

  it('adjusts the map when its box changes size', () => {
    let resized: () => void = () => {}
    const observe = vi.fn()
    vi.stubGlobal(
      'ResizeObserver',
      class {
        constructor(callback: () => void) {
          resized = callback
        }
        observe = observe
        disconnect = vi.fn()
      }
    )

    mount(RiverMap, { props: { river: null } })
    resized()

    expect(observe).toHaveBeenCalled()
    expect(leaflet.map.invalidateSize).toHaveBeenCalled()
  })

  it('removes the map when it goes away', () => {
    const wrapper = mount(RiverMap, { props: { river: null } })

    wrapper.unmount()

    expect(leaflet.map.remove).toHaveBeenCalled()
  })
})
