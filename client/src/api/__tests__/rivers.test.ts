import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, getRiver, isAbort, searchRivers } from '../rivers'
import { failure, gwda, jsonResponse, success } from '../../test-utils/fetch'

const fetchMock = vi.fn<typeof fetch>()

describe('rivers API client', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', fetchMock)
    fetchMock.mockReset()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.unstubAllEnvs()
  })

  describe('searchRivers', () => {
    it('asks for rivers matching the query and returns them', async () => {
      fetchMock.mockResolvedValue(success([{ id: 8974372, name: 'Gwda' }]))

      await expect(searchRivers('gwda')).resolves.toEqual([{ id: 8974372, name: 'Gwda' }])
      expect(fetchMock).toHaveBeenCalledWith('http://localhost:8080/api/rivers?q=gwda', {
        signal: undefined
      })
    })

    it('trims and encodes the query', async () => {
      fetchMock.mockResolvedValue(success([]))

      await searchRivers('  Noteć & co ')

      expect(fetchMock.mock.calls[0][0]).toBe(
        'http://localhost:8080/api/rivers?q=Note%C4%87%20%26%20co'
      )
    })

    it('uses the configured API address', async () => {
      vi.stubEnv('VITE_API_URL', 'https://api.example.com/')
      fetchMock.mockResolvedValue(success([]))

      await searchRivers('')

      expect(fetchMock.mock.calls[0][0]).toBe('https://api.example.com/api/rivers?q=')
    })

    it('passes the abort signal on', async () => {
      fetchMock.mockResolvedValue(success([]))
      const controller = new AbortController()

      await searchRivers('gwda', controller.signal)

      expect(fetchMock.mock.calls[0][1]).toEqual({ signal: controller.signal })
    })

    it('rejects a list that is not made of rivers', async () => {
      fetchMock.mockResolvedValue(success([{ id: '1', name: 'Gwda' }]))

      await expect(searchRivers('gwda')).rejects.toThrow(
        'The server sent a response this page does not understand.'
      )
    })
  })

  describe('getRiver', () => {
    it('returns the river', async () => {
      fetchMock.mockResolvedValue(success(gwda))

      await expect(getRiver(8974372)).resolves.toEqual(gwda)
      expect(fetchMock.mock.calls[0][0]).toBe('http://localhost:8080/api/rivers/8974372')
    })

    it.each([
      ['a missing length', { ...gwda, length: undefined }],
      ['a single point', { ...gwda, points: [[53.8, 16.6]] }],
      ['a point that is not a pair', { ...gwda, points: [[53.8, 16.6], [53.4]] }],
      ['features missing', { ...gwda, features: undefined }],
      [
        'an unknown kind of feature',
        { ...gwda, features: [{ ...gwda.features[0], kind: 'ferry' }] }
      ],
      ['an unknown category', { ...gwda, features: [{ ...gwda.features[0], category: 'other' }] }]
    ])('rejects a river with %s', async (_case, river) => {
      fetchMock.mockResolvedValue(success(river))

      await expect(getRiver(8974372)).rejects.toThrow(ApiError)
    })
  })

  describe('errors', () => {
    it('reports the message the API gives', async () => {
      fetchMock.mockResolvedValue(failure('River not found', 404))

      const error = await searchRivers('gwda').catch((e: unknown) => e)

      expect(error).toBeInstanceOf(ApiError)
      expect(error).toMatchObject({ message: 'River not found', status: 404 })
    })

    it('reports the status when the error response has no message', async () => {
      fetchMock.mockResolvedValue(new Response('Bad gateway', { status: 502 }))

      await expect(getRiver(1)).rejects.toMatchObject({
        message: 'The server answered with an error (502).',
        status: 502
      })
    })

    it('reports an unreachable server', async () => {
      fetchMock.mockRejectedValue(new TypeError('Failed to fetch'))

      await expect(searchRivers('gwda')).rejects.toMatchObject({
        message: 'Could not reach the server. Check your connection and try again.',
        status: null
      })
    })

    it('reports a successful response that is not the expected JSON', async () => {
      fetchMock.mockResolvedValue(new Response('<html>', { status: 200 }))

      await expect(searchRivers('gwda')).rejects.toMatchObject({
        message: 'The server sent a response this page does not understand.'
      })
    })

    it('reports a response without data', async () => {
      fetchMock.mockResolvedValue(jsonResponse({ status: 'success' }))

      await expect(searchRivers('gwda')).rejects.toThrow(ApiError)
    })

    it('lets an aborted request reject as an abort, not as a failure', async () => {
      fetchMock.mockRejectedValue(new DOMException('aborted', 'AbortError'))

      const error = await searchRivers('gwda').catch((e: unknown) => e)

      expect(isAbort(error)).toBe(true)
      expect(error).not.toBeInstanceOf(ApiError)
    })
  })
})
