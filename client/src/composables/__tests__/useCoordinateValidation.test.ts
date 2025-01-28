import { describe, it, expect } from 'vitest'
import { useCoordinateValidation } from '../useCoordinateValidation'

describe('useCoordinateValidation', () => {
  const { validateCoordinate, validatePoint } = useCoordinateValidation()

  describe('validateCoordinate', () => {
    describe('latitude validation', () => {
      it('handles empty values', () => {
        expect(validateCoordinate('', 'latitude')).toBe('Latitude is required')
        expect(validateCoordinate('  ', 'latitude')).toBe('Latitude is required')
      })

      it('validates decimal format', () => {
        expect(validateCoordinate('51', 'latitude')).toBe('Must be a decimal number with 4-6 decimal places')
        expect(validateCoordinate('51.123', 'latitude')).toBe('Must be a decimal number with 4-6 decimal places')
        expect(validateCoordinate('51.1234', 'latitude')).toBeUndefined()
        expect(validateCoordinate('51.12345', 'latitude')).toBeUndefined()
        expect(validateCoordinate('51.123456', 'latitude')).toBeUndefined()
        expect(validateCoordinate('51.1234567', 'latitude')).toBe('Must be a decimal number with 4-6 decimal places')
      })

      it('validates latitude range', () => {
        expect(validateCoordinate('-91.1234', 'latitude')).toBe('Latitude must be between -90 and 90 degrees')
        expect(validateCoordinate('91.1234', 'latitude')).toBe('Latitude must be between -90 and 90 degrees')
        expect(validateCoordinate('-90.0000', 'latitude')).toBeUndefined()
        expect(validateCoordinate('90.0000', 'latitude')).toBeUndefined()
      })
    })

    describe('longitude validation', () => {
      it('handles empty values', () => {
        expect(validateCoordinate('', 'longitude')).toBe('Longitude is required')
        expect(validateCoordinate('  ', 'longitude')).toBe('Longitude is required')
      })

      it('validates decimal format', () => {
        expect(validateCoordinate('51', 'longitude')).toBe('Must be a decimal number with 4-6 decimal places')
        expect(validateCoordinate('51.123', 'longitude')).toBe('Must be a decimal number with 4-6 decimal places')
        expect(validateCoordinate('51.1234', 'longitude')).toBeUndefined()
        expect(validateCoordinate('51.12345', 'longitude')).toBeUndefined()
        expect(validateCoordinate('51.123456', 'longitude')).toBeUndefined()
        expect(validateCoordinate('51.1234567', 'longitude')).toBe('Must be a decimal number with 4-6 decimal places')
      })

      it('validates longitude range', () => {
        expect(validateCoordinate('-181.1234', 'longitude')).toBe('Longitude must be between -180 and 180 degrees')
        expect(validateCoordinate('181.1234', 'longitude')).toBe('Longitude must be between -180 and 180 degrees')
        expect(validateCoordinate('-180.0000', 'longitude')).toBeUndefined()
        expect(validateCoordinate('180.0000', 'longitude')).toBeUndefined()
      })
    })
  })

  describe('validatePoint', () => {
    it('validates a complete valid point', () => {
      const point = {
        latitude: '51.5074',
        longitude: '-0.1278'
      }
      const errors = validatePoint(point)
      expect(errors.latitude).toBeUndefined()
      expect(errors.longitude).toBeUndefined()
    })

    it('validates a complete invalid point', () => {
      const point = {
        latitude: '91.5074',
        longitude: '181.1278'
      }
      const errors = validatePoint(point)
      expect(errors.latitude).toBe('Latitude must be between -90 and 90 degrees')
      expect(errors.longitude).toBe('Longitude must be between -180 and 180 degrees')
    })
  })
})