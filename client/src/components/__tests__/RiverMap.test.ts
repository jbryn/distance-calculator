import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { gwda } from '../../test-utils/fetch'
import type { LatLng, River } from '../../types/river'

/**
 * Leaflet needs a real layout to draw, which jsdom does not have. The tests replace it
 * with a stand-in that records what the component asks of it; the end-to-end tests
 * check the drawn map.
 */
const leaflet = vi.hoisted(() => {
  type Handler = (event: unknown) => void

  const makeLine = () => {
    const line = {
      addTo: vi.fn(),
      remove: vi.fn(),
      getBounds: vi.fn(() => 'river bounds')
    }
    line.addTo.mockReturnValue(line)
    return line
  }

  const makeMarker = () => {
    const handlers: Record<string, Handler> = {}
    let position: unknown = null
    const marker = {
      handlers,
      on: vi.fn((name: string, handler: Handler) => {
        handlers[name] = handler
        return marker
      }),
      setLatLng: vi.fn((point: unknown) => {
        position = point
        return marker
      }),
      getLatLng: vi.fn(() => position),
      addTo: vi.fn(),
      remove: vi.fn()
    }
    marker.addTo.mockReturnValue(marker)
    return marker
  }

  const mapHandlers: Record<string, Handler> = {}
  const layers = new Set<unknown>()

  const map = {
    handlers: mapHandlers,
    setView: vi.fn(),
    fitBounds: vi.fn(),
    invalidateSize: vi.fn(),
    remove: vi.fn(),
    on: vi.fn((name: string, handler: Handler) => {
      mapHandlers[name] = handler
    }),
    hasLayer: vi.fn((layer: unknown) => layers.has(layer))
  }
  map.setView.mockReturnValue(map)

  const tiles = { addTo: vi.fn() }

  return {
    map,
    layers,
    tiles,
    lines: [] as ReturnType<typeof makeLine>[],
    markers: [] as ReturnType<typeof makeMarker>[],
    makeLine,
    makeMarker
  }
})

vi.mock('leaflet', () => ({
  default: {
    map: vi.fn(() => leaflet.map),
    tileLayer: vi.fn(() => leaflet.tiles),
    divIcon: vi.fn((options: unknown) => options),
    polyline: vi.fn((points: unknown, options: unknown) => {
      const line = leaflet.makeLine()
      Object.assign(line, { points, options })
      leaflet.lines.push(line)
      return line
    }),
    marker: vi.fn(() => {
      const marker = leaflet.makeMarker()
      // Track which markers are on the map, for hasLayer()
      marker.addTo.mockImplementation(() => {
        leaflet.layers.add(marker)
        return marker
      })
      marker.remove.mockImplementation(() => {
        leaflet.layers.delete(marker)
        return marker
      })
      leaflet.markers.push(marker)
      return marker
    })
  }
}))

const { default: L } = await import('leaflet')
const { default: RiverMap } = await import('../RiverMap.vue')

const A: LatLng = [53.5, 16.9]
const B: LatLng = [53.2, 16.7]

const props = (
  overrides: Partial<{
    river: River | null
    start: LatLng | null
    end: LatLng | null
    section: LatLng[] | null
    revision: number
  }> = {}
) => ({
  river: null,
  start: null,
  end: null,
  section: null,
  revision: 0,
  ...overrides
})

const linesWithClass = (className: string) =>
  leaflet.lines.filter(
    (line) =>
      (line as unknown as { options: { className: string } }).options.className === className
  )

