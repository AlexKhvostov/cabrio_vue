<script setup lang="ts">
defineProps<{ value: string | number; label: string }>()
defineEmits<{ click: [] }>()
</script>

<template>
  <button class="rounded-xl bg-surface py-2 flex flex-col items-center justify-center gap-0.5" @click="$emit('click')">
    <div class="text-title font-extrabold text-accent">{{ value }}</div>
    <div class="text-micro text-muted">{{ label }}</div>
  </button>
</template>
