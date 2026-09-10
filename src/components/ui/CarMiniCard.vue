<script setup lang="ts">
defineProps<{ car: { name: string; year: number; photo: string } }>()
defineEmits<{ click: [] }>()
</script>

<template>
  <button
    @click="$emit('click')"
    class="shrink-0 w-28 rounded-2xl overflow-hidden bg-surface text-left hover:opacity-90 transition-opacity"
  >
    <img :src="car.photo" class="w-full h-16 object-cover" />
    <div class="p-2">
      <div class="text-body font-bold text-text truncate">{{ car.name }}</div>
      <div class="text-label text-muted">{{ car.year }} г.</div>
    </div>
  </button>
</template>
