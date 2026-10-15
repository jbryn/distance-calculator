import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { useRiverStore } from '../river'
import { useSectionStore } from '../section'
import {
  controlledFetch,
  failure,
  flushPromises,
  gwda,
  gwdaSection,
  success
} from '../../test-utils/fetch'
import type { LatLng } from '../../types/river'

const A: LatLng = [53.5, 16.9]
const B: LatLng = [53.2, 16.7]
const C: LatLng = [53.1, 16.72]

/**
 * Where the stand-in API "snaps" a clicked point: a little to the east. Snapping a
 * point that is already on the river leaves it where it is, as the real API does.
 */
const snapped = ([lat, lon]: LatLng): LatLng => [lat, Math.floor(lon * 100) / 100 + 0.005]

const sectionUrl = (from: LatLng, to: LatLng) =>
  `http://localhost:8080/api/rivers/${gwda.id}/section?from=${from.join(',')}&to=${to.join(',')}`

describe('section store', () => {
  let api: ReturnType<typeof controlledFetch>

  /** Answers request number `index` with the section between the snapped points */
  const answer = async (index: number, from: LatLng, to: LatLng, length = 1000) => {
    api.respond(index, success(gwdaSection(snapped(from), snapped(to), length)))
    await flushPromises()
  }

  beforeEach(async () => {
    setActivePinia(createPinia())
    api = controlledFetch()

    // A river is selected and loaded: request 0
    const loading = useRiverStore().select(gwda.id)
    api.respond(0, success(gwda))
    await loading
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('starts empty', () => {
    const store = useSectionStore()

    expect(store.start).toBeNull()
    expect(store.end).toBeNull()
    expect(store.section).toBeNull()
    expect(store.status).toBe('idle')
  })

  it('places the start where the API snaps the first click', async () => {
    const store = useSectionStore()

    void store.pick(A)
    expect(store.status).toBe('loading')
    expect(api.urls()[1]).toBe(sectionUrl(A, A))

    await answer(1, A, A)

    expect(store.start?.point).toEqual(snapped(A))
    expect(store.end).toBeNull()
    expect(store.section).toBeNull()
    expect(store.status).toBe('ready')
  })

  it('measures the section once the second click sets the end', async () => {
    const store = useSectionStore()
    void store.pick(A)
    await answer(1, A, A)

    void store.pick(B)
    expect(api.urls()[2]).toBe(sectionUrl(snapped(A), B))
    await answer(2, snapped(A), B, 34_000)

    expect(store.end?.point).toEqual(snapped(B))
    expect(store.section?.length).toBe(34_000)
  })

  it('starts a new selection on a third click', async () => {
    const store = useSectionStore()
    void store.pick(A)
    await answer(1, A, A)
    void store.pick(B)
    await answer(2, snapped(A), B)

    void store.pick(C)
    expect(api.urls()[3]).toBe(sectionUrl(C, C))
    await answer(3, C, C)

    expect(store.start?.point).toEqual(snapped(C))
    expect(store.end).toBeNull()
    expect(store.section).toBeNull()
  })

  it('moves the start and keeps the end', async () => {
    const store = useSectionStore()
    void store.pick(A)
    await answer(1, A, A)
    void store.pick(B)
    await answer(2, snapped(A), B)

    void store.moveStart(C)

    expect(api.urls()[3]).toBe(sectionUrl(C, snapped(B)))
  })

  it('moves the start alone before there is an end', async () => {
    const store = useSectionStore()
    void store.pick(A)
    await answer(1, A, A)

    void store.moveStart(C)

    expect(api.urls()[2]).toBe(sectionUrl(C, C))
  })

  it('moves the end and keeps the start', async () => {
    const store = useSectionStore()
    void store.pick(A)
    await answer(1, A, A)
    void store.pick(B)
    await answer(2, snapped(A), B)

    void store.moveEnd(C)

    expect(api.urls()[3]).toBe(sectionUrl(snapped(A), C))
  })

  it('swaps the start and the end', async () => {
    const store = useSectionStore()
    void store.pick(A)
    await answer(1, A, A)
    void store.pick(B)
    await answer(2, snapped(A), B)

    void store.swap()

    expect(api.urls()[3]).toBe(sectionUrl(snapped(B), snapped(A)))
  })

  it('does not swap or move the end without a full selection', async () => {
    const store = useSectionStore()

    await store.swap()
    await store.moveEnd(C)

    expect(api.urls()).toHaveLength(1)
  })

  it('keeps the previous selection and shows why when the API refuses a point', async () => {
    const store = useSectionStore()
    void store.pick(A)
    await answer(1, A, A)
    const revision = store.revision

    void store.pick([52.23, 21.01])
    api.respond(
      2,
      failure("The 'to' point is 301.0 km from the river Gwda; it must be within 2 km", 422)
    )
    await flushPromises()

    expect(store.status).toBe('error')
    expect(store.error).toBe(
      "The 'to' point is 301.0 km from the river Gwda; it must be within 2 km"
    )
    expect(store.start?.point).toEqual(snapped(A))
    expect(store.end).toBeNull()
    expect(store.revision).toBe(revision + 1)
  })

  it('clears the error with the next successful request', async () => {
    const store = useSectionStore()
    void store.pick(A)
    api.respond(1, failure('too far', 422))
    await flushPromises()

    void store.pick(A)
    expect(store.error).toBeNull()
    await answer(2, A, A)

    expect(store.status).toBe('ready')
  })

  it('applies only the latest change when answers arrive out of order', async () => {
    const store = useSectionStore()
    void store.pick(A)
    await answer(1, A, A)

    void store.pick(B)
    void store.moveEnd(C)
    expect(api.fetchMock.mock.calls[2][1]?.signal?.aborted).toBe(true)

    await answer(3, snapped(A), C, 50_000)
    await answer(2, snapped(A), B, 34_000)

    expect(store.end?.point).toEqual(snapped(C))
    expect(store.section?.length).toBe(50_000)
  })

  it('clears the selection', async () => {
    const store = useSectionStore()
    void store.pick(A)
    await answer(1, A, A)

    store.clear()

    expect(store.start).toBeNull()
    expect(store.status).toBe('idle')
  })

  it('forgets the selection when another river is chosen', async () => {
    const store = useSectionStore()
    void store.pick(A)
    await answer(1, A, A)

    void useRiverStore().select(34392)
    await nextTick()

    expect(store.start).toBeNull()
    expect(store.status).toBe('idle')
  })

  it('ignores clicks while no river is loaded', async () => {
    const rivers = useRiverStore()
    rivers.clear()
    const store = useSectionStore()

    await store.pick(A)

    expect(api.urls()).toHaveLength(1)
    expect(store.status).toBe('idle')
  })
})
