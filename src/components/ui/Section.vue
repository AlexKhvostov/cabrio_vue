<script setup lang="ts">
defineProps<{ title: string; count: number }>()
defineEmits<{ more: [] }>()
</script>

<template>
  <div class="mt-2.5">
    <div class="flex items-center justify-between px-1 mb-1">
      <h3 class="section-title">{{ title }} ({{ count }})</h3>
      <button class="text-meta font-semibold text-accent2 flex items-center gap-0.5" @click="$emit('more')">
        все
        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6" />
        </svg>
      </button>
    </div>
    <slot />
  </div>
</template>
