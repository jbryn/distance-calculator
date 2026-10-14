import { onScopeDispose, ref, watch } from 'vue'
import { isAbort, searchRivers } from '../api/rivers'
import type { RiverSummary } from '../types/river'

export const SEARCH_DELAY_MS = 250

/**
 * Searches rivers as the user types. Requests wait until typing pauses, and only the
 * answer to the latest query is shown, even if an older request finishes later.
 * The search starts with an empty query, which lists every river.
 */
export function useRiverSearch(delay = SEARCH_DELAY_MS) {
  const query = ref('')
  const results = ref<RiverSummary[]>([])
  const status = ref<'loading' | 'done' | 'error'>('loading')
  const error = ref<string | null>(null)

  let timer: ReturnType<typeof setTimeout> | undefined
  let latest: AbortController | null = null

  const search = async () => {
    latest?.abort()
    const request = new AbortController()
    latest = request

    status.value = 'loading'
    error.value = null

    try {
      const found = await searchRivers(query.value, request.signal)
      if (request !== latest) return

      results.value = found
      status.value = 'done'
    } catch (failure) {
      if (request !== latest || isAbort(failure)) return

      results.value = []
      error.value = failure instanceof Error ? failure.message : 'Search failed.'
      status.value = 'error'
    }
  }

  watch(query, () => {
    clearTimeout(timer)
    timer = setTimeout(search, delay)
  })

  onScopeDispose(() => {
    clearTimeout(timer)
    latest?.abort()
  })

  void search()

  return { query, results, status, error, retry: search }
}
