<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { PageHeader, SearchFilterBar, BottomNav } from '@/components/ui'
import EventListCard from '@/components/EventListCard.vue'

const router = useRouter()
const search = ref('')

const events = [
  { id: 1, name: 'Слёт кабриолетов', date: '12 июня', dateIso: '2026-06-12', time: '18:00', city: 'Нарочь', location: 'база отдыха «Волна»', type: 'слёт', photo: 'https://images.unsplash.com/photo-1541447271487-09612b3f49f7?w=500&h=300&fit=crop', going: 2, maybe: 1, limit: 40 },
  { id: 2, name: 'Пикник на Заславском вдхр.', date: '28 мая', dateIso: '2026-05-28', time: '16:00', city: 'Заславль', location: 'пляж', type: 'пикник', photo: 'https://images.unsplash.com/photo-1533106418989-88406c7cc8ca?w=500&h=300&fit=crop', going: 8, maybe: 3, limit: 20 },
  { id: 3, name: 'Ночной заезд', date: '3 мая', dateIso: '2026-05-03', time: '22:00', city: 'Минск', location: 'проспект Победителей', type: 'заезд', photo: 'https://images.unsplash.com/photo-1493238792000-8113da705763?w=500&h=300&fit=crop', going: 5, maybe: 0, limit: 15 },
]

const filtered = computed(() => events.filter((e) => e.name.toLowerCase().includes(search.value.toLowerCase())))

function openEvent(id: number) {
  router.push(`/events/${id}`)
}
</script>

<template>
  <div class="page-shell pb-16">
    <PageHeader />
    <h1 class="text-name font-bold text-text mb-2 px-1">Мероприятия</h1>
    <SearchFilterBar v-model="search" placeholder="Поиск по названию..." />
    <EventListCard v-for="e in filtered" :key="e.id" :event="e" @click="openEvent(e.id)" />
    <BottomNav />
  </div>
</template>
