<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { PageHeader, Section, SearchFilterBar, FilterSelect, BottomNav } from '@/components/ui'
import CarListCard from '@/components/CarListCard.vue'

const router = useRouter()
const search = ref('')
const statusFilter = ref('Все статусы')
const statusOptions = [
  { value: 'Все статусы', label: 'Все статусы' },
  { value: 'Активен', label: 'Активен' },
  { value: 'В ремонте', label: 'В ремонте' },
  { value: 'Продан', label: 'Продан' },
]

const cars = [
  {
    id: 1,
    name: 'BMW Z4',
    year: 2019,
    city: 'Минск',
    owner: 'Иван Петров',
    ownerAvatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop',
    photo: 'https://images.unsplash.com/photo-1555215695-3004980ad54e?w=400&h=250&fit=crop',
    status: 'Активен',
  },
  {
    id: 2,
    name: 'Mazda MX-5',
    year: 2021,
    city: 'Гродно',
    owner: 'Иван Петров',
    ownerAvatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop',
    photo: 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=400&h=250&fit=crop',
    status: 'В ремонте',
  },
  {
    id: 3,
    name: 'Audi TT Roadster',
    year: 2017,
    city: 'Минск',
    owner: 'Ольга Смирнова',
    ownerAvatar: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&h=100&fit=crop',
    photo: 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=400&h=250&fit=crop',
    status: 'Активен',
  },
]

const filtered = computed(() =>
  cars.filter(
    (c) =>
      c.name.toLowerCase().includes(search.value.toLowerCase()) &&
      (statusFilter.value === 'Все статусы' || c.status === statusFilter.value)
  )
)
</script>

<template>
  <div class="page-shell pb-16">
    <PageHeader />
    <h1 class="text-name font-bold text-text mb-2 px-1">Автомобили клуба</h1>
    <SearchFilterBar v-model="search" placeholder="Поиск по модели...">
      <template #filter>
        <FilterSelect v-model="statusFilter" :options="statusOptions" />
      </template>
    </SearchFilterBar>
    <Section title="Все автомобили" :count="filtered.length">
      <CarListCard v-for="c in filtered" :key="c.id" :car="c" @click="router.push(`/cars/${c.id}`)" />
    </Section>
    <BottomNav />
  </div>
</template>
