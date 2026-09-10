<script setup lang="ts">
import { Avatar, Badge } from '@/components/ui'

defineProps<{
  member: {
    id: number
    name: string
    username: string
    role: string
    city: string
    avatar: string
    carBrands: string
  }
}>()
defineEmits<{ click: [] }>()
</script>

<template>
  <button @click="$emit('click')" class="w-full flex items-center gap-2.5 card px-3 py-2 mb-2 text-left">
    <Avatar :src="member.avatar" :size="40" />
    <div class="min-w-0 flex-1">
      <div class="flex items-center gap-1.5 leading-none">
        <span class="text-body font-bold text-text truncate">{{ member.name }}</span>
        <Badge :text="member.role" />
      </div>
      <div class="text-meta text-muted mt-0.5 truncate">@{{ member.username }} · {{ member.city }}</div>
      <div v-if="member.carBrands" class="text-label text-accent2 mt-0.5 truncate">{{ member.carBrands }}</div>
    </div>
    <svg class="w-3 h-3 text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6" />
    </svg>
  </button>
</template>
