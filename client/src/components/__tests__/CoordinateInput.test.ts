import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CoordinateInput from '../CoordinateInput.vue'
import type { Coordinates, ValidationErrors } from '../../types/coordinates'

const byTestId = (id: string) => `[data-testid="${id}"]`

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

      expect(wrapper.find(byTestId('point-title-1')).text()).toBe('Point 1')
      expect(wrapper.find(byTestId('marker-1')).attributes('style')).toContain(
        'background-color: rgb(75, 123, 245)'
      )
      expect(wrapper.find(byTestId('latitude-1')).exists()).toBe(true)
      expect(wrapper.find(byTestId('longitude-1')).exists()).toBe(true)
    })

    it('displays correct placeholders based on point number', async () => {
      const wrapper = mount(CoordinateInput, { props: defaultProps })

      expect(wrapper.find(byTestId('latitude-1')).attributes('placeholder')).toBe('e.g. 51.5074')
      expect(wrapper.find(byTestId('longitude-1')).attributes('placeholder')).toBe('e.g. -0.1278')

      await wrapper.setProps({ pointNumber: 2 })
      expect(wrapper.find(byTestId('latitude-2')).attributes('placeholder')).toBe('e.g. 48.8566')
      expect(wrapper.find(byTestId('longitude-2')).attributes('placeholder')).toBe('e.g. 2.3522')
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

      expect(wrapper.find(byTestId('latitude-error-1')).text()).toBe('Invalid latitude')
      expect(wrapper.find(byTestId('longitude-error-1')).text()).toBe('Invalid longitude')
    })

    it('shows no error messages without errors', () => {
      const wrapper = mount(CoordinateInput, { props: defaultProps })

      expect(wrapper.find(byTestId('latitude-error-1')).exists()).toBe(false)
      expect(wrapper.find(byTestId('longitude-error-1')).exists()).toBe(false)
    })
  })

  describe('interactions', () => {
    it('updates latitude value on input', async () => {
      const coordinates = { latitude: '', longitude: '' }
      const wrapper = mount(CoordinateInput, {
        props: { ...defaultProps, coordinates }
      })

      await wrapper.find(byTestId('latitude-1')).setValue('51.5074')

      expect(coordinates.latitude).toBe('51.5074')
    })

    it('updates longitude value on input', async () => {
      const coordinates = { latitude: '', longitude: '' }
      const wrapper = mount(CoordinateInput, {
        props: { ...defaultProps, coordinates }
      })

      await wrapper.find(byTestId('longitude-1')).setValue('-0.1278')

      expect(coordinates.longitude).toBe('-0.1278')
    })

    it('calls onBlur when inputs lose focus', async () => {
      const onBlur = vi.fn()
      const wrapper = mount(CoordinateInput, {
        props: { ...defaultProps, onBlur }
      })

      await wrapper.find(byTestId('latitude-1')).trigger('blur')
      expect(onBlur).toHaveBeenCalledTimes(1)

      await wrapper.find(byTestId('longitude-1')).trigger('blur')
      expect(onBlur).toHaveBeenCalledTimes(2)
    })
  })

  describe('accessibility', () => {
    it('has correct input labels and ids', () => {
      const wrapper = mount(CoordinateInput, { props: defaultProps })
      const labels = wrapper.findAll('label')

      expect(wrapper.find(byTestId('latitude-1')).attributes('id')).toBe('lat1')
      expect(labels[0].attributes('for')).toBe('lat1')
      expect(labels[0].text()).toBe('Latitude')

      expect(wrapper.find(byTestId('longitude-1')).attributes('id')).toBe('lng1')
      expect(labels[1].attributes('for')).toBe('lng1')
      expect(labels[1].text()).toBe('Longitude')
    })

    it('marks inputs as required', () => {
      const wrapper = mount(CoordinateInput, { props: defaultProps })

      expect(wrapper.find(byTestId('latitude-1')).attributes('required')).toBeDefined()
      expect(wrapper.find(byTestId('longitude-1')).attributes('required')).toBeDefined()
    })

    it('groups the two inputs of a point under its title', () => {
      const wrapper = mount(CoordinateInput, { props: defaultProps })

      expect(wrapper.find(byTestId('point-1')).element.tagName).toBe('FIELDSET')
      expect(wrapper.find('legend').text()).toBe('Point 1')
    })
  })

  describe('styling', () => {
    it('applies custom marker color', () => {
      const wrapper = mount(CoordinateInput, {
        props: { ...defaultProps, markerColor: '#22C55E' }
      })

      expect(wrapper.find(byTestId('marker-1')).attributes('style')).toContain(
        'background-color: rgb(34, 197, 94)'
      )
    })
  })
})
