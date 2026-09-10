<script setup lang="ts">
import { Badge, eventWhenLabel } from '@/components/ui'

defineProps<{
  event: {
    name: string
    date: string
    dateIso: string
    time: string
    city: string
    location: string
    type: string
    photo: string
    going?: number
    maybe?: number
    limit?: number
  }
}>()
defineEmits<{ click: [] }>()
</script>

<template>
  <button @click="$emit('click')" class="w-full card mb-2.5 text-left">
    <div class="relative">
      <img :src="event.photo" class="w-full h-24 object-cover" />
      <Badge :text="eventWhenLabel(event.dateIso)" tone="accent2" class="absolute top-2 right-2" />
    </div>
    <div class="px-3 py-2">
      <div class="flex items-center justify-between">
        <span class="text-body font-bold text-text truncate">{{ event.name }}</span>
        <Badge :text="event.type" tone="muted" />
      </div>
      <div class="text-meta text-muted mt-0.5">
        {{ event.date }} · {{ event.time }} · {{ event.city }}, {{ event.location }}
      </div>
      <div v-if="event.going !== undefined" class="flex items-center justify-between mt-1 text-label text-muted">
        <span>🚗 едут: {{ event.going }} · 🤔 думают: {{ event.maybe }}</span>
        <span v-if="event.limit" class="font-semibold text-accent2">{{ event.limit - event.going }} мест</span>
      </div>
    </div>
  </button>
</template>
