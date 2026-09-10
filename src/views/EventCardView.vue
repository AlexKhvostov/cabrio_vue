<script setup lang="ts">
import { ref, computed } from 'vue'
import { PageHeader, Badge, InfoRow, PrimaryButton, EntityLinkRow, Section, eventWhenLabel } from '@/components/ui'

const event = {
  title: 'Слёт кабриолетов',
  dateIso: '2026-06-12',
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
  going: [
    { id: 1, name: 'Иван Петров' },
    { id: 2, name: 'Алексей Ковалёв' },
  ],
  maybe: [{ id: 3, name: 'Ольга Смирнова' }],
  notGoing: [{ id: 4, name: 'Мария Волк' }],
}

const whenLabel = eventWhenLabel(event.dateIso)
const takenSeats = computed(() => event.going.length)
const tabs = ['Едут', 'Думают', 'Не едут'] as const
const activeTab = ref<(typeof tabs)[number]>('Едут')
const tabList = computed(() => {
  if (activeTab.value === 'Едут') return event.going
  if (activeTab.value === 'Думают') return event.maybe
  return event.notGoing
})
const myStatus = ref<'going' | 'maybe' | null>('going')

function respond(status: 'going' | 'maybe') {
  myStatus.value = myStatus.value === status ? null : status
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
      <div class="flex items-center gap-1 shrink-0">
        <Badge :text="whenLabel" tone="accent2" />
        <Badge :text="event.type" tone="muted" />
      </div>
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

    <div class="card mt-2.5 px-3 py-2.5">
      <div class="flex items-center justify-between mb-1.5">
        <span class="text-meta text-muted">Регистрация</span>
        <span class="text-meta font-semibold text-text">{{ takenSeats }} / {{ event.limit }} мест</span>
      </div>
      <div class="h-1.5 rounded-full bg-white/10 overflow-hidden mb-2">
        <div class="h-full bg-accent" :style="{ width: Math.min(100, (takenSeats / event.limit) * 100) + '%' }" />
      </div>
      <div class="flex items-center gap-3 text-meta text-muted">
        <span>🚗 едут: {{ event.going.length }}</span>
        <span>🤔 думают: {{ event.maybe.length }}</span>
        <span>❌ не едут: {{ event.notGoing.length }}</span>
      </div>
      <p v-if="myStatus" class="text-label text-accent mt-1.5">
        {{ myStatus === 'going' ? 'Вы едете на это мероприятие' : 'Вы отметили «думаю»' }}
      </p>
    </div>

    <Section :title="'Кто ответил'" :count="event.going.length + event.maybe.length + event.notGoing.length">
      <div class="flex gap-1.5 mb-1.5 px-1">
        <button
          v-for="t in tabs"
          :key="t"
          class="px-2.5 py-1 rounded-full text-meta font-semibold"
          :class="activeTab === t ? 'bg-accent text-bg' : 'bg-white/5 text-muted'"
          @click="activeTab = t"
        >
          {{ t }}
        </button>
      </div>
      <div class="card">
        <EntityLinkRow v-for="p in tabList" :key="p.id" :title="p.name" meta="участник" icon="" />
        <div v-if="!tabList.length" class="px-3 py-2 text-meta text-muted">не указано</div>
      </div>
    </Section>

    <div class="mt-3 flex gap-2">
      <div class="flex-1">
        <PrimaryButton :label="myStatus === 'going' ? '✓ Еду' : 'Пойду'" @click="respond('going')" />
      </div>
      <PrimaryButton :label="myStatus === 'maybe' ? '✓ Думаю' : 'Думаю'" size="sm" @click="respond('maybe')" />
    </div>
  </div>
</template>
