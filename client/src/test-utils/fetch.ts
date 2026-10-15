import { vi } from 'vitest'
import type { LatLng, River, Section } from '../types/river'

export const jsonResponse = (body: unknown, status = 200) =>
  new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })

export const success = (data: unknown) => jsonResponse({ status: 'success', data })

export const failure = (message: string, status: number) =>
  jsonResponse({ status: 'error', message }, status)

/**
 * Replaces fetch with one whose responses the test releases one by one, in any order.
 * Like the real fetch, a request rejects with an AbortError when its signal is aborted.
 */
export function controlledFetch() {
  const pending: { url: string; resolve: (response: Response) => void }[] = []

  const fetchMock = vi.fn(
    (url: string, init?: RequestInit) =>
      new Promise<Response>((resolve, reject) => {
        pending.push({ url, resolve })
        init?.signal?.addEventListener('abort', () =>
          reject(new DOMException('The operation was aborted.', 'AbortError'))
        )
      })
  )

  vi.stubGlobal('fetch', fetchMock)

  return {
    fetchMock,
    /** URLs requested so far */
    urls: () => fetchMock.mock.calls.map(([url]) => url),
    /** Answers the request made n-th (0 = first) */
    respond(index: number, response: Response) {
      pending[index].resolve(response)
    }
  }
}

export const gwda: River = {
  id: 8974372,
  name: 'Gwda',
  length: 142928.6,
  points: [
    [53.85058, 16.669742],
    [53.4, 16.8],
    [53.0548523, 16.7259211]
  ],
  features: [
    {
      kind: 'bridge',
      category: 'access',
      name: null,
      point: [53.8505192, 16.6694025],
      distanceFromSource: 23.8
    },
    {
      kind: 'weir',
      category: 'obstacle',
      name: 'Jaz',
      point: [53.4, 16.8],
      distanceFromSource: 50000
    },
    {
      kind: 'dam',
      category: 'obstacle',
      name: null,
      point: [53.3, 16.8],
      distanceFromSource: 60000
    }
  ]
}

/**
 * Lets pending responses be read and promise callbacks run. Uses setImmediate, which
 * tests never fake: reading a Response body depends on it.
 */
export const flushPromises = async () => {
  for (let i = 0; i < 5; i++) {
    await new Promise((resolve) => setImmediate(resolve))
  }
}

/** A section of the Gwda as the API would describe it */
export const gwdaSection = (from: LatLng, to: LatLng, length = 1000): Section => ({
  river: { id: gwda.id, name: gwda.name },
  length,
  downstream: true,
  from: { point: from, distanceFromSource: 1000, offset: 10 },
  to: { point: to, distanceFromSource: 1000 + length, offset: 10 },
  points: [from, to],
  features: []
})
