<script setup lang="ts">
import { StarRating, ratingTone } from '@/components/ui'

defineProps<{
  place: { name: string; city: string; avgRating: number; reviewsCount: number; photo: string; tags?: string[] }
}>()
defineEmits<{ click: [] }>()

const toneBorder: Record<string, string> = {
  success: 'border-emerald-500/40',
  warning: 'border-amber-500/40',
  danger: 'border-rose-500/40',
}
</script>

<template>
  <button
    @click="$emit('click')"
    class="w-full flex items-center gap-2.5 card px-3 py-2 mb-2 text-left border-l-2"
    :class="toneBorder[ratingTone(place.avgRating)]"
  >
    <img :src="place.photo" class="w-12 h-12 rounded-lg object-cover shrink-0" />
    <div class="min-w-0 flex-1">
      <div class="text-body font-bold text-text truncate">{{ place.name }}</div>
      <div class="text-meta text-muted mt-0.5">{{ place.city }}</div>
      <div v-if="place.tags?.length" class="flex flex-wrap gap-1 mt-0.5">
        <span v-for="t in place.tags.slice(0, 3)" :key="t" class="text-micro text-accent2">#{{ t }}</span>
      </div>
      <div class="flex items-center gap-1 mt-0.5">
        <StarRating :value="place.avgRating" />
        <span class="text-label text-muted">({{ place.reviewsCount }})</span>
      </div>
    </div>
  </button>
</template>
