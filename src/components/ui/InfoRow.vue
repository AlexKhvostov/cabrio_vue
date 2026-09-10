<script setup lang="ts">
defineProps<{ label: string; value?: string | number | null }>()
</script>

<template>
  <div class="flex items-center justify-between px-3 py-1.5 border-b border-white/5 last:border-0">
    <div class="text-meta text-muted">{{ label }}</div>
    <div class="text-body font-medium text-text truncate ml-3">{{ value || '—' }}</div>
  </div>
</template>
