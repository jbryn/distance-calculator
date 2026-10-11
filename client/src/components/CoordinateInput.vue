<script setup lang="ts">
import type { Coordinates, ValidationErrors } from '../types/coordinates'

defineProps<{
  pointNumber: number
  coordinates: Coordinates
  errors: ValidationErrors
  onBlur: () => void
  markerColor: string
}>()
</script>

<!-- eslint-disable vue/no-mutating-props -- v-model writes to the coordinates prop; this component is removed in #16 -->
<template>
  <div class="point-container">
    <div class="point-header">
      <div class="marker" :style="{ backgroundColor: markerColor }">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
          <circle cx="12" cy="10" r="3"></circle>
        </svg>
      </div>
      <h3>Point {{ pointNumber }}</h3>
    </div>

    <div class="coordinates-grid">
      <div class="input-group">
        <label :for="'lat' + pointNumber">Latitude</label>
        <input
          :id="'lat' + pointNumber"
          v-model="coordinates.latitude"
          type="text"
          required
          :placeholder="'e.g. ' + (pointNumber === 1 ? '51.5074' : '48.8566')"
          @blur="onBlur"
        />
        <span v-if="errors.latitude" class="error">
          {{ errors.latitude }}
        </span>
      </div>

      <div class="input-group">
        <label :for="'lng' + pointNumber">Longitude</label>
        <input
          :id="'lng' + pointNumber"
          v-model="coordinates.longitude"
          type="text"
          required
          :placeholder="'e.g. ' + (pointNumber === 1 ? '-0.1278' : '2.3522')"
          @blur="onBlur"
        />
        <span v-if="errors.longitude" class="error">
          {{ errors.longitude }}
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.point-container {
  margin-bottom: 24px;
  min-height: 160px;
  width: 100%;
  box-sizing: border-box;
}

.point-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.marker {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}

.marker svg {
  width: 20px;
  height: 20px;
}

h3 {
  font-size: 20px;
  font-weight: 500;
  margin: 0;
  color: #374151;
}

.coordinates-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 16px;
  min-height: 90px;
  width: 100%;
  box-sizing: border-box;
}

.input-group {
  display: flex;
  flex-direction: column;
  height: 100%;
  width: 100%;
  box-sizing: border-box;
}

label {
  font-size: 16px;
  color: #374151;
  margin-bottom: 8px;
}

input {
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 16px;
  width: 100%;
  box-sizing: border-box;
}

input::placeholder {
  color: #9ca3af;
}

input:focus {
  outline: none;
  border-color: #4b7bf5;
  box-shadow: 0 0 0 2px rgba(75, 123, 245, 0.1);
}

.error {
  color: #ef4444;
  font-size: 12px;
  margin-top: 4px;
}
</style>
