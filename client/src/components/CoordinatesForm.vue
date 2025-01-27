<script setup lang="ts">
import { ref, computed } from 'vue'
import type { Coordinates, ValidationErrors } from '../types/coordinates'
import { useCoordinateValidation } from '../composables/useCoordinateValidation'
import CoordinateInput from './CoordinateInput.vue'

const { validatePoint } = useCoordinateValidation()

const point1 = ref<Coordinates>({
  latitude: 0,
  longitude: 0
})

const point2 = ref<Coordinates>({
  latitude: 0,
  longitude: 0
})

const point1Errors = ref<ValidationErrors>({})
const point2Errors = ref<ValidationErrors>({})
const distance = ref<number | null>(null)
const isLoading = ref(false)
const apiError = ref<string | null>(null)

const isFormValid = computed(() => {
  const p1Errors = validatePoint(point1.value)
  const p2Errors = validatePoint(point2.value)
  
  return !Object.values(p1Errors).some(error => error !== undefined) &&
         !Object.values(p2Errors).some(error => error !== undefined)
})

const calculateDistance = async () => {
  try {
    const response = await fetch('http://localhost:8080/api/calculate-distance', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        lat1: point1.value.latitude,
        lon1: point1.value.longitude,
        lat2: point2.value.latitude,
        lon2: point2.value.longitude
      })
    })

    if (!response.ok) {
      throw new Error('Failed to calculate distance')
    }

    const data = await response.json()
    return data.distance
  } catch (error) {
    throw new Error('Failed to calculate distance')
  }
}

const handleSubmit = async () => {
  point1Errors.value = validatePoint(point1.value)
  point2Errors.value = validatePoint(point2.value)
  
  if (isFormValid.value) {
    isLoading.value = true
    apiError.value = null
    
    try {
      distance.value = await calculateDistance()
    } catch (error) {
      apiError.value = error instanceof Error ? error.message : 'An error occurred'
    } finally {
      isLoading.value = false
    }
  }
}

const handleInput = (pointNumber: 1 | 2) => {
  const point = pointNumber === 1 ? point1.value : point2.value
  const errors = pointNumber === 1 ? point1Errors : point2Errors
  errors.value = validatePoint(point)
}
</script>

<template>
  <div class="coordinate-form">
    <h2>Geographic Coordinates Form</h2>
    
    <form @submit.prevent="handleSubmit">
      <CoordinateInput
        :pointNumber="1"
        :coordinates="point1"
        :errors="point1Errors"
        :onInput="() => handleInput(1)"
      />
      
      <CoordinateInput
        :pointNumber="2"
        :coordinates="point2"
        :errors="point2Errors"
        :onInput="() => handleInput(2)"
      />

      <button 
        type="submit" 
        :disabled="!isFormValid || isLoading"
      >
        {{ isLoading ? 'Calculating...' : 'Calculate Distance' }}
      </button>

      <div v-if="distance !== null" class="result">
        <p>Distance: {{ distance.toFixed(2) }} kilometers</p>
      </div>

      <div v-if="apiError" class="error">
        {{ apiError }}
      </div>
    </form>
  </div>
</template>

<style scoped>
.coordinate-form {
  max-width: 500px;
  margin: 0 auto;
  padding: 20px;
}

.point-section {
  margin-bottom: 20px;
  padding: 15px;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.input-group {
  margin-bottom: 15px;
  display: flex;
  flex-direction: column;
}

label {
  margin-bottom: 5px;
}

input {
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
}

input:invalid {
  border-color: #ff4444;
}

.error {
  color: #ff4444;
  font-size: 0.9em;
  margin-top: 5px;
}

.result {
  margin-top: 20px;
  padding: 15px;
  background-color: #f0f8f4;
  border-radius: 4px;
  text-align: center;
}

button {
  width: 100%;
  padding: 10px;
  background-color: #42b883;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

button:disabled {
  background-color: #cccccc;
  cursor: not-allowed;
}

button:hover:not(:disabled) {
  background-color: #3aa876;
}
</style>