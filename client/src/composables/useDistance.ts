import { ref } from 'vue'
import type { Coordinates } from '../types/coordinates'

export function useDistance() {
  const distance = ref<number | null>(null)
  const isLoading = ref(false)
  const apiError = ref<string | null>(null)

  const calculateDistance = async (point1: Coordinates, point2: Coordinates) => {
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
      
      if (data.status !== 'success' || !data.data?.kilometers) {
        throw new Error('Invalid response format')
      }

      return data.data.kilometers
    } catch (error) {
      console.error('API Error:', error)
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