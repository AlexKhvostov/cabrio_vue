<script setup lang="ts">
import { StarRating } from '@/components/ui'

defineProps<{
  place: { name: string; city: string; avgRating: number; reviewsCount: number; photo: string }
}>()
defineEmits<{ click: [] }>()
</script>

<template>
  <button @click="$emit('click')" class="w-full flex items-center gap-2.5 card px-3 py-2 mb-2 text-left">
    <img :src="place.photo" class="w-12 h-12 rounded-lg object-cover shrink-0" />
    <div class="min-w-0 flex-1">
      <div class="text-body font-bold text-text truncate">{{ place.name }}</div>
      <div class="text-meta text-muted mt-0.5">{{ place.city }}</div>
      <div class="flex items-center gap-1 mt-0.5">
        <StarRating :value="place.avgRating" />
        <span class="text-label text-muted">({{ place.reviewsCount }})</span>
      </div>
    </div>
  </button>
</template>
