<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { PageHeader, SearchFilterBar, BottomNav } from '@/components/ui'
import MemberListCard from '@/components/MemberListCard.vue'

const router = useRouter()
const search = ref('')

const members = [
  { id: 1, name: 'Иван Петров', username: 'ivan_cabrio', role: 'Участник', city: 'Минск', avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop', carBrands: 'BMW · Mazda' },
  { id: 2, name: 'Ольга Смирнова', username: 'olga_tt', role: 'Модератор', city: 'Минск', avatar: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&h=100&fit=crop', carBrands: 'Audi' },
  { id: 3, name: 'Алексей Ковалёв', username: 'alex_kovalev', role: 'Участник', city: 'Гродно', avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop', carBrands: 'Mini Cooper' },
  { id: 4, name: 'Мария Волк', username: 'maria_v', role: 'Пользователь', city: 'Брест', avatar: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&h=100&fit=crop', carBrands: '' },
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
