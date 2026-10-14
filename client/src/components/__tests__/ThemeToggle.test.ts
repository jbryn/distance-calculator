import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ThemeToggle from '../ThemeToggle.vue'
import { resetThemeForTests } from '../../composables/useTheme'
import { mockSystemTheme } from '../../test-utils/matchMedia'

const byTestId = (id: string) => `[data-testid="${id}"]`

describe('ThemeToggle', () => {
  beforeEach(() => {
    localStorage.clear()
    document.documentElement.className = ''
    resetThemeForTests()
    mockSystemTheme(false)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('offers the system theme, light and dark, with the system theme selected', () => {
    const wrapper = mount(ThemeToggle)

    expect(wrapper.findAll('button').map((button) => button.text())).toEqual([
      'Auto',
      'Light',
      'Dark'
    ])
    expect(wrapper.find(byTestId('theme-system')).attributes('aria-pressed')).toBe('true')
    expect(wrapper.find(byTestId('theme-dark')).attributes('aria-pressed')).toBe('false')
  })

  it('switches the page to the chosen theme', async () => {
    const wrapper = mount(ThemeToggle)

    await wrapper.find(byTestId('theme-dark')).trigger('click')

    expect(document.documentElement.classList.contains('dark')).toBe(true)
    expect(wrapper.find(byTestId('theme-dark')).attributes('aria-pressed')).toBe('true')
    expect(wrapper.find(byTestId('theme-system')).attributes('aria-pressed')).toBe('false')

    await wrapper.find(byTestId('theme-light')).trigger('click')

    expect(document.documentElement.classList.contains('dark')).toBe(false)
  })

  it('is labelled for assistive technology', () => {
    const wrapper = mount(ThemeToggle)

    expect(wrapper.find('[role="group"]').attributes('aria-label')).toBe('Colour theme')
  })
})
