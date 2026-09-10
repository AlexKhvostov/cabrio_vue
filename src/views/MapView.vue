<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { BottomNav, Spinner, Toast } from '@/components/ui'

const router = useRouter()

const peopleOnMap = ref([
  { id: 1, name: 'Иван Петров', username: 'ivan_cabrio', avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop', x: 42, y: 38, ageMin: 1 },
  { id: 2, name: 'Ольга Смирнова', username: 'olga_s', avatar: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&h=100&fit=crop', x: 65, y: 55, ageMin: 12 },
  { id: 3, name: 'Алексей Ковалёв', username: 'alex_k', avatar: 'https://images.unsplash.com/photo-1552058544-f2b08422138a?w=100&h=100&fit=crop', x: 28, y: 62, ageMin: 47 },
])

function relativeTime(mins: number): string {
  if (mins < 1) return 'только что'
  if (mins < 60) return mins + 'м'
  const h = Math.floor(mins / 60)
  return h + 'ч'
}

const listOpen = ref(false)
const sharing = ref(false)
const following = ref(false)
const loading = ref(false)
const errorMsg = ref('')
const toastMsg = ref('')
let toastTimer: ReturnType<typeof setTimeout> | null = null

function showToast(msg: string) {
  toastMsg.value = msg
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => (toastMsg.value = ''), 2200)
}

function toggleSharing() {
  errorMsg.value = ''
  if (sharing.value) {
    sharing.value = false
    following.value = false
    showToast('Тебя больше не видно на карте')
    return
  }
  if (!navigator.geolocation) {
    errorMsg.value = 'В этом приложении нет геолокации. Карту смотреть можно.'
    return
  }
  loading.value = true
  navigator.geolocation.getCurrentPosition(
    () => {
      loading.value = false
      sharing.value = true
      following.value = true
    },
    () => {
      loading.value = false
      errorMsg.value = 'Не удалось включить геолокацию. Карту смотреть можно.'
    },
    { enableHighAccuracy: true, timeout: 8000 }
  )
}

const selectedPerson = ref<(typeof peopleOnMap.value)[number] | null>(null)
function focusPerson(p: (typeof peopleOnMap.value)[number]) {
  following.value = false
  selectedPerson.value = p
}
function openProfile(id: number) {
  router.push(`/participants/${id}`)
}
</script>

<template>
  <div class="relative h-screen overflow-hidden pb-16">
    <div
      class="absolute inset-0 bg-surface2"
      style="background-image: radial-gradient(circle at 30% 30%, rgba(94,200,216,0.12), transparent 50%), radial-gradient(circle at 70% 70%, rgba(255,138,92,0.12), transparent 50%)"
    >
      <button
        v-for="p in peopleOnMap"
        :key="p.id"
        class="absolute -translate-x-1/2 -translate-y-full flex flex-col items-center"
        :style="{ left: p.x + '%', top: p.y + '%' }"
        @click="focusPerson(p)"
      >
        <img :src="p.avatar" class="w-9 h-9 rounded-full object-cover ring-2 ring-accent2" />
      </button>
    </div>

    <Spinner v-if="loading" />

    <div class="absolute top-3 left-3 right-3 z-10">
      <button
        class="px-3 py-1.5 rounded-full bg-surface text-meta font-semibold text-text shadow"
        @click="listOpen = !listOpen"
      >
        Сейчас на карте — {{ peopleOnMap.length }}
      </button>
      <div v-if="listOpen" class="card mt-1.5 p-1 max-h-56 overflow-y-auto">
        <button
          v-for="p in peopleOnMap"
          :key="p.id"
          class="w-full flex items-center gap-2 px-2 py-1.5 text-left"
          @click="focusPerson(p)"
        >
          <img :src="p.avatar" class="w-6 h-6 rounded-full object-cover" />
          <div class="min-w-0 flex-1">
            <div class="text-body text-text truncate">{{ p.name }}</div>
            <div class="text-micro text-muted">@{{ p.username }}</div>
          </div>
          <span class="text-micro text-muted shrink-0">{{ relativeTime(p.ageMin) }}</span>
        </button>
        <div v-if="!peopleOnMap.length" class="px-2 py-1.5 text-meta text-muted">Никого на карте</div>
      </div>
    </div>

    <div v-if="errorMsg" class="absolute top-16 left-3 right-3 z-10 card px-3 py-2 text-meta text-accent">
      {{ errorMsg }}
    </div>

    <div v-if="selectedPerson" class="absolute bottom-20 left-3 right-3 z-10 card px-3 py-2 flex items-center gap-2.5">
      <img :src="selectedPerson.avatar" class="w-10 h-10 rounded-full object-cover" />
      <div class="min-w-0 flex-1">
        <div class="text-body font-semibold text-text truncate">{{ selectedPerson.name }}</div>
        <div class="text-micro text-muted">был(а) в сети {{ relativeTime(selectedPerson.ageMin) }} назад</div>
      </div>
      <button class="text-meta font-semibold text-accent2 shrink-0" @click="openProfile(selectedPerson.id)">Профиль</button>
    </div>

    <Toast :message="toastMsg" />

    <div class="absolute bottom-20 right-3 z-10 flex flex-col gap-2">
      <button
        v-if="sharing"
        class="icon-button w-10 h-10"
        :class="following ? 'bg-accent text-bg' : 'bg-surface text-text'"
        @click="following = !following"
      >
        ⦿
      </button>
      <button
        class="icon-button w-10 h-10"
        :class="sharing ? 'bg-accent text-bg' : 'bg-surface text-text'"
        @click="toggleSharing"
      >
        ⏻
      </button>
    </div>

    <BottomNav />
  </div>
</template>

