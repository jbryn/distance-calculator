/** A position as the API sends it: [latitude, longitude] */
export type LatLng = [number, number]

export interface RiverSummary {
  id: number
  name: string
}

export type FeatureKind =
  'weir' | 'dam' | 'lock' | 'rapids' | 'waterfall' | 'put_in' | 'slipway' | 'bridge'

export interface RiverFeature {
  kind: FeatureKind
  category: 'obstacle' | 'access'
  name: string | null
  point: LatLng
  /** Meters along the river from its source */
  distanceFromSource: number
}

export interface River {
  id: number
  name: string
  /** Meters */
  length: number
  /** The course from source to mouth */
  points: LatLng[]
  features: RiverFeature[]
}
