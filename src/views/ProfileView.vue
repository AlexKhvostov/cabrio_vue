<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { PageHeader, Badge, EditableField, Section, CarMiniCard, EntityLinkRow, BottomNav, stars, ICONS, roleTone } from '@/components/ui'

const router = useRouter()
const editing = ref(false)

const profile = reactive({
  firstName: 'Иван',
  lastName: 'Петров',
  username: 'ivan_cabrio',
  role: 'Участник',
  city: 'Минск',
  country: 'Беларусь',
  phone: '+375 29 123-45-67',
  email: 'ivan.petrov@mail.by',
  bio: 'Люблю дальние поездки на закате. Каждое лето — слёт кабриолетов на Нарочи.',
  avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=300&h=300&fit=crop',
})
let snapshot = { ...profile }

const cars = [
  { id: 1, name: 'BMW Z4', year: 2019, photo: 'https://images.unsplash.com/photo-1555215695-3004980ad54e?w=300&h=200&fit=crop' },
  { id: 2, name: 'Mazda MX-5', year: 2021, photo: 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=300&h=200&fit=crop' },
]

const myEvents = [
  { id: 1, title: 'Слёт кабриолетов', date: '12 июня', place: 'Нарочь' },
  { id: 2, title: 'Пикник на Заславском вдхр.', date: '28 мая', place: 'Заславль' },
]

const myReviews = [
  { id: 1, title: 'Мойка «Блеск»', rating: 5 },
  { id: 2, title: 'СТО «АвтоПро»', rating: 4 },
]

function startEdit() {
  snapshot = { ...profile }
  editing.value = true
}
function cancelEdit() {
  Object.assign(profile, snapshot)
  editing.value = false
}
function saveEdit() {
  editing.value = false
}
</script>

<template>
  <div class="page-shell pb-16">
    <PageHeader>
      <template #actions>
        <div class="flex items-center gap-2">
          <button v-if="!editing" class="text-meta font-semibold text-accent2" @click="startEdit">Изменить</button>
          <template v-else>
            <button class="text-meta font-semibold text-muted" @click="cancelEdit">Отмена</button>
            <button class="text-meta font-semibold text-accent" @click="saveEdit">Сохранить</button>
          </template>
        </div>
      </template>
    </PageHeader>

    <div class="flex items-center gap-2.5">
      <div class="relative shrink-0">
        <img :src="profile.avatar" class="w-14 h-14 rounded-full object-cover ring-1 ring-accent/40" />
        <button v-if="editing" class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-accent text-bg text-micro flex items-center justify-center">
          📷
        </button>
      </div>
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-1.5 flex-wrap leading-none">
          <h1 class="text-name font-bold text-text truncate">{{ profile.firstName }} {{ profile.lastName }}</h1>
          <Badge :text="profile.role" :tone="roleTone(profile.role)" />
        </div>
        <div class="text-meta text-muted mt-0.5 truncate">@{{ profile.username }}</div>
      </div>
    </div>

    <div class="text-meta text-muted mt-3 mb-1 px-1 uppercase tracking-wide font-semibold">Основная информация</div>
    <div class="card">
      <EditableField label="Имя" v-model="profile.firstName" :editing="editing" />
      <EditableField label="Фамилия" v-model="profile.lastName" :editing="editing" />
      <EditableField label="Город" v-model="profile.city" :editing="editing" />
      <EditableField label="Страна" v-model="profile.country" :editing="editing" />
      <EditableField label="Телефон" v-model="profile.phone" :editing="editing" type="tel" />
      <EditableField label="Почта" v-model="profile.email" :editing="editing" type="email" />
    </div>

    <div class="card mt-2.5 px-3 py-2">
      <div class="text-meta text-muted mb-1">О себе</div>
      <textarea
        v-if="editing"
        v-model="profile.bio"
        rows="3"
        class="w-full bg-surface2 rounded-lg px-2 py-1.5 text-body text-text outline-none resize-none"
      />
      <p v-else class="text-body text-text leading-snug">{{ profile.bio }}</p>
    </div>

    <Section title="Мои автомобили" :count="cars.length">
      <div class="flex gap-2 overflow-x-auto scrollbar-hide -mx-1 px-1">
        <CarMiniCard v-for="c in cars" :key="c.id" :car="c" @click="router.push(`/cars/${c.id}`)" />
        <button
          class="shrink-0 w-24 h-full min-h-[88px] rounded-xl border border-dashed border-white/15 flex flex-col items-center justify-center gap-1 text-muted"
          @click="router.push('/cars')"
        >
          <span class="text-title leading-none">+</span>
          <span class="text-micro text-center leading-tight px-1">Добавить авто</span>
        </button>
      </div>
    </Section>

    <Section title="Мои мероприятия" :count="myEvents.length">
      <div class="card">
        <EntityLinkRow
          v-for="e in myEvents"
          :key="e.id"
          :title="e.title"
          :meta="e.date + ' · ' + e.place"
          :icon="ICONS.event"
          @click="router.push(`/events/${e.id}`)"
        />
      </div>
    </Section>

    <Section title="Мои отзывы" :count="myReviews.length">
      <div class="card">
        <EntityLinkRow
          v-for="r in myReviews"
          :key="r.id"
          :title="r.title"
          :meta="stars(r.rating)"
          :icon="ICONS.review"
          @click="router.push(`/services/${r.id}`)"
        />
      </div>
    </Section>

    <BottomNav />
  </div>
</template>
