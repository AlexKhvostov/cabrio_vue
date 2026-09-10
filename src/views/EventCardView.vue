<script setup lang="ts">
import { PageHeader, Badge, InfoRow, PrimaryButton, EntityLinkRow, Section } from '@/components/ui'

const event = {
  title: 'Слёт кабриолетов',
  date: '12 июня 2026',
  time: '18:00',
  city: 'Нарочь',
  location: 'база отдыха «Волна»',
  type: 'слёт',
  limit: 40,
  format: 'открытое',
  status: 'Активно',
  description: 'Ежегодный слёт клуба на озере. Живая музыка, конкурс на лучший тюнинг, ночёвка в палатках или домиках.',
  photo: 'https://images.unsplash.com/photo-1541447271487-09612b3f49f7?w=800&h=450&fit=crop',
  participants: [
    { id: 1, name: 'Иван Петров' },
    { id: 2, name: 'Ольга Смирнова' },
    { id: 3, name: 'Алексей Ковалёв' },
  ],
}
</script>

<template>
  <div class="page-shell">
    <PageHeader />

    <figure class="relative rounded-2xl overflow-hidden mb-2.5">
      <img :src="event.photo" class="w-full h-40 object-cover" />
      <Badge :text="event.status" class="absolute top-2 right-2" />
    </figure>

    <div class="flex items-center justify-between px-1">
      <h1 class="text-name font-bold text-text">{{ event.title }}</h1>
      <Badge :text="event.type" tone="muted" />
    </div>
    <p class="text-body text-muted mt-1.5 px-1 leading-snug">{{ event.description }}</p>

    <div class="card mt-2.5">
      <InfoRow label="Дата" :value="event.date" />
      <InfoRow label="Время" :value="event.time" />
      <InfoRow label="Город" :value="event.city" />
      <InfoRow label="Место" :value="event.location" />
      <InfoRow label="Лимит" :value="event.limit" />
      <InfoRow label="Формат" :value="event.format" />
    </div>

    <Section title="Участники" :count="event.participants.length">
      <div class="card">
        <EntityLinkRow v-for="p in event.participants" :key="p.id" :title="p.name" meta="участник" icon="" />
      </div>
    </Section>

    <div class="mt-3">
      <PrimaryButton label="Пойду" />
    </div>
  </div>
</template>
