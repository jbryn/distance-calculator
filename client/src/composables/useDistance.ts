import { ref } from 'vue'
import type { Coordinates, DistanceResult } from '../types/coordinates'

export function useDistance() {
  const distance = ref<DistanceResult | null>(null)
  const isLoading = ref(false)
  const apiError = ref<string | null>(null)

  const calculateDistance = async (point1: Coordinates, point2: Coordinates): Promise<DistanceResult> => {
    try {
      const response = await fetch('http://localhost:8080/api/calculate-distance', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          lat1: Number(point1.latitude),
          lon1: Number(point1.longitude),
          lat2: Number(point2.latitude),
          lon2: Number(point2.longitude)
        })
      })
  
      if (!response.ok) {
        throw new Error('Failed to calculate distance')
      }
  
      const data = await response.json()
      
      const { meters, kilometers } = data.data ?? {}

      if (data.status !== 'success' || typeof meters !== 'number' || typeof kilometers !== 'number') {
        throw new Error('Invalid response format')
      }
  
      return { meters, kilometers }
    } catch (error) {
      console.error('API Error:', error)
      if (error instanceof Error) {
        throw error
      }
      throw new Error('Failed to calculate distance')
    }
  }

  return {
    distance,
    isLoading,
    apiError,
    calculateDistance
  }
}