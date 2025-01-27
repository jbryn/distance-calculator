<script setup lang="ts">
import type { Coordinates, ValidationErrors } from '../types/coordinates'

defineProps<{
  pointNumber: number
  coordinates: Coordinates
  errors: ValidationErrors
  onInput: () => void
}>()
</script>

<template>
  <div class="point-section">
    <h3>Point {{ pointNumber }}</h3>
    <div class="input-group">
      <label :for="'lat' + pointNumber">Latitude:</label>
      <input
        :id="'lat' + pointNumber"
        type="number"
        v-model="coordinates.latitude"
        step="0.000001"
        min="-90"
        max="90"
        required
        @input="onInput"
      />
      <span class="error" v-if="errors.latitude">
        {{ errors.latitude }}
      </span>
    </div>
    <div class="input-group">
      <label :for="'lng' + pointNumber">Longitude:</label>
      <input
        :id="'lng' + pointNumber"
        type="number"
        v-model="coordinates.longitude"
        step="0.000001"
        min="-180"
        max="180"
        required
        @input="onInput"
      />
      <span class="error" v-if="errors.longitude">
        {{ errors.longitude }}
      </span>
    </div>
  </div>
</template>
