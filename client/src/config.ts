const DEFAULT_API_URL = 'http://localhost:8080'

export const getApiUrl = (): string =>
  (import.meta.env.VITE_API_URL || DEFAULT_API_URL).replace(/\/+$/, '')

/** OpenStreetMap's standard tiles; their usage policy requires the attribution below */
export const TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'

export const TILE_ATTRIBUTION =
  '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
