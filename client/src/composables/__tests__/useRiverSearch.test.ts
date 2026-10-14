import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectScope, nextTick } from 'vue'
import { useRiverSearch, SEARCH_DELAY_MS } from '../useRiverSearch'
import { controlledFetch, failure, flushPromises, success } from '../../test-utils/fetch'

const GWDA = { id: 8974372, name: 'Gwda' }
const WISLA = { id: 34392, name: 'Wisła' }

describe('useRiverSearch', () => {
  let scope: ReturnType<typeof effectScope>

  const start = () => {
    scope = effectScope()
    return scope.run(() => useRiverSearch())!
  }

  /** Changes the query and lets the pause before searching pass */
  const typeAndWait = async (search: ReturnType<typeof start>, text: string) => {
    search.query.value = text
    await nextTick()
    vi.advanceTimersByTime(SEARCH_DELAY_MS)
  }

  beforeEach(() => {
    // Only the search delay is faked; reading responses needs the real setImmediate
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
  })

  afterEach(() => {
    scope?.stop()
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('lists every river straight away', async () => {
    const api = controlledFetch()
    const search = start()

    expect(api.urls()).toEqual(['http://localhost:8080/api/rivers?q='])
    expect(search.status.value).toBe('loading')

    api.respond(0, success([GWDA, WISLA]))
    await flushPromises()

    expect(search.results.value).toEqual([GWDA, WISLA])
    expect(search.status.value).toBe('done')
  })

  it('waits for a pause in typing before searching', async () => {
    const api = controlledFetch()
    const search = start()

    for (const text of ['g', 'gw', 'gwd', 'gwda']) {
      search.query.value = text
      await nextTick()
      vi.advanceTimersByTime(SEARCH_DELAY_MS - 50)
    }
    expect(api.urls()).toHaveLength(1)

    vi.advanceTimersByTime(50)

    expect(api.urls()).toEqual([
      'http://localhost:8080/api/rivers?q=',
      'http://localhost:8080/api/rivers?q=gwda'
    ])
  })

  it('shows the answer to the latest query even when an older one arrives later', async () => {
    const api = controlledFetch()
    const search = start()

    await typeAndWait(search, 'w')
    await typeAndWait(search, 'gwda')

    // The answer to "gwda" comes first, then the slower answer to "w"
    api.respond(2, success([GWDA]))
    api.respond(1, success([WISLA]))
    await flushPromises()

    expect(search.results.value).toEqual([GWDA])
    expect(search.status.value).toBe('done')
  })

  it('cancels a request that has been overtaken', async () => {
    const api = controlledFetch()
    const search = start()

    await typeAndWait(search, 'w')

    const firstSignal = api.fetchMock.mock.calls[0][1]?.signal
    expect(firstSignal?.aborted).toBe(true)

    await typeAndWait(search, 'gwda')
    expect(api.fetchMock.mock.calls[1][1]?.signal?.aborted).toBe(true)
    expect(api.fetchMock.mock.calls[2][1]?.signal?.aborted).toBe(false)
  })

  it('reports a failed search and can try again', async () => {
    const api = controlledFetch()
    const search = start()

    api.respond(0, failure('The data for this river is unavailable', 503))
    await flushPromises()

    expect(search.status.value).toBe('error')
    expect(search.error.value).toBe('The data for this river is unavailable')
    expect(search.results.value).toEqual([])

    void search.retry()
    expect(search.status.value).toBe('loading')
    expect(search.error.value).toBeNull()

    api.respond(1, success([GWDA]))
    await flushPromises()

    expect(search.status.value).toBe('done')
    expect(search.results.value).toEqual([GWDA])
  })

  it('stops searching when its owner goes away', async () => {
    const api = controlledFetch()
    const search = start()

    search.query.value = 'gwda'
    await nextTick()
    scope.stop()
    vi.advanceTimersByTime(SEARCH_DELAY_MS)
    await flushPromises()

    expect(api.urls()).toHaveLength(1)
    expect(api.fetchMock.mock.calls[0][1]?.signal?.aborted).toBe(true)
  })
})
