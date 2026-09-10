<script setup lang="ts">
import { PageHeader, Badge, InfoRow, Section, EntityLinkRow, Avatar, carStatusTone, BottomNav } from '@/components/ui'

const car = {
  brand: 'BMW',
  model: 'Z4',
  color: 'Синий металлик',
  year: 2019,
  roof: 'Мягкая',
  power: '197 л.с.',
  volume: '2.0 л',
  vin: 'WBASN91060J1XXXXX',
  regNumber: 'скрыт',
  description: 'Взят новым, всё родное. Крыша открывается за 12 секунд на ходу до 40 км/ч.',
  status: 'Активен',
  photo: 'https://images.unsplash.com/photo-1555215695-3004980ad54e?w=800&h=450&fit=crop',
  owner: { name: 'Иван Петров', username: 'ivan_cabrio', avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop' },
}
</script>

<template>
  <div class="page-shell pb-16">
    <PageHeader />

    <figure class="relative rounded-2xl overflow-hidden mb-2.5">
      <img :src="car.photo" class="w-full h-40 object-cover" />
      <Badge :text="car.status" :tone="carStatusTone(car.status)" class="absolute top-2 right-2" />
    </figure>

    <div class="flex items-center justify-between px-1">
      <h1 class="text-name font-bold text-text">{{ car.brand }} {{ car.model }}</h1>
      <span class="text-meta text-muted">{{ car.year }} г.</span>
    </div>

    <button class="w-full flex items-center gap-2 px-1 py-1.5 mt-1 text-left">
      <Avatar :src="car.owner.avatar" :size="24" />
      <span class="text-meta text-muted">{{ car.owner.name }} · @{{ car.owner.username }}</span>
    </button>

    <Section title="Характеристики" :count="4">
      <div class="card">
        <InfoRow label="Цвет" :value="car.color" />
        <InfoRow label="Крыша" :value="car.roof" />
        <InfoRow label="Мощность" :value="car.power" />
        <InfoRow label="Объём" :value="car.volume" />
      </div>
    </Section>

    <Section title="Идентификация" :count="2">
      <div class="card">
        <InfoRow label="Гос. номер" :value="car.regNumber" />
        <InfoRow label="VIN" :value="car.vin" />
      </div>
    </Section>

    <div class="card mt-2.5 px-3 py-2">
      <div class="text-meta text-muted mb-0.5">Описание</div>
      <p class="text-body text-text leading-snug">{{ car.description }}</p>
    </div>

    <BottomNav />
  </div>
</template>
