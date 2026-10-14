import { vi } from 'vitest'

/**
 * Replaces window.matchMedia (missing in jsdom) with one whose answer to
 * "prefers-color-scheme: dark" the test controls.
 */
export function mockSystemTheme(initiallyDark: boolean) {
  let dark = initiallyDark
  const listeners = new Set<() => void>()

  const media = {
    get matches() {
      return dark
    },
    media: '(prefers-color-scheme: dark)',
    addEventListener: (_type: string, listener: () => void) => listeners.add(listener),
    removeEventListener: (_type: string, listener: () => void) => listeners.delete(listener)
  }

  vi.stubGlobal(
    'matchMedia',
    vi.fn(() => media)
  )

  return {
    /** Simulates the user switching their operating system between light and dark */
    setSystemDark(value: boolean) {
      dark = value
      listeners.forEach((listener) => listener())
    }
  }
}
