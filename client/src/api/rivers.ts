import { getApiUrl } from '../config'
import type {
  FeatureKind,
  LatLng,
  River,
  RiverFeature,
  RiverSummary,
  Section,
  SectionFeature,
  SnappedPoint
} from '../types/river'

/** A request that reached the server, or tried to, and did not give a usable answer */
export class ApiError extends Error {
  constructor(
    message: string,
    /** HTTP status, or null when the server could not be reached */
    readonly status: number | null
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

export const isAbort = (error: unknown): boolean =>
  error instanceof DOMException && error.name === 'AbortError'

export async function searchRivers(query: string, signal?: AbortSignal): Promise<RiverSummary[]> {
  const data = await getData(`/api/rivers?q=${encodeURIComponent(query.trim())}`, signal)

  if (!Array.isArray(data) || !data.every(isRiverSummary)) {
    throw unexpected()
  }

  return data
}

export async function getRiver(id: number, signal?: AbortSignal): Promise<River> {
  const data = await getData(`/api/rivers/${id}`, signal)

  if (!isRiver(data)) {
    throw unexpected()
  }

  return data
}

/**
 * The stretch of a river between two points; the API snaps both to the river.
 * Pass the same point twice to find where a single point lands.
 */
export async function getSection(
  riverId: number,
  from: LatLng,
  to: LatLng,
  signal?: AbortSignal
): Promise<Section> {
  const data = await getData(
    `/api/rivers/${riverId}/section?from=${from.join(',')}&to=${to.join(',')}`,
    signal
  )

  if (!isSection(data)) {
    throw unexpected()
  }

  return data
}

/**
 * Fetches a path and returns the "data" of a successful response. An aborted request
 * rejects with the browser's AbortError, so callers can tell it apart from a failure.
 */
async function getData(path: string, signal?: AbortSignal): Promise<unknown> {
  let response: Response

  try {
    response = await fetch(`${getApiUrl()}${path}`, { signal })
  } catch (error) {
    if (isAbort(error)) {
      throw error
    }
    throw new ApiError('Could not reach the server. Check your connection and try again.', null)
  }

  let body: unknown = null
  try {
    body = await response.json()
  } catch {
    // Handled below as an unexpected response
  }

  if (!response.ok) {
    const message = isObject(body) && typeof body.message === 'string' ? body.message : null
    throw new ApiError(
      message ?? `The server answered with an error (${response.status}).`,
      response.status
    )
  }

  if (!isObject(body) || body.status !== 'success' || !('data' in body)) {
    throw unexpected(response.status)
  }

  return body.data
}

const unexpected = (status: number | null = 200) =>
  new ApiError('The server sent a response this page does not understand.', status)

const FEATURE_KINDS: readonly string[] = [
  'weir',
  'dam',
  'lock',
  'rapids',
  'waterfall',
  'put_in',
  'slipway',
  'bridge'
] satisfies FeatureKind[]

const isObject = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value)

const isLatLng = (value: unknown): value is LatLng =>
  Array.isArray(value) &&
  value.length === 2 &&
  typeof value[0] === 'number' &&
  typeof value[1] === 'number'

const isRiverSummary = (value: unknown): value is RiverSummary =>
  isObject(value) && typeof value.id === 'number' && typeof value.name === 'string'

const isFeature = (value: unknown): value is RiverFeature =>
  isObject(value) && isFeatureFields(value) && typeof value.distanceFromSource === 'number'

const isRiver = (value: unknown): value is River =>
  isRiverSummary(value) &&
  isObject(value) &&
  typeof value.length === 'number' &&
  Array.isArray(value.points) &&
  value.points.length >= 2 &&
  value.points.every(isLatLng) &&
  Array.isArray(value.features) &&
  value.features.every(isFeature)

const isFeatureFields = (value: Record<string, unknown>): boolean =>
  typeof value.kind === 'string' &&
  FEATURE_KINDS.includes(value.kind) &&
  (value.category === 'obstacle' || value.category === 'access') &&
  (value.name === null || typeof value.name === 'string') &&
  isLatLng(value.point)

const isSectionFeature = (value: unknown): value is SectionFeature =>
  isObject(value) && isFeatureFields(value) && typeof value.distance === 'number'

const isSnappedPoint = (value: unknown): value is SnappedPoint =>
  isObject(value) &&
  isLatLng(value.point) &&
  typeof value.distanceFromSource === 'number' &&
  typeof value.offset === 'number'

const isSection = (value: unknown): value is Section =>
  isObject(value) &&
  isRiverSummary(value.river) &&
  typeof value.length === 'number' &&
  typeof value.downstream === 'boolean' &&
  isSnappedPoint(value.from) &&
  isSnappedPoint(value.to) &&
  Array.isArray(value.points) &&
  value.points.every(isLatLng) &&
  Array.isArray(value.features) &&
  value.features.every(isSectionFeature)
