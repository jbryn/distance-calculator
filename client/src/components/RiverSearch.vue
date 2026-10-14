<script setup lang="ts">
import { useRiverSearch } from '../composables/useRiverSearch'

const emit = defineEmits<{ select: [id: number] }>()

const { query, results, status, error, retry } = useRiverSearch()
</script>

<template>
  <section data-testid="river-search">
    <label for="river-query" class="mb-1 block text-sm font-medium">River</label>
    <input
      id="river-query"
      v-model="query"
      type="search"
      autocomplete="off"
      placeholder="Search by name, e.g. Gwda"
      class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-base placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none dark:border-slate-600 dark:bg-slate-800 dark:placeholder:text-slate-500"
      data-testid="river-query"
    />

    <p
      v-if="status === 'error'"
      class="mt-3 text-sm text-red-600 dark:text-red-400"
      role="alert"
      data-testid="search-error"
    >
      {{ error }}
      <button
        type="button"
        class="ml-1 font-medium underline"
        data-testid="search-retry"
        @click="retry"
      >
        Try again
      </button>
    </p>

    <p
      v-else-if="status === 'done' && results.length === 0"
      class="mt-3 text-sm text-slate-500 dark:text-slate-400"
      data-testid="search-empty"
    >
      No rivers match “{{ query.trim() }}”.
    </p>

    <ul
      v-else
      class="mt-3 divide-y divide-slate-100 dark:divide-slate-800"
      :aria-busy="status === 'loading'"
      aria-label="Rivers"
      data-testid="search-results"
    >
      <li v-for="river in results" :key="river.id">
        <button
          type="button"
          class="w-full rounded-md px-2 py-2.5 text-left hover:bg-slate-100 focus-visible:bg-slate-100 focus-visible:outline-none dark:hover:bg-slate-800 dark:focus-visible:bg-slate-800"
          :data-testid="`river-option-${river.id}`"
          @click="emit('select', river.id)"
        >
          {{ river.name }}
        </button>
      </li>
    </ul>
  </section>
</template>
