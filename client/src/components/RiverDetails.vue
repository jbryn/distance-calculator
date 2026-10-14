<script setup lang="ts">
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useRiverStore } from '../stores/river'

const emit = defineEmits<{ back: [] }>()

const store = useRiverStore()
const { river, status, error } = storeToRefs(store)

const kilometers = computed(() => (river.value ? (river.value.length / 1000).toFixed(1) : null))

const obstacles = computed(
  () => river.value?.features.filter((feature) => feature.category === 'obstacle').length ?? 0
)

const accessPoints = computed(
  () => river.value?.features.filter((feature) => feature.category === 'access').length ?? 0
)
</script>

<template>
  <section data-testid="river-details">
    <button
      type="button"
      class="mb-3 text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
      data-testid="change-river"
      @click="emit('back')"
    >
      ← Choose another river
    </button>

    <p
      v-if="status === 'loading'"
      class="text-sm text-slate-500"
      aria-live="polite"
      data-testid="river-loading"
    >
      Loading river…
    </p>

    <div v-else-if="status === 'error'" role="alert" data-testid="river-error">
      <p class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
      <button
        type="button"
        class="mt-2 text-sm font-medium underline"
        data-testid="river-retry"
        @click="store.retry()"
      >
        Try again
      </button>
    </div>

    <template v-else-if="river">
      <h2 class="text-xl font-semibold" data-testid="river-name">{{ river.name }}</h2>

      <dl class="mt-3 grid grid-cols-3 gap-3 text-center">
        <div class="rounded-lg bg-slate-100 px-2 py-3 dark:bg-slate-800">
          <dt class="text-xs text-slate-500 dark:text-slate-400">Length</dt>
          <dd class="font-semibold" data-testid="river-length">{{ kilometers }} km</dd>
        </div>
        <div class="rounded-lg bg-slate-100 px-2 py-3 dark:bg-slate-800">
          <dt class="text-xs text-slate-500 dark:text-slate-400">Obstacles</dt>
          <dd class="font-semibold" data-testid="river-obstacles">{{ obstacles }}</dd>
        </div>
        <div class="rounded-lg bg-slate-100 px-2 py-3 dark:bg-slate-800">
          <dt class="text-xs text-slate-500 dark:text-slate-400">Access points</dt>
          <dd class="font-semibold" data-testid="river-access-points">{{ accessPoints }}</dd>
        </div>
      </dl>
    </template>
  </section>
</template>
