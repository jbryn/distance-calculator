<script setup lang="ts">
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useSectionStore } from '../stores/section'

const store = useSectionStore()
const { start, end, section, status, error } = storeToRefs(store)

const kilometers = computed(() => (section.value ? (section.value.length / 1000).toFixed(1) : null))

const buttonClass =
  'rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-100 dark:border-slate-600 dark:hover:bg-slate-800'
</script>

<template>
  <section class="mt-6" aria-labelledby="section-heading" data-testid="section-panel">
    <h3
      id="section-heading"
      class="text-sm font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
    >
      Your section
    </h3>

    <p v-if="!start" class="mt-2 text-sm" data-testid="section-hint">
      Click the river on the map to set where you start.
    </p>

    <p v-else-if="!end" class="mt-2 text-sm" data-testid="section-hint">
      <span class="font-semibold text-emerald-600">A</span> is set. Click the river again to set
      where you finish.
    </p>

    <div v-else-if="section" class="mt-2">
      <p class="text-3xl font-semibold" data-testid="section-length">{{ kilometers }} km</p>
      <p class="text-sm text-slate-500 dark:text-slate-400">
        along the river from <span class="font-semibold text-emerald-600">A</span> to
        <span class="font-semibold text-rose-600">B</span>
      </p>
      <p
        v-if="!section.downstream"
        class="mt-2 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200"
        data-testid="section-upstream"
      >
        B is upstream of A: this trip goes against the current.
      </p>
    </div>

    <p
      v-if="status === 'loading'"
      class="mt-2 text-sm text-slate-500"
      aria-live="polite"
      data-testid="section-loading"
    >
      Measuring…
    </p>

    <p
      v-if="status === 'error'"
      class="mt-2 text-sm text-red-600 dark:text-red-400"
      role="alert"
      data-testid="section-error"
    >
      {{ error }}
    </p>

    <div v-if="start" class="mt-3 flex gap-2">
      <button
        v-if="end"
        type="button"
        :class="buttonClass"
        data-testid="section-swap"
        @click="store.swap()"
      >
        Swap A and B
      </button>
      <button type="button" :class="buttonClass" data-testid="section-clear" @click="store.clear()">
        Clear
      </button>
    </div>
  </section>
</template>
