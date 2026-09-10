<script setup lang="ts">
import { Avatar, Badge } from '@/components/ui'

defineProps<{
  car: { name: string; year: number; city: string; owner: string; ownerAvatar: string; photo: string }
}>()
</script>

<template>
  <div class="card mb-2.5">
    <img :src="car.photo" class="w-full h-32 object-cover" />
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
