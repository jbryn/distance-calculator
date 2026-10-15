import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, getRiver, getSection, isAbort, searchRivers } from '../rivers'
import { failure, gwda, gwdaSection, jsonResponse, success } from '../../test-utils/fetch'

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

describe('getSection', () => {
  const fetchSection = vi.fn<typeof fetch>()

  beforeEach(() => {
    vi.stubGlobal('fetch', fetchSection)
    fetchSection.mockReset()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('asks for the section between two points and returns it', async () => {
    const section = gwdaSection([53.3665124, 16.7965265], [53.0548549, 16.7260184], 57810.7)
    fetchSection.mockResolvedValue(success(section))

    await expect(getSection(8974372, [53.3667, 16.7964], [53.0551, 16.726])).resolves.toEqual(
      section
    )
    expect(fetchSection.mock.calls[0][0]).toBe(
      'http://localhost:8080/api/rivers/8974372/section?from=53.3667,16.7964&to=53.0551,16.726'
    )
  })

  it.each([
    ['without a direction', { downstream: undefined }],
    ['with a start that is not a snapped point', { from: { point: [53.3, 16.7] } }],
    [
      'with a feature measured from the source instead of the start',
      {
        features: [
          {
            kind: 'weir',
            category: 'obstacle',
            name: null,
            point: [53.3, 16.7],
            distanceFromSource: 5
          }
        ]
      }
    ]
  ])('rejects a section %s', async (_case, change) => {
    fetchSection.mockResolvedValue(
      success({ ...gwdaSection([53.3, 16.7], [53.0, 16.7]), ...change })
    )

    await expect(getSection(8974372, [53.3, 16.7], [53.0, 16.7])).rejects.toThrow(ApiError)
  })

  it('reports why the API refused a point', async () => {
    fetchSection.mockResolvedValue(
      failure("The 'to' point is 301.0 km from the river Gwda; it must be within 2 km", 422)
    )

    await expect(getSection(8974372, [53.3, 16.7], [52.2, 21.0])).rejects.toMatchObject({
      status: 422,
      message: "The 'to' point is 301.0 km from the river Gwda; it must be within 2 km"
    })
  })
})
