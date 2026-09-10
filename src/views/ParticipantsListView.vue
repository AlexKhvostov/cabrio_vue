<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { PageHeader, SearchFilterBar, BottomNav } from '@/components/ui'
import MemberListCard from '@/components/MemberListCard.vue'

const router = useRouter()
const search = ref('')

const bmwPhoto = 'https://images.unsplash.com/photo-1555215695-3004980ad54e?w=200&h=200&fit=crop'
const mazdaPhoto = 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=200&h=200&fit=crop'
const audiPhoto = 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=200&h=200&fit=crop'
const miniPhoto = 'https://images.unsplash.com/photo-1493238792000-8113da705763?w=200&h=200&fit=crop'
const porschePhoto = 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=200&h=200&fit=crop'

const members = [
  {
    id: 1,
    name: 'Иван Петров',
    username: 'ivan_cabrio',
    role: 'Участник',
    city: 'Минск',
    avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop',
    cars: [
      { id: 1, brand: 'BMW', photo: bmwPhoto },
      { id: 2, brand: 'Mazda', photo: mazdaPhoto },
    ],
  },
  {
    id: 2,
    name: 'Ольга Смирнова',
    username: 'olga_tt',
    role: 'Модератор',
    city: 'Минск',
    avatar: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&h=100&fit=crop',
    cars: [
      { id: 3, brand: 'Audi', photo: audiPhoto },
      { id: 4, brand: 'Mini', photo: miniPhoto },
      { id: 5, brand: 'Porsche', photo: porschePhoto },
      { id: 6, brand: 'BMW', photo: bmwPhoto },
    ],
  },
  {
    id: 3,
    name: 'Алексей Ковалёв',
    username: 'alex_kovalev',
    role: 'Участник',
    city: 'Гродно',
    avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop',
    cars: [{ id: 7, brand: 'Mini Cooper', photo: miniPhoto }],
  },
  {
    id: 4,
    name: 'Мария Волк',
    username: 'maria_v',
    role: 'Пользователь',
    city: 'Брест',
    avatar: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&h=100&fit=crop',
    cars: [],
  },
]

const filtered = computed(() =>
  members.filter((m) => (m.name + m.username).toLowerCase().includes(search.value.toLowerCase()))
)

function openMember(id: number) {
  router.push(`/participants/${id}`)
}
</script>

<template>
  <div class="page-shell pb-16">
    <PageHeader />
    <h1 class="text-name font-bold text-text mb-2 px-1">Участники клуба</h1>
    <SearchFilterBar v-model="search" placeholder="Поиск по имени или нику..." />
    <MemberListCard v-for="m in filtered" :key="m.id" :member="m" @click="openMember(m.id)" />
    <BottomNav />
  </div>
</template>
