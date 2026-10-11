const DEFAULT_API_URL = 'http://localhost:8080'

export const getApiUrl = (): string =>
  (import.meta.env.VITE_API_URL || DEFAULT_API_URL).replace(/\/+$/, '')
