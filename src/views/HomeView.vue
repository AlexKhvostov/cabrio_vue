<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { StatTile, BottomNav, ClubInfoSheet } from '@/components/ui'

const router = useRouter()
const infoSheet = ref<'roles' | 'sections' | null>(null)

const stats = [
  { label: 'в клубе', value: 128, path: '/participants' },
  { label: 'кабриолетов', value: 96, path: '/cars' },
  { label: 'встреч', value: 34, path: '/events' },
  { label: 'отзывов', value: 57, path: '/services' },
  { label: 'городов', value: 12, path: '/participants' },
  { label: 'на карте', value: 41, path: '/map' },
]
</script>

<template>
  <div class="page-shell pb-16">
    <figure class="relative rounded-2xl overflow-hidden mb-3">
      <img
        src="https://images.unsplash.com/photo-1541447271487-09612b3f49f7?w=800&h=450&fit=crop"
        class="w-full h-40 object-cover"
      />
      <span
        class="absolute inset-0"
        style="background: linear-gradient(180deg, rgba(22,26,46,0) 40%, rgba(22,26,46,0.92) 100%)"
      ></span>
      <figcaption class="absolute bottom-2 left-3 right-3">
        <h1 class="text-title font-extrabold text-text">Привет, Иван</h1>
        <p class="text-meta text-muted">Клуб владельцев кабриолетов</p>
      </figcaption>
    </figure>

    <div class="grid grid-cols-3 gap-2">
      <StatTile v-for="s in stats" :key="s.label" :value="s.value" :label="s.label" @click="router.push(s.path)" />
    </div>

    <div class="card mt-3 px-3 py-2.5">
      <div class="text-meta text-accent2 font-bold uppercase tracking-wide">про нас</div>
      <h2 class="text-title font-extrabold text-text mt-0.5">Люди с поехавшей крышей</h2>
      <p class="text-body text-muted mt-1 leading-snug">
        Не лента для всех, а свои: кто на чём ездит, где катаются и когда следующая встреча. Минск и вся Беларусь.
      </p>
      <div class="flex gap-2 mt-2">
        <a class="flex-1 rounded-xl bg-surface2 px-3 py-2 text-center" href="https://cabrioride.by" target="_blank" rel="noopener">
          <div class="text-body font-bold text-text">Сайт клуба</div>
          <div class="text-micro text-muted">cabrioride.by</div>
        </a>
        <a class="flex-1 rounded-xl bg-surface2 px-3 py-2 text-center" href="#" target="_blank" rel="noopener">
          <div class="text-body font-bold text-text">Чат в Telegram</div>
          <div class="text-micro text-muted">живой разговор</div>
        </a>
      </div>
    </div>

    <div class="card mt-3 px-3 py-2.5">
      <div class="text-meta text-accent2 font-bold uppercase tracking-wide">справка</div>
      <h2 class="text-title font-extrabold text-text mt-0.5">Как устроен клуб</h2>
      <p class="text-body text-muted mt-1 leading-snug">Роли людей и что умеет каждый раздел приложения.</p>
      <div class="flex gap-2 mt-2">
        <button class="flex-1 rounded-xl bg-surface2 px-3 py-2 text-center" @click="infoSheet = 'roles'">
          <div class="text-body font-bold text-text">Роли и доступы</div>
          <div class="text-micro text-muted">какие есть и как получить</div>
        </button>
        <button class="flex-1 rounded-xl bg-surface2 px-3 py-2 text-center" @click="infoSheet = 'sections'">
          <div class="text-body font-bold text-text">Разделы</div>
          <div class="text-micro text-muted">для чего и кто видит</div>
        </button>
      </div>
    </div>

    <p class="text-meta text-muted mt-3 px-1 leading-snug">Своё фото в профиле — и в списке участников вас сразу узнают.</p>

    <BottomNav />

    <ClubInfoSheet v-if="infoSheet" :kind="infoSheet" @close="infoSheet = null" />
  </div>
</template>