describe('RiverMap', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    leaflet.lines.length = 0
    leaflet.markers.length = 0
    leaflet.layers.clear()
    leaflet.map.setView.mockReturnValue(leaflet.map)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  const [startMarker, endMarker] = [() => leaflet.markers[0], () => leaflet.markers[1]]

  describe('the river', () => {
    it('opens on Poland with OpenStreetMap tiles and their attribution', () => {
      mount(RiverMap, { props: props() })

      expect(leaflet.map.setView).toHaveBeenCalledWith([52.1, 19.4], 6)
      expect(L.tileLayer).toHaveBeenCalledWith(
        'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        expect.objectContaining({ attribution: expect.stringContaining('OpenStreetMap') })
      )
      expect(leaflet.tiles.addTo).toHaveBeenCalledWith(leaflet.map)
      expect(L.polyline).not.toHaveBeenCalled()
    })

    it('draws the river and fits it in view', () => {
      mount(RiverMap, { props: props({ river: gwda }) })

      expect(L.polyline).toHaveBeenCalledWith(
        gwda.points,
        expect.objectContaining({ className: 'river-line' })
      )
      expect(leaflet.map.fitBounds).toHaveBeenCalledWith('river bounds', { padding: [24, 24] })
    })

    it('replaces the line when another river is chosen, and removes it when none is', async () => {
      const wrapper = mount(RiverMap, { props: props({ river: gwda }) })
      const first = linesWithClass('river-line')[0]

      await wrapper.setProps({ river: { ...gwda, id: 1, name: 'Other' } })
      expect(first.remove).toHaveBeenCalled()
      expect(linesWithClass('river-line')).toHaveLength(2)

      await wrapper.setProps({ river: null })
      expect(linesWithClass('river-line')[1].remove).toHaveBeenCalled()
    })
  })

  describe('picking points', () => {
    it('reports a click on the map while a river is shown', () => {
      const wrapper = mount(RiverMap, { props: props({ river: gwda }) })

      leaflet.map.handlers.click({ latlng: { lat: 53.4, lng: 16.8 } })

      expect(wrapper.emitted('pick')).toEqual([[[53.4, 16.8]]])
    })

    it('ignores clicks while no river is shown', () => {
      const wrapper = mount(RiverMap, { props: props() })

      leaflet.map.handlers.click({ latlng: { lat: 53.4, lng: 16.8 } })

      expect(wrapper.emitted('pick')).toBeUndefined()
    })

    it('shows a crosshair while a river is shown', async () => {
      const wrapper = mount(RiverMap, { props: props() })
      expect(wrapper.classes()).not.toContain('map-picking')

      await wrapper.setProps({ river: gwda })
      expect(wrapper.classes()).toContain('map-picking')
    })
  })

  describe('markers', () => {
    it('creates draggable start and finish markers labelled A and B', () => {
      mount(RiverMap, { props: props() })

      expect(L.marker).toHaveBeenCalledTimes(2)
      expect(L.marker).toHaveBeenNthCalledWith(
        1,
        [0, 0],
        expect.objectContaining({ draggable: true, title: 'Start' })
      )
      expect(L.marker).toHaveBeenNthCalledWith(
        2,
        [0, 0],
        expect.objectContaining({ draggable: true, title: 'Finish' })
      )
      expect(vi.mocked(L.divIcon).mock.calls[0][0]).toMatchObject({
        html: expect.stringContaining('>A<')
      })
      expect(vi.mocked(L.divIcon).mock.calls[1][0]).toMatchObject({
        html: expect.stringContaining('>B<')
      })
    })

    it('shows only the markers that have a position', async () => {
      const wrapper = mount(RiverMap, { props: props({ river: gwda }) })
      expect(leaflet.layers.size).toBe(0)

      await wrapper.setProps({ start: A })
      expect(startMarker().setLatLng).toHaveBeenLastCalledWith(A)
      expect(leaflet.layers.has(startMarker())).toBe(true)
      expect(leaflet.layers.has(endMarker())).toBe(false)

      await wrapper.setProps({ end: B })
      expect(endMarker().setLatLng).toHaveBeenLastCalledWith(B)
      expect(leaflet.layers.has(endMarker())).toBe(true)

      await wrapper.setProps({ start: null, end: null })
      expect(leaflet.layers.size).toBe(0)
    })

    it('reports where a marker was dragged to', () => {
      const wrapper = mount(RiverMap, { props: props({ river: gwda, start: A, end: B }) })

      startMarker().setLatLng([53.45, 16.85])
      startMarker().handlers.dragend({ target: { getLatLng: () => ({ lat: 53.45, lng: 16.85 }) } })
      endMarker().handlers.dragend({ target: { getLatLng: () => ({ lat: 53.1, lng: 16.7 }) } })

      expect(wrapper.emitted('moveStart')).toEqual([[[53.45, 16.85]]])
      expect(wrapper.emitted('moveEnd')).toEqual([[[53.1, 16.7]]])
    })

    it('puts a dragged marker back when the selection did not change', async () => {
      const wrapper = mount(RiverMap, { props: props({ river: gwda, start: A }) })

      // Dragged somewhere the API then refused: the store keeps A and bumps the revision
      startMarker().setLatLng([52.2, 21.0])
      await wrapper.setProps({ revision: 1 })

      expect(startMarker().setLatLng).toHaveBeenLastCalledWith(A)
    })
  })

  describe('the section', () => {
    it('highlights the section over the river', async () => {
      const wrapper = mount(RiverMap, { props: props({ river: gwda }) })

      await wrapper.setProps({ section: [A, B] })

      expect(linesWithClass('section-line')).toHaveLength(1)
      expect(L.polyline).toHaveBeenLastCalledWith(
        [A, B],
        expect.objectContaining({ className: 'section-line' })
      )
    })

    it('removes the highlight when the section is cleared', async () => {
      const wrapper = mount(RiverMap, { props: props({ river: gwda, section: [A, B] }) })

      await wrapper.setProps({ section: null })

      expect(linesWithClass('section-line')[0].remove).toHaveBeenCalled()
    })
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

    mount(RiverMap, { props: props() })
    resized()

    expect(observe).toHaveBeenCalled()
    expect(leaflet.map.invalidateSize).toHaveBeenCalled()
  })

  it('removes the map when it goes away', () => {
    const wrapper = mount(RiverMap, { props: props() })

    wrapper.unmount()

    expect(leaflet.map.remove).toHaveBeenCalled()
  })
})
