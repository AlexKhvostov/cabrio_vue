<script setup lang="ts">
import { useRouter } from 'vue-router'
import { PageHeader, Badge, PrimaryButton, InfoRow, EntityLinkRow, CarMiniCard, Section, Avatar, stars, ICONS, roleTone } from '@/components/ui'

const router = useRouter()

const participant = {
  name: 'Иван Петров',
  username: 'ivan_cabrio',
  role: 'Участник',
  city: 'Минск',
  country: 'Беларусь',
  phone: '+375 29 123-45-67',
  email: 'ivan.petrov@mail.by',
  joinDate: '14 мая 2023',
  avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=300&h=300&fit=crop',
  bio: 'Люблю дальние поездки на закате. Каждое лето — слёт кабриолетов на Нарочи. С крышей вниз при любой погоде выше +15.',
  cars: [
    { id: 1, name: 'BMW Z4', year: 2019, photo: 'https://images.unsplash.com/photo-1555215695-3004980ad54e?w=300&h=200&fit=crop' },
    { id: 2, name: 'Mazda MX-5', year: 2021, photo: 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=300&h=200&fit=crop' },
  ],
  events: [
    { id: 1, title: 'Слёт кабриолетов', date: '12 июня', place: 'Нарочь' },
    { id: 2, title: 'Пикник на Заславском вдхр.', date: '28 мая', place: 'Заславль' },
    { id: 3, title: 'Ночной заезд', date: '3 мая', place: 'Минск' },
  ],
  reviews: [
    { id: 1, title: 'Мойка «Блеск»', rating: 5 },
    { id: 2, title: 'СТО «АвтоПро»', rating: 4 },
    { id: 3, title: 'Шиномонтаж «Колесо»', rating: 5 },
  ],
}
</script>

<template>
  <div class="page-shell">
    <PageHeader />

    <div class="flex items-center gap-2.5">
      <Avatar :src="participant.avatar" :size="48" />
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-1.5 flex-wrap leading-none">
          <h1 class="text-name font-bold text-text truncate">{{ participant.name }}</h1>
          <Badge :text="participant.role" :tone="roleTone(participant.role)" />
        </div>
        <div class="text-meta text-muted mt-0.5 truncate">
          @{{ participant.username }} · {{ participant.city }} · с {{ participant.joinDate }}
        </div>
      </div>
      <PrimaryButton label="Написать" size="sm" />
    </div>

    <div class="card mt-2.5">
      <InfoRow label="Город" :value="participant.city" />
      <InfoRow label="Страна" :value="participant.country" />
      <InfoRow label="Телефон" :value="participant.phone" />
      <InfoRow label="Почта" :value="participant.email" />
      <div class="px-3 py-1.5">
        <div class="text-meta text-muted mb-0.5">О себе</div>
        <p class="text-body text-text leading-snug">{{ participant.bio }}</p>
      </div>
    </div>

    <Section title="Автомобили" :count="participant.cars.length">
      <div class="flex gap-2 overflow-x-auto scrollbar-hide -mx-1 px-1">
        <CarMiniCard v-for="c in participant.cars" :key="c.id" :car="c" @click="router.push(`/cars/${c.id}`)" />
      </div>
    </Section>

    <Section title="Мероприятия" :count="participant.events.length">
      <div class="card">
        <EntityLinkRow
          v-for="e in participant.events"
          :key="e.id"
          :title="e.title"
          :meta="e.date + ' · ' + e.place"
          :icon="ICONS.event"
          @click="router.push(`/events/${e.id}`)"
        />
      </div>
    </Section>

    <Section title="Отзывы" :count="participant.reviews.length">
      <div class="card">
        <EntityLinkRow
          v-for="r in participant.reviews"
          :key="r.id"
          :title="r.title"
          :meta="stars(r.rating)"
          :icon="ICONS.review"
          @click="router.push(`/services/${r.id}`)"
        />
      </div>
    </Section>
  </div>
</template>
