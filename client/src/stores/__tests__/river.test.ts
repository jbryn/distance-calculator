import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useRiverStore } from '../river'
import { controlledFetch, failure, flushPromises, gwda, success } from '../../test-utils/fetch'

const wisla = { ...gwda, id: 34392, name: 'Wisła' }

describe('river store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('starts with no river', () => {
    const store = useRiverStore()

    expect(store.river).toBeNull()
    expect(store.selectedId).toBeNull()
    expect(store.status).toBe('idle')
  })

  it('loads the selected river', async () => {
    const api = controlledFetch()
    const store = useRiverStore()

    const loading = store.select(8974372)

    expect(store.status).toBe('loading')
    expect(store.selectedId).toBe(8974372)
    expect(api.urls()).toEqual(['http://localhost:8080/api/rivers/8974372'])

    api.respond(0, success(gwda))
    await loading

    expect(store.status).toBe('ready')
    expect(store.river).toEqual(gwda)
  })

  it('keeps the river chosen last when an earlier one arrives later', async () => {
    const api = controlledFetch()
    const store = useRiverStore()

    void store.select(8974372)
    const second = store.select(34392)

    expect(api.fetchMock.mock.calls[0][1]?.signal?.aborted).toBe(true)

    api.respond(1, success(wisla))
    await second
    api.respond(0, success(gwda))
    await flushPromises()

    expect(store.selectedId).toBe(34392)
    expect(store.river?.name).toBe('Wisła')
    expect(store.status).toBe('ready')
  })

  it('reports a river that cannot be loaded, and can try again', async () => {
    const api = controlledFetch()
    const store = useRiverStore()

    const loading = store.select(8974372)
    api.respond(0, failure('The data for this river is unavailable', 503))
    await loading

    expect(store.status).toBe('error')
    expect(store.error).toBe('The data for this river is unavailable')
    expect(store.river).toBeNull()

    const retrying = store.retry()
    expect(store.status).toBe('loading')
    expect(store.error).toBeNull()

    api.respond(1, success(gwda))
    await retrying

    expect(store.status).toBe('ready')
    expect(api.urls()).toEqual([
      'http://localhost:8080/api/rivers/8974372',
      'http://localhost:8080/api/rivers/8974372'
    ])
  })

  it('clears the river and drops a request still on its way', async () => {
    const api = controlledFetch()
    const store = useRiverStore()

    void store.select(8974372)
    store.clear()

    expect(store.status).toBe('idle')
    expect(store.selectedId).toBeNull()
    expect(api.fetchMock.mock.calls[0][1]?.signal?.aborted).toBe(true)

    api.respond(0, success(gwda))
    await flushPromises()

    expect(store.river).toBeNull()
    expect(store.status).toBe('idle')
  })

  it('does nothing on retry when no river is selected', async () => {
    const api = controlledFetch()
    const store = useRiverStore()

    await store.retry()

    expect(api.urls()).toEqual([])
  })
})
