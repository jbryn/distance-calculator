import { defineStore } from 'pinia'
import { ref, shallowRef, watch } from 'vue'
import { getSection, isAbort } from '../api/rivers'
import { useRiverStore } from './river'
import type { LatLng, Section, SnappedPoint } from '../types/river'

/**
 * The stretch of the selected river between a start and an end the user picks.
 *
 * Every change asks the API, which snaps the points to the river. Until the answer
 * arrives the previous selection stays; if the API refuses a point (for example one
 * too far from the river), the previous selection is kept and the reason is shown.
 */
export const useSectionStore = defineStore('section', () => {
  const rivers = useRiverStore()

  const start = ref<SnappedPoint | null>(null)
  const end = ref<SnappedPoint | null>(null)
  const section = shallowRef<Section | null>(null)
  const status = ref<'idle' | 'loading' | 'ready' | 'error'>('idle')
  const error = ref<string | null>(null)
  /** Goes up whenever a request finishes, so the map can put its markers back in place */
  const revision = ref(0)

  let latest: AbortController | null = null

  async function measure(from: LatLng, to: LatLng | null): Promise<void> {
    const riverId = rivers.river?.id
    if (riverId === undefined) return

    latest?.abort()
    const request = new AbortController()
    latest = request

    status.value = 'loading'
    error.value = null

    try {
      // With no end yet, measuring from the start to itself tells where the start lands
      const result = await getSection(riverId, from, to ?? from, request.signal)
      if (request !== latest) return

      start.value = result.from
      end.value = to ? result.to : null
      section.value = to ? result : null
      status.value = 'ready'
    } catch (failure) {
      if (request !== latest || isAbort(failure)) return

      error.value = failure instanceof Error ? failure.message : 'Could not measure the section.'
      status.value = 'error'
    } finally {
      if (request === latest) revision.value++
    }
  }

  /** A click on the map: sets the start, then the end, then starts a new selection */
  function pick(point: LatLng): Promise<void> {
    if (start.value === null || end.value !== null) {
      return measure(point, null)
    }
    return measure(start.value.point, point)
  }

  function moveStart(point: LatLng): Promise<void> {
    return measure(point, end.value?.point ?? null)
  }

  async function moveEnd(point: LatLng): Promise<void> {
    if (start.value !== null) {
      await measure(start.value.point, point)
    }
  }

  async function swap(): Promise<void> {
    if (start.value !== null && end.value !== null) {
      await measure(end.value.point, start.value.point)
    }
  }

  function clear(): void {
    latest?.abort()
    latest = null
    start.value = null
    end.value = null
    section.value = null
    status.value = 'idle'
    error.value = null
  }

  // A selection belongs to one river
  watch(() => rivers.selectedId, clear)

  return { start, end, section, status, error, revision, pick, moveStart, moveEnd, swap, clear }
})
