<script setup lang="ts">
import { useTheme, type ThemePreference } from '../composables/useTheme'

const { preference } = useTheme()

const options: { value: ThemePreference; label: string }[] = [
  { value: 'system', label: 'Auto' },
  { value: 'light', label: 'Light' },
  { value: 'dark', label: 'Dark' }
]
</script>

<template>
  <div
    role="group"
    aria-label="Colour theme"
    data-testid="theme-toggle"
    class="inline-flex rounded-lg bg-slate-100 p-0.5 text-xs font-medium dark:bg-slate-800"
  >
    <button
      v-for="option in options"
      :key="option.value"
      type="button"
      :data-testid="`theme-${option.value}`"
      :aria-pressed="preference === option.value"
      class="rounded-md px-2.5 py-1 transition-colors"
      :class="
        preference === option.value
          ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-600 dark:text-white'
          : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
      "
      @click="preference = option.value"
    >
      {{ option.label }}
    </button>
  </div>
</template>
