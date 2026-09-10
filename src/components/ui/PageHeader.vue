<script setup lang="ts">
defineEmits<{ back: []; menu: [] }>()
</script>

<template>
  <div class="flex items-center justify-between mb-2">
    <button class="icon-button" @click="$emit('back')">
      <svg class="w-3.5 h-3.5 text-text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
      </svg>
    </button>
    <slot name="actions">
      <button class="icon-button text-text text-xs" @click="$emit('menu')">⋯</button>
    </slot>
  </div>
</template>
