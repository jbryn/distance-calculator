<script setup lang="ts">
import AppShell from './components/layout/AppShell.vue'
import CoordinateForm from './components/CoordinatesForm.vue'
</script>

<template>
  <AppShell>
    <template #title>Distance Calculator</template>

    <template #map>
      <div
        class="flex h-full items-center justify-center text-sm text-slate-500 dark:text-slate-400"
        data-testid="map-placeholder"
      >
        Map
      </div>
    </template>

    <template #panel>
      <CoordinateForm />
    </template>
  </AppShell>
</template>
