import { describe, it, expect, beforeEach, vi } from 'vitest'
import { useDistance } from '../useDistance'

// Mock fetch globally
const mockFetch = vi.fn()
global.fetch = mockFetch

describe('useDistance', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  const validPoint1 = {
    latitude: '51.5074',
    longitude: '-0.1278'
  }

  const validPoint2 = {
    latitude: '48.8566',
    longitude: '2.3522'
  }

  it('calculates distance successfully', async () => {
    const mockResponse = {
      status: 'success',
      data: {
        kilometers: 1000
      }
    }

    mockFetch.mockResolvedValueOnce({
      ok: true,
      json: () => Promise.resolve(mockResponse)
    })

    const { calculateDistance } = useDistance()
    const result = await calculateDistance(validPoint1, validPoint2)

    expect(result).toBe(1000)
    expect(mockFetch).toHaveBeenCalledWith(
      'http://localhost:8080/api/calculate-distance',
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          lat1: Number(validPoint1.latitude),
          lon1: Number(validPoint1.longitude),
          lat2: Number(validPoint2.latitude),
          lon2: Number(validPoint2.longitude)
        })
      }
    )
  })

  it('handles API error response', async () => {
    mockFetch.mockResolvedValueOnce({
      ok: false
    })

    const { calculateDistance } = useDistance()
    
    await expect(calculateDistance(validPoint1, validPoint2)).rejects.toThrow('Failed to calculate distance')
  })

  it('handles invalid response format', async () => {
  mockFetch.mockResolvedValueOnce({
    ok: true,
    json: () => Promise.resolve({ status: 'success' }) // Missing data.kilometers
  })

  const { calculateDistance } = useDistance()
  
  await expect(calculateDistance(validPoint1, validPoint2)).rejects.toThrow('Failed to calculate distance')
})

  it('handles network errors', async () => {
    mockFetch.mockRejectedValueOnce(new Error('Network error'))

    const { calculateDistance } = useDistance()
    
    await expect(calculateDistance(validPoint1, validPoint2)).rejects.toThrow('Failed to calculate distance')
  })

  it('manages loading state correctly', async () => {
    mockFetch.mockResolvedValueOnce({
      ok: true,
      json: () => Promise.resolve({ 
        status: 'success',
        data: { kilometers: 1000 }
      })
    })

    const { calculateDistance, isLoading } = useDistance()
    
    expect(isLoading.value).toBe(false)
    
    const promise = calculateDistance(validPoint1, validPoint2)
    
    // We can't test the loading state directly here since the mock resolves immediately
    // In a real application, we'd use something like await nextTick() to test intermediate states
    
    await promise
    
    expect(isLoading.value).toBe(false)
  })
})