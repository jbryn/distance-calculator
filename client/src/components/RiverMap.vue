<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import { TILE_ATTRIBUTION, TILE_URL } from '../config'
import type { River } from '../types/river'

const props = defineProps<{ river: River | null }>()

/** Poland, where all the offered rivers are */
const START_CENTER: L.LatLngTuple = [52.1, 19.4]
const START_ZOOM = 6

const container = ref<HTMLElement | null>(null)

let map: L.Map | null = null
let line: L.Polyline | null = null
let resizeObserver: ResizeObserver | null = null

const showRiver = (river: River | null) => {
  if (!map) return

  line?.remove()
  line = null

  if (river) {
    line = L.polyline(river.points, {
      color: '#2563eb',
      weight: 4,
      className: 'river-line'
    }).addTo(map)
    map.fitBounds(line.getBounds(), { padding: [24, 24] })
  }
}

onMounted(() => {
  if (!container.value) return

  map = L.map(container.value).setView(START_CENTER, START_ZOOM)
  L.tileLayer(TILE_URL, { attribution: TILE_ATTRIBUTION, maxZoom: 19 }).addTo(map)

  // The map's box changes when the bottom sheet opens or closes; Leaflet must be told
  if (typeof ResizeObserver !== 'undefined') {
    resizeObserver = new ResizeObserver(() => map?.invalidateSize())
    resizeObserver.observe(container.value)
  }

  showRiver(props.river)
})

watch(() => props.river, showRiver)

onBeforeUnmount(() => {
  resizeObserver?.disconnect()
  map?.remove()
  map = null
})
</script>

<template>
  <div ref="container" class="h-full w-full" data-testid="river-map" />
</template>
