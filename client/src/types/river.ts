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

/** Where a point given by the user landed on the river */
export interface SnappedPoint {
  point: LatLng
  /** Meters along the river from its source */
  distanceFromSource: number
  /** Meters between the given point and the river */
  offset: number
}

export interface SectionFeature extends Omit<RiverFeature, 'distanceFromSource'> {
  /** Meters from the start of the section */
  distance: number
}

export interface Section {
  river: RiverSummary
  /** Meters along the river */
  length: number
  /** False when the end lies upstream of the start */
  downstream: boolean
  from: SnappedPoint
  to: SnappedPoint
  /** The course from start to end */
  points: LatLng[]
  features: SectionFeature[]
}
