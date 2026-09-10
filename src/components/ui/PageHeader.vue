<script setup lang="ts">
import { useRouter } from 'vue-router'

const emit = defineEmits<{ back: []; menu: [] }>()
const router = useRouter()

function onBack() {
  emit('back')
  if (window.history.length > 1) router.back()
  else router.push('/')
}
</script>

<template>
  <div class="flex items-center justify-between mb-2">
    <button class="icon-button" @click="onBack">
      <svg class="w-3.5 h-3.5 text-text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
      </svg>
    </button>
    <slot name="actions">
      <button class="icon-button text-text text-xs" @click="$emit('menu')">⋯</button>
    </slot>
  </div>
</template>
