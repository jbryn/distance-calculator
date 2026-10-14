import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AppShell from '../layout/AppShell.vue'
import { resetThemeForTests } from '../../composables/useTheme'
import { mockSystemTheme } from '../../test-utils/matchMedia'

const byTestId = (id: string) => `[data-testid="${id}"]`

const mountShell = () =>
  mount(AppShell, {
    slots: {
      title: 'River tool',
      map: '<div data-testid="test-map">map</div>',
      panel: '<p data-testid="test-panel">controls</p>'
    }
  })

describe('AppShell', () => {
  beforeEach(() => {
    resetThemeForTests()
    mockSystemTheme(false)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('places the title, map and panel content in their areas', () => {
    const wrapper = mountShell()

    expect(wrapper.find(byTestId('app-title')).text()).toBe('River tool')
    expect(wrapper.find(byTestId('map-area')).find(byTestId('test-map')).exists()).toBe(true)
    expect(wrapper.find(byTestId('side-panel')).find(byTestId('test-panel')).exists()).toBe(true)
  })

  it('has a theme toggle in the header', () => {
    const wrapper = mountShell()

    expect(wrapper.find('header').find(byTestId('theme-toggle')).exists()).toBe(true)
  })

  it('starts with the panel open', () => {
    const wrapper = mountShell()
    const toggle = wrapper.find(byTestId('sheet-toggle'))

    expect(toggle.attributes('aria-expanded')).toBe('true')
    expect(toggle.attributes('aria-label')).toBe('Collapse panel')
    expect(wrapper.find(byTestId('panel-content')).classes()).not.toContain('max-md:hidden')
  })

  it('collapses and expands the panel on narrow screens', async () => {
    const wrapper = mountShell()
    const toggle = wrapper.find(byTestId('sheet-toggle'))

    await toggle.trigger('click')

    expect(toggle.attributes('aria-expanded')).toBe('false')
    expect(toggle.attributes('aria-label')).toBe('Expand panel')
    // Hidden only below the md breakpoint: on wide screens the panel always shows
    expect(wrapper.find(byTestId('panel-content')).classes()).toContain('max-md:hidden')

    await toggle.trigger('click')

    expect(toggle.attributes('aria-expanded')).toBe('true')
    expect(wrapper.find(byTestId('panel-content')).classes()).not.toContain('max-md:hidden')
  })

  it('links the toggle to the content it controls', () => {
    const wrapper = mountShell()

    expect(wrapper.find(byTestId('sheet-toggle')).attributes('aria-controls')).toBe(
      wrapper.find(byTestId('panel-content')).attributes('id')
    )
  })
})
