import type { Coordinates, ValidationErrors } from '../types/coordinates'

export function useCoordinateValidation() {
  const validateCoordinate = (value: number, type: 'latitude' | 'longitude'): string | undefined => {
    // Check if value is default/empty (0)
    if (value === 0) {
      return `Please enter a non-zero ${type}`
    }
    
    if (isNaN(value)) {
      return 'Must be a valid number'
    }
    
    if (type === 'latitude') {
      if (value < -90 || value > 90) {
        return 'Latitude must be between -90 and 90 degrees'
      }
    } else {
      if (value < -180 || value > 180) {
        return 'Longitude must be between -180 and 180 degrees'
      }
    }
    
    const decimalPlaces = value.toString().split('.')[1]?.length || 0
    if (decimalPlaces > 6) {
      return 'Maximum 6 decimal places allowed'
    }
    
    return undefined
  }

  const validatePoint = (point: Coordinates): ValidationErrors => {
    const errors: ValidationErrors = {}
    
    errors.latitude = validateCoordinate(point.latitude, 'latitude')
    errors.longitude = validateCoordinate(point.longitude, 'longitude')
    
    return errors
  }

  return {
    validateCoordinate,
    validatePoint
  }
}