<script setup lang="ts">
import { ref } from 'vue'
import { BottomNav } from '@/components/ui'

const peopleOnMap = ref([
  { id: 1, name: 'Иван Петров', avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop' },
  { id: 2, name: 'Ольга Смирнова', avatar: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&h=100&fit=crop' },
])
const listOpen = ref(false)
const sharing = ref(false)
</script>

<template>
  <div class="relative h-screen overflow-hidden pb-16">
    <div
      class="absolute inset-0 flex items-center justify-center bg-surface2"
      style="background-image: radial-gradient(circle at 30% 30%, rgba(94,200,216,0.12), transparent 50%), radial-gradient(circle at 70% 70%, rgba(255,138,92,0.12), transparent 50%)"
    >
      <span class="text-meta text-muted">карта загружается…</span>
    </div>

    <div class="absolute top-3 left-3 right-3 z-10">
      <button
        class="px-3 py-1.5 rounded-full bg-surface text-meta font-semibold text-text shadow"
        @click="listOpen = !listOpen"
      >
        Сейчас на карте — {{ peopleOnMap.length }}
      </button>
      <div v-if="listOpen" class="card mt-1.5 p-1">
        <div v-for="p in peopleOnMap" :key="p.id" class="flex items-center gap-2 px-2 py-1.5">
          <img :src="p.avatar" class="w-6 h-6 rounded-full object-cover" />
          <span class="text-body text-text">{{ p.name }}</span>
        </div>
      </div>
    </div>

    <div class="absolute bottom-20 right-3 z-10 flex flex-col gap-2">
      <button
        class="icon-button w-10 h-10"
        :class="sharing ? 'bg-accent text-bg' : 'bg-surface text-text'"
        @click="sharing = !sharing"
      >
        ⏻
      </button>
    </div>

    <BottomNav />
  </div>
</template>
