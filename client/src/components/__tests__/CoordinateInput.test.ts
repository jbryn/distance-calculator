import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CoordinateInput from '../CoordinateInput.vue'
import type { Coordinates, ValidationErrors } from '../../types/coordinates'

describe('CoordinateInput', () => {
  const defaultProps = {
    pointNumber: 1,
    coordinates: { latitude: '', longitude: '' } as Coordinates,
    errors: {} as ValidationErrors,
    onBlur: vi.fn(),
    markerColor: '#4B7BF5'
  }

  describe('rendering', () => {
    it('renders with default props', () => {
      const wrapper = mount(CoordinateInput, { props: defaultProps })
      
      expect(wrapper.find('h3').text()).toBe('Point 1')
      expect(wrapper.find('.marker').attributes('style'))
        .toContain('background-color: rgb(75, 123, 245)')
      expect(wrapper.findAll('input')).toHaveLength(2)
    })

    it('displays correct placeholders based on point number', async () => {
      const wrapper = mount(CoordinateInput, { props: defaultProps })
      
      // Point 1 placeholders
      expect(wrapper.find('#lat1').attributes('placeholder')).toBe('e.g. 51.5074')
      expect(wrapper.find('#lng1').attributes('placeholder')).toBe('e.g. -0.1278')

      // Test point 2 placeholders
      await wrapper.setProps({ pointNumber: 2 })
      expect(wrapper.find('#lat2').attributes('placeholder')).toBe('e.g. 48.8566')
      expect(wrapper.find('#lng2').attributes('placeholder')).toBe('e.g. 2.3522')
    })

    it('displays validation errors when present', () => {
      const wrapper = mount(CoordinateInput, {
        props: {
          ...defaultProps,
          errors: {
            latitude: 'Invalid latitude',
            longitude: 'Invalid longitude'
          }
        }
      })

      const errors = wrapper.findAll('.error')
      expect(errors).toHaveLength(2)
      expect(errors[0].text()).toBe('Invalid latitude')
      expect(errors[1].text()).toBe('Invalid longitude')
    })
  })

  describe('interactions', () => {
    it('updates latitude value on input', async () => {
      const coordinates = { latitude: '', longitude: '' }
      const wrapper = mount(CoordinateInput, {
        props: { ...defaultProps, coordinates }
      })

      const latitudeInput = wrapper.find('input[id="lat1"]')
      await latitudeInput.setValue('51.5074')

      expect(coordinates.latitude).toBe('51.5074')
    })

    it('updates longitude value on input', async () => {
      const coordinates = { latitude: '', longitude: '' }
      const wrapper = mount(CoordinateInput, {
        props: { ...defaultProps, coordinates }
      })

      const longitudeInput = wrapper.find('input[id="lng1"]')
      await longitudeInput.setValue('-0.1278')

      expect(coordinates.longitude).toBe('-0.1278')
    })

    it('calls onBlur when inputs lose focus', async () => {
      const onBlur = vi.fn()
      const wrapper = mount(CoordinateInput, {
        props: { ...defaultProps, onBlur }
      })

      const inputs = wrapper.findAll('input')
      
      await inputs[0].trigger('blur')
      expect(onBlur).toHaveBeenCalledTimes(1)

      await inputs[1].trigger('blur')
      expect(onBlur).toHaveBeenCalledTimes(2)
    })
  })

  describe('accessibility', () => {
    it('has correct input labels and ids', () => {
      const wrapper = mount(CoordinateInput, { props: defaultProps })
      const inputs = wrapper.findAll('input')
      const labels = wrapper.findAll('label')

      // Check latitude input
      expect(inputs[0].attributes('id')).toBe('lat1')
      expect(labels[0].attributes('for')).toBe('lat1')
      expect(labels[0].text()).toBe('Latitude')

      // Check longitude input
      expect(inputs[1].attributes('id')).toBe('lng1')
      expect(labels[1].attributes('for')).toBe('lng1')
      expect(labels[1].text()).toBe('Longitude')
    })

    it('marks inputs as required', () => {
      const wrapper = mount(CoordinateInput, { props: defaultProps })
      const inputs = wrapper.findAll('input')

      inputs.forEach(input => {
        expect(input.attributes('required')).toBeDefined()
      })
    })
  })

  describe('styling', () => {
    it('applies custom marker color', () => {
      const wrapper = mount(CoordinateInput, {
        props: { ...defaultProps, markerColor: '#22C55E' }
      })

      expect(wrapper.find('.marker').attributes('style'))
        .toContain('background-color: rgb(34, 197, 94)')
    })

    it('maintains consistent layout with and without errors', async () => {
      const wrapper = mount(CoordinateInput, { props: defaultProps })
      const initialHeight = (wrapper.find('.point-container').element as HTMLElement).style.height

      await wrapper.setProps({
        errors: {
          latitude: 'Error message',
          longitude: 'Error message'
        }
      })

      expect((wrapper.find('.point-container').element as HTMLElement).style.height)
        .toBe(initialHeight)
    })
  })
})