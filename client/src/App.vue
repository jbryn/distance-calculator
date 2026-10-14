<script setup lang="ts">
import { storeToRefs } from 'pinia'
import AppShell from './components/layout/AppShell.vue'
import RiverDetails from './components/RiverDetails.vue'
import RiverMap from './components/RiverMap.vue'
import RiverSearch from './components/RiverSearch.vue'
import { useRiverStore } from './stores/river'

const store = useRiverStore()
const { river, status } = storeToRefs(store)
</script>

<template>
  <AppShell>
    <template #title>Distance Calculator</template>

    <template #map>
      <RiverMap :river="river" />
    </template>

    <template #panel>
      <RiverSearch v-if="status === 'idle'" @select="store.select" />
      <RiverDetails v-else @back="store.clear" />
    </template>
  </AppShell>
</template>
