<script setup lang="ts">
import { storeToRefs } from 'pinia'
import AppShell from './components/layout/AppShell.vue'
import RiverDetails from './components/RiverDetails.vue'
import RiverMap from './components/RiverMap.vue'
import RiverSearch from './components/RiverSearch.vue'
import { useRiverStore } from './stores/river'
import { useSectionStore } from './stores/section'

const store = useRiverStore()
const { river, status } = storeToRefs(store)

const sectionStore = useSectionStore()
const { start, end, section, revision } = storeToRefs(sectionStore)
</script>

<template>
  <AppShell>
    <template #title>Downriver</template>

    <template #map>
      <RiverMap
        :river="river"
        :start="start?.point ?? null"
        :end="end?.point ?? null"
        :section="section?.points ?? null"
        :revision="revision"
        @pick="sectionStore.pick"
        @move-start="sectionStore.moveStart"
        @move-end="sectionStore.moveEnd"
      />
    </template>

    <template #panel>
      <RiverSearch v-if="status === 'idle'" @select="store.select" />
      <RiverDetails v-else @back="store.clear" />
    </template>
  </AppShell>
</template>
