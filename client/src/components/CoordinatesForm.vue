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
  
  return !Object.values(p1Errors).some(error => error !== undefined) &&
         !Object.values(p2Errors).some(error => error !== undefined)
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
  return distance.value !== null ? distance.value.toFixed(2) : null
})
</script>

<template>
  <div class="coordinate-form">
    <form @submit.prevent="handleSubmit" class="form-container">
      <CoordinateInput
        :pointNumber="1"
        :coordinates="point1"
        :errors="point1Errors"
        :onBlur="() => handleBlur(1)"
        marker-color="#4B7BF5"
      />
      
      <CoordinateInput
        :pointNumber="2"
        :coordinates="point2"
        :errors="point2Errors"
        :onBlur="() => handleBlur(2)"
        marker-color="#22C55E"
      />

      <button 
        type="submit" 
        :disabled="!isFormValid || isLoading"
        class="calculate-button"
      >
        {{ isLoading ? 'Calculating...' : 'Calculate Distance' }}
        <span class="arrow">→</span>
      </button>

      <div v-if="formattedDistance" class="result">
        <p>Distance: {{ formattedDistance }} kilometers</p>
      </div>

      <div v-if="apiError" class="error">
        {{ apiError }}
      </div>
    </form>
  </div>
</template>

<style scoped>
.coordinate-form {
  width: 500px;
  margin: 0 auto;
  padding: 20px;
}

.form-container {
  width: 100%;
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
  padding: 24px;
  box-sizing: border-box;
}

.calculate-button {
  width: 100%;
  padding: 16px;
  background-color: #4B7BF5;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 16px;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 16px;
}

.calculate-button:disabled {
  background-color: #E5E7EB;
  cursor: not-allowed;
}

.calculate-button:hover:not(:disabled) {
  background-color: #3B69E3;
}

.arrow {
  margin-left: 8px;
}

.error {
  color: #EF4444;
  font-size: 0.9em;
  margin-top: 12px;
  text-align: center;
}

.result {
  margin-top: 16px;
  text-align: center;
  font-weight: 500;
}
</style>