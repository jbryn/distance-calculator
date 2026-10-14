import { ref, watch, type Ref } from 'vue'

export type ThemePreference = 'system' | 'light' | 'dark'

export const THEME_STORAGE_KEY = 'theme'

const DARK_QUERY = '(prefers-color-scheme: dark)'

const isPreference = (value: unknown): value is ThemePreference =>
  value === 'system' || value === 'light' || value === 'dark'

const readStoredPreference = (): ThemePreference => {
  try {
    const stored = localStorage.getItem(THEME_STORAGE_KEY)
    return isPreference(stored) ? stored : 'system'
  } catch {
    // Storage can be unavailable, for example in private browsing
    return 'system'
  }
}

const storePreference = (preference: ThemePreference): void => {
  try {
    if (preference === 'system') {
      localStorage.removeItem(THEME_STORAGE_KEY)
    } else {
      localStorage.setItem(THEME_STORAGE_KEY, preference)
    }
  } catch {
    // Not remembering the choice is acceptable
  }
}

let preference: Ref<ThemePreference> | null = null

/**
 * The colour theme: follows the operating system unless the user picks light or dark.
 * The choice is remembered in localStorage and applied as a "dark" class on <html>.
 *
 * All callers share one preference.
 */
export function useTheme() {
  if (preference === null) {
    preference = ref(readStoredPreference())

    const media = window.matchMedia(DARK_QUERY)
    const current = preference

    const apply = () => {
      const dark = current.value === 'dark' || (current.value === 'system' && media.matches)
      document.documentElement.classList.toggle('dark', dark)
      document.documentElement.style.colorScheme = dark ? 'dark' : 'light'
    }

    media.addEventListener('change', apply)
    watch(current, (value) => {
      storePreference(value)
      apply()
    })
    apply()
  }

  return { preference }
}

/** Forgets the shared preference; only for tests */
export function resetThemeForTests(): void {
  preference = null
}
