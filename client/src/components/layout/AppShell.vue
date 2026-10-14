<script setup lang="ts">
import { ref } from 'vue'
import ThemeToggle from '../ThemeToggle.vue'

/**
 * The page layout: a header, the map filling the rest of the screen, and a panel for
 * controls. On wide screens the panel sits on the left; on narrow ones it is a sheet
 * at the bottom that can be collapsed to leave more room for the map.
 */
const sheetOpen = ref(true)
</script>

<template>
  <div class="flex h-full flex-col" data-testid="app-shell">
    <header
      class="flex h-14 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 dark:border-slate-800 dark:bg-slate-900"
    >
      <h1 class="text-base font-semibold tracking-tight" data-testid="app-title">
        <slot name="title" />
      </h1>
      <ThemeToggle />
    </header>

    <div class="relative flex min-h-0 flex-1 flex-col md:flex-row">
      <main
        class="relative min-h-0 flex-1 bg-slate-200 md:order-last dark:bg-slate-800"
        data-testid="map-area"
      >
        <slot name="map" />
      </main>

      <aside
        class="flex shrink-0 flex-col border-slate-200 bg-white shadow-[0_-4px_12px_rgb(0_0_0/0.08)] md:w-96 md:border-r md:shadow-none dark:border-slate-800 dark:bg-slate-900"
        :class="sheetOpen ? 'max-md:max-h-[60%]' : ''"
        data-testid="side-panel"
      >
        <button
          type="button"
          class="flex w-full items-center justify-center py-2 md:hidden"
          :aria-expanded="sheetOpen"
          aria-controls="panel-content"
          :aria-label="sheetOpen ? 'Collapse panel' : 'Expand panel'"
          data-testid="sheet-toggle"
          @click="sheetOpen = !sheetOpen"
        >
          <span class="h-1.5 w-10 rounded-full bg-slate-300 dark:bg-slate-600" />
        </button>

        <div
          id="panel-content"
          class="min-h-0 flex-1 overflow-y-auto px-4 pb-4 md:pt-4"
          :class="sheetOpen ? '' : 'max-md:hidden'"
          data-testid="panel-content"
        >
          <slot name="panel" />
        </div>
      </aside>
    </div>
  </div>
</template>
