<script setup lang="ts">
import type { Coordinates, ValidationErrors } from '../types/coordinates'

defineProps<{
  pointNumber: number
  coordinates: Coordinates
  errors: ValidationErrors
  onBlur: () => void
  markerColor: string
}>()

const inputClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-base placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none dark:border-slate-600 dark:bg-slate-800 dark:placeholder:text-slate-500'
</script>

<!-- eslint-disable vue/no-mutating-props -- v-model writes to the coordinates prop; this component is removed in #16 -->
<template>
  <fieldset class="mb-5" :data-testid="`point-${pointNumber}`">
    <legend class="mb-3 flex items-center gap-3">
      <span
        class="flex size-8 items-center justify-center rounded-full text-white"
        :style="{ backgroundColor: markerColor }"
        :data-testid="`marker-${pointNumber}`"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="size-4"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          aria-hidden="true"
        >
          <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
          <circle cx="12" cy="10" r="3"></circle>
        </svg>
      </span>
      <span class="text-base font-medium" :data-testid="`point-title-${pointNumber}`">
        Point {{ pointNumber }}
      </span>
    </legend>

    <div class="grid grid-cols-2 gap-3">
      <div class="flex flex-col">
        <label :for="'lat' + pointNumber" class="mb-1 text-sm text-slate-600 dark:text-slate-300">
          Latitude
        </label>
        <input
          :id="'lat' + pointNumber"
          v-model="coordinates.latitude"
          type="text"
          inputmode="decimal"
          required
          :class="inputClass"
          :placeholder="'e.g. ' + (pointNumber === 1 ? '51.5074' : '48.8566')"
          :data-testid="`latitude-${pointNumber}`"
          @blur="onBlur"
        />
        <span
          v-if="errors.latitude"
          class="mt-1 text-xs text-red-600 dark:text-red-400"
          :data-testid="`latitude-error-${pointNumber}`"
        >
          {{ errors.latitude }}
        </span>
      </div>

      <div class="flex flex-col">
        <label :for="'lng' + pointNumber" class="mb-1 text-sm text-slate-600 dark:text-slate-300">
          Longitude
        </label>
        <input
          :id="'lng' + pointNumber"
          v-model="coordinates.longitude"
          type="text"
          inputmode="decimal"
          required
          :class="inputClass"
          :placeholder="'e.g. ' + (pointNumber === 1 ? '-0.1278' : '2.3522')"
          :data-testid="`longitude-${pointNumber}`"
          @blur="onBlur"
        />
        <span
          v-if="errors.longitude"
          class="mt-1 text-xs text-red-600 dark:text-red-400"
          :data-testid="`longitude-error-${pointNumber}`"
        >
          {{ errors.longitude }}
        </span>
      </div>
    </div>
  </fieldset>
</template>
