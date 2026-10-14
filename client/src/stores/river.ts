import { defineStore } from 'pinia'
import { ref, shallowRef } from 'vue'
import { getRiver, isAbort } from '../api/rivers'
import type { River } from '../types/river'

/**
 * The river the user is looking at.
 */
export const useRiverStore = defineStore('river', () => {
  // Shallow: a river has thousands of points, which never change once loaded
  const river = shallowRef<River | null>(null)
  const selectedId = ref<number | null>(null)
  const status = ref<'idle' | 'loading' | 'ready' | 'error'>('idle')
  const error = ref<string | null>(null)

  let latest: AbortController | null = null

  /** Loads a river; if another is selected before it arrives, the earlier one is dropped */
  async function select(id: number): Promise<void> {
    latest?.abort()
    const request = new AbortController()
    latest = request

    selectedId.value = id
    river.value = null
    status.value = 'loading'
    error.value = null

    try {
      const loaded = await getRiver(id, request.signal)
      if (request !== latest) return

      river.value = loaded
      status.value = 'ready'
    } catch (failure) {
      if (request !== latest || isAbort(failure)) return

      error.value = failure instanceof Error ? failure.message : 'Could not load the river.'
      status.value = 'error'
    }
  }

  function clear(): void {
    latest?.abort()
    latest = null
    selectedId.value = null
    river.value = null
    status.value = 'idle'
    error.value = null
  }

  async function retry(): Promise<void> {
    if (selectedId.value !== null) {
      await select(selectedId.value)
    }
  }

  return { river, selectedId, status, error, select, clear, retry }
})
