<script setup lang="ts">
import { Badge } from '@/components/ui'

defineProps<{
  event: { name: string; date: string; time: string; city: string; location: string; type: string; photo: string }
}>()
defineEmits<{ click: [] }>()
</script>

<template>
  <button @click="$emit('click')" class="w-full card mb-2.5 text-left">
    <img :src="event.photo" class="w-full h-24 object-cover" />
    <div class="px-3 py-2">
      <div class="flex items-center justify-between">
        <span class="text-body font-bold text-text truncate">{{ event.name }}</span>
        <Badge :text="event.type" tone="muted" />
      </div>
      <div class="text-meta text-muted mt-0.5">
        {{ event.date }} · {{ event.time }} · {{ event.city }}, {{ event.location }}
      </div>
    </div>
  </button>
</template>
