import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { nextTick } from 'vue'
import { useTheme, resetThemeForTests, THEME_STORAGE_KEY } from '../useTheme'
import { mockSystemTheme } from '../../test-utils/matchMedia'

const isDark = () => document.documentElement.classList.contains('dark')

describe('useTheme', () => {
  beforeEach(() => {
    localStorage.clear()
    document.documentElement.className = ''
    resetThemeForTests()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('follows the system theme by default', () => {
    mockSystemTheme(true)

    const { preference } = useTheme()

    expect(preference.value).toBe('system')
    expect(isDark()).toBe(true)
    expect(document.documentElement.style.colorScheme).toBe('dark')
  })

  it('switches when the system theme changes while following it', () => {
    const system = mockSystemTheme(false)
    useTheme()
    expect(isDark()).toBe(false)

    system.setSystemDark(true)
    expect(isDark()).toBe(true)

    system.setSystemDark(false)
    expect(isDark()).toBe(false)
  })

  it('applies a chosen theme regardless of the system theme', async () => {
    const system = mockSystemTheme(true)
    const { preference } = useTheme()

    preference.value = 'light'
    await nextTick()
    expect(isDark()).toBe(false)
    expect(document.documentElement.style.colorScheme).toBe('light')

    system.setSystemDark(true)
    expect(isDark()).toBe(false)

    preference.value = 'dark'
    await nextTick()
    expect(isDark()).toBe(true)
  })

  it('remembers a chosen theme', async () => {
    mockSystemTheme(false)
    const { preference } = useTheme()

    preference.value = 'dark'
    await nextTick()

    expect(localStorage.getItem(THEME_STORAGE_KEY)).toBe('dark')
  })

  it('forgets the choice when going back to the system theme', async () => {
    mockSystemTheme(false)
    localStorage.setItem(THEME_STORAGE_KEY, 'dark')
    const { preference } = useTheme()

    preference.value = 'system'
    await nextTick()

    expect(localStorage.getItem(THEME_STORAGE_KEY)).toBeNull()
  })

  it('starts from the remembered theme', () => {
    mockSystemTheme(false)
    localStorage.setItem(THEME_STORAGE_KEY, 'dark')

    const { preference } = useTheme()

    expect(preference.value).toBe('dark')
    expect(isDark()).toBe(true)
  })

  it('ignores a remembered value it does not recognise', () => {
    mockSystemTheme(false)
    localStorage.setItem(THEME_STORAGE_KEY, 'sepia')

    expect(useTheme().preference.value).toBe('system')
  })

  it('works when storage is unavailable', async () => {
    mockSystemTheme(true)
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('blocked')
    })
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('blocked')
    })

    const { preference } = useTheme()
    expect(preference.value).toBe('system')

    preference.value = 'light'
    await nextTick()
    expect(isDark()).toBe(false)
  })

  it('shares one preference between all callers', () => {
    mockSystemTheme(false)

    expect(useTheme().preference).toBe(useTheme().preference)
  })
})
