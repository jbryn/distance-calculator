import type { Coordinates, ValidationErrors } from '../types/coordinates'

export function useCoordinateValidation() {
  const validateCoordinate = (value: string, type: 'latitude' | 'longitude'): string | undefined => {
    // Check for empty value
    if (!value.trim()) {
      return `${type.charAt(0).toUpperCase() + type.slice(1)} is required`
    }

    // Check for valid format: optional minus, digits, decimal point, 4-6 decimal places
    if (!/^\-?\d+\.\d{4,6}$/.test(value)) {
      return 'Must be a decimal number with 4-6 decimal places'
    }

    const num = Number(value)
    const limits = type === 'latitude' ? 90 : 180
    if (num < -limits || num > limits) {
      return `${type.charAt(0).toUpperCase() + type.slice(1)} must be between -${limits} and ${limits} degrees`
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