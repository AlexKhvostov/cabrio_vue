<script setup lang="ts">
import { Avatar, Badge, CarPhotoStack, roleTone } from '@/components/ui'

defineProps<{
  member: {
    id: number
    name: string
    username: string
    role: string
    city: string
    avatar: string
    cars: { id: number; photo: string; brand: string }[]
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
        <Badge :text="member.role" :tone="roleTone(member.role)" />
      </div>
      <div class="text-meta text-muted mt-0.5 truncate">@{{ member.username }} · {{ member.city }}</div>
      <div v-if="member.cars.length" class="text-label text-accent2 mt-0.5 truncate">
        {{ member.cars.slice(0, 3).map((c) => c.brand).join(' · ') }}<span v-if="member.cars.length > 3"> · +{{ member.cars.length - 3 }}</span>
      </div>
    </div>
    <CarPhotoStack :cars="member.cars" />
    <svg class="w-3 h-3 text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6" />
    </svg>
  </button>
</template>
