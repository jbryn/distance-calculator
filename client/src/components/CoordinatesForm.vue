<script setup lang="ts">
import { ref, computed } from 'vue'
import type { Coordinates, ValidationErrors } from '../types/coordinates'
import { useCoordinateValidation } from '../composables/useCoordinateValidation'
import { useDistance } from '../composables/useDistance'
import CoordinateInput from './CoordinateInput.vue'

const { validatePoint } = useCoordinateValidation()
const { distance, isLoading, apiError, calculateDistance } = useDistance()

const point1 = ref<Coordinates>({
  latitude: '',
  longitude: ''
})

const point2 = ref<Coordinates>({
  latitude: '',
  longitude: ''
})

const point1Errors = ref<ValidationErrors>({})
const point2Errors = ref<ValidationErrors>({})

const isFormValid = computed(() => {
  const p1Errors = validatePoint(point1.value)
  const p2Errors = validatePoint(point2.value)

  return (
    !Object.values(p1Errors).some((error) => error !== undefined) &&
    !Object.values(p2Errors).some((error) => error !== undefined)
  )
})

const handleSubmit = async () => {
  point1Errors.value = validatePoint(point1.value)
  point2Errors.value = validatePoint(point2.value)

  if (isFormValid.value) {
    isLoading.value = true
    apiError.value = null
    distance.value = null

    try {
      distance.value = await calculateDistance(point1.value, point2.value)
    } catch (error) {
      apiError.value = error instanceof Error ? error.message : 'An error occurred'
    } finally {
      isLoading.value = false
    }
  }
}

const handleBlur = (pointNumber: 1 | 2) => {
  const point = pointNumber === 1 ? point1.value : point2.value
  const errors = pointNumber === 1 ? point1Errors : point2Errors
  errors.value = validatePoint(point)
}

const formattedDistance = computed(() => {
  if (!distance.value) return null
  return {
    kilometers: distance.value.kilometers.toFixed(2),
    meters: distance.value.meters.toFixed(2)
  }
})
</script>

<template>
  <form data-testid="coordinates-form" novalidate @submit.prevent="handleSubmit">
    <CoordinateInput
      :point-number="1"
      :coordinates="point1"
      :errors="point1Errors"
      :on-blur="() => handleBlur(1)"
      marker-color="#4B7BF5"
    />

    <CoordinateInput
      :point-number="2"
      :coordinates="point2"
      :errors="point2Errors"
      :on-blur="() => handleBlur(2)"
      marker-color="#22C55E"
    />

    <button
      type="submit"
      :disabled="!isFormValid || isLoading"
      class="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-3 font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500 dark:disabled:bg-slate-800 dark:disabled:text-slate-500"
      data-testid="calculate-button"
    >
      {{ isLoading ? 'Calculating...' : 'Calculate Distance' }}
      <span aria-hidden="true">→</span>
    </button>

    <p
      v-if="formattedDistance"
      class="mt-4 text-center font-medium"
      role="status"
      data-testid="distance-result"
    >
      Distance: {{ formattedDistance.kilometers }} kilometers ({{ formattedDistance.meters }}
      meters)
    </p>

    <p
      v-if="apiError"
      class="mt-3 text-center text-sm text-red-600 dark:text-red-400"
      role="alert"
      data-testid="form-error"
    >
      {{ apiError }}
    </p>
  </form>
</template>
