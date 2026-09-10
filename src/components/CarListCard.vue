<script setup lang="ts">
import { Avatar, Badge, carStatusTone } from '@/components/ui'

defineProps<{
  car: { name: string; year: number; city: string; owner: string; ownerAvatar: string; photo: string; status?: string }
}>()
defineEmits<{ click: [] }>()
</script>

<template>
  <div class="card mb-2.5" @click="$emit('click')">
    <div class="relative">
      <img :src="car.photo" class="w-full h-32 object-cover" />
      <Badge v-if="car.status" :text="car.status" :tone="carStatusTone(car.status)" class="absolute top-2 right-2" />
    </div>
    <div class="flex items-center justify-between px-3 py-2">
      <div class="min-w-0">
        <div class="text-name font-bold text-text truncate">{{ car.name }}</div>
        <div class="text-meta text-muted">{{ car.year }} г. · {{ car.city }}</div>
      </div>
      <Badge text="кабрио" tone="muted" />
    </div>
    <div class="flex items-center gap-2 px-3 py-2 border-t border-white/5">
      <Avatar :src="car.ownerAvatar" :size="24" />
      <span class="text-meta text-muted">{{ car.owner }}</span>
    </div>
  </div>
</template>
