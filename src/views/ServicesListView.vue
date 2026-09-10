<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { PageHeader, SearchFilterBar, BottomNav } from '@/components/ui'
import ServiceListCard from '@/components/ServiceListCard.vue'

const router = useRouter()
const search = ref('')

const places = [
  { id: 1, name: 'Мойка «Блеск»', city: 'Минск', avgRating: 5, reviewsCount: 12, photo: 'https://images.unsplash.com/photo-1520340356584-f9917d1eea6f?w=200&h=200&fit=crop' },
  { id: 2, name: 'СТО «АвтоПро»', city: 'Минск', avgRating: 4, reviewsCount: 8, photo: 'https://images.unsplash.com/photo-1493238792000-8113da705763?w=200&h=200&fit=crop' },
  { id: 3, name: 'Шиномонтаж «Колесо»', city: 'Гродно', avgRating: 5, reviewsCount: 5, photo: 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=200&h=200&fit=crop' },
]

const filtered = computed(() => places.filter((p) => p.name.toLowerCase().includes(search.value.toLowerCase())))

function openPlace(id: number) {
  router.push(`/services/${id}`)
}
</script>

<template>
  <div class="page-shell pb-16">
    <PageHeader />
    <h1 class="text-name font-bold text-text mb-2 px-1">Отзывы о местах</h1>
    <SearchFilterBar v-model="search" placeholder="Поиск по названию..." />
    <ServiceListCard v-for="p in filtered" :key="p.id" :place="p" @click="openPlace(p.id)" />
    <BottomNav />
  </div>
</template>
