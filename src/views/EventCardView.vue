<script setup lang="ts">
import { ref, computed, reactive } from 'vue'
import { useRoute } from 'vue-router'
import { PageHeader, Badge, InfoRow, EditableField, PrimaryButton, EntityLinkRow, Section, eventWhenLabel, BottomNav } from '@/components/ui'

const route = useRoute()
const isNew = route.params.id === 'new'
const editing = ref(isNew)

const event = reactive({
  title: isNew ? '' : 'Слёт кабриолетов',
  dateIso: '2026-06-12',
  date: isNew ? '' : '12 июня 2026',
  time: isNew ? '' : '18:00',
  city: isNew ? '' : 'Нарочь',
  location: isNew ? '' : 'база отдыха «Волна»',
  type: isNew ? 'слёт' : 'слёт',
  limit: isNew ? 0 : 40,
  format: isNew ? 'открытое' : 'открытое',
  status: 'Активно',
  description: isNew ? '' : 'Ежегодный слёт клуба на озере. Живая музыка, конкурс на лучший тюнинг, ночёвка в палатках или домиках.',
  photo: 'https://images.unsplash.com/photo-1541447271487-09612b3f49f7?w=800&h=450&fit=crop',
})
let snapshot = { ...event }

const going = ref([
  { id: 1, name: 'Иван Петров', plusOne: false },
  { id: 2, name: 'Алексей Ковалёв', plusOne: true },
])
const maybeList = ref([{ id: 3, name: 'Ольга Смирнова' }])
const notGoing = ref([{ id: 4, name: 'Мария Волк' }])

function startEdit() {
  snapshot = { ...event }
  editing.value = true
}
function cancelEdit() {
  Object.assign(event, snapshot)
  editing.value = false
}
function saveEdit() {
  editing.value = false
}

const whenLabel = eventWhenLabel(event.dateIso)

const myStatus = ref<'yes' | 'maybe' | 'no' | ''>('yes')
const plusOne = ref(false)
const me = { id: 0, name: 'Вы' }

function removeMeFromAll() {
  going.value = going.value.filter((p) => p.id !== me.id)
  maybeList.value = maybeList.value.filter((p) => p.id !== me.id)
  notGoing.value = notGoing.value.filter((p) => p.id !== me.id)
}

function respond(status: 'yes' | 'maybe' | 'no') {
  myStatus.value = status
  removeMeFromAll()
  if (status === 'yes') going.value.unshift({ ...me, plusOne: plusOne.value })
  if (status === 'maybe') maybeList.value.unshift({ ...me })
  if (status === 'no') notGoing.value.unshift({ ...me })
}

function togglePlusOne() {
  plusOne.value = !plusOne.value
  if (myStatus.value === 'yes') {
    const idx = going.value.findIndex((p) => p.id === me.id)
    if (idx !== -1) going.value[idx].plusOne = plusOne.value
  }
}

const takenSeats = computed(() => going.value.length)
const totalAnswered = computed(() => going.value.length + maybeList.value.length + notGoing.value.length)
const occupancyPct = computed(() =>
  event.limit > 0 ? Math.min(100, Math.round((takenSeats.value / event.limit) * 100)) : 0
)
const spotsLeft = computed(() => (event.limit > 0 ? Math.max(0, event.limit - takenSeats.value) : null))
const capacityCaption = computed(() => {
  if (!event.limit) return 'лимита нет'
  return spotsLeft.value === 0
    ? `мест нет · ${takenSeats.value} из ${event.limit}`
    : `занято ${takenSeats.value} из ${event.limit} · свободно ${spotsLeft.value}`
})
const myCaption = computed(() => {
  if (myStatus.value === 'yes') return plusOne.value ? 'вы едете +1' : 'вы едете'
  if (myStatus.value === 'maybe') return 'вы думаете'
  if (myStatus.value === 'no') return 'вы не едете'
  return ''
})
</script>

<template>
  <div class="page-shell pb-16">
    <PageHeader>
      <template #actions>
        <div v-if="editing" class="flex gap-1.5">
          <button class="text-meta font-semibold text-muted px-2" @click="cancelEdit">Отмена</button>
          <button class="text-meta font-semibold text-accent px-2" @click="saveEdit">Сохранить</button>
        </div>
        <button v-else class="text-meta font-semibold text-accent2 px-2" @click="startEdit">Изменить</button>
      </template>
    </PageHeader>

    <figure class="relative rounded-2xl overflow-hidden mb-2.5">
      <img :src="event.photo" class="w-full h-40 object-cover" />
      <Badge v-if="!isNew" :text="event.status" class="absolute top-2 right-2" />
    </figure>

    <div class="flex items-center justify-between px-1">
      <h1 v-if="!editing" class="text-name font-bold text-text">{{ event.title || 'Новое мероприятие' }}</h1>
      <input v-else v-model="event.title" placeholder="Название" class="text-name font-bold text-text bg-surface2 rounded-lg px-2 py-1 outline-none flex-1 mr-2" />
      <div v-if="!isNew" class="flex items-center gap-1 shrink-0">
        <Badge :text="whenLabel" tone="accent2" />
        <Badge :text="event.type" tone="muted" />
      </div>
    </div>
    <p v-if="!editing" class="text-body text-muted mt-1.5 px-1 leading-snug">{{ event.description }}</p>
    <textarea
      v-else
      v-model="event.description"
      placeholder="Описание"
      rows="3"
      class="w-full bg-surface2 rounded-lg px-2 py-1.5 mt-1.5 text-body text-text outline-none resize-none"
    />

    <div class="card mt-2.5">
      <EditableField label="Дата" v-model="event.date" :editing="editing" placeholder="12 июня 2026" />
      <EditableField label="Время" v-model="event.time" :editing="editing" placeholder="18:00" />
      <EditableField label="Город" v-model="event.city" :editing="editing" placeholder="Минск" />
      <EditableField label="Место" v-model="event.location" :editing="editing" placeholder="площадка" />
      <InfoRow label="Лимит" :value="event.limit" />
      <EditableField label="Формат" v-model="event.format" :editing="editing" />
    </div>

    <template v-if="!isNew">
      <div class="card mt-2.5 px-3 py-2.5">
        <div class="text-meta text-muted uppercase tracking-wide text-micro font-bold mb-1.5">Регистрация</div>
        <div class="grid grid-cols-3 gap-2 text-center mb-2">
          <div class="rounded-xl bg-emerald-500/10 py-1.5">
            <div class="text-title font-extrabold text-emerald-400">{{ going.length }}</div>
            <div class="text-micro text-muted">едут</div>
          </div>
          <div class="rounded-xl bg-amber-500/10 py-1.5">
            <div class="text-title font-extrabold text-amber-400">{{ maybeList.length }}</div>
            <div class="text-micro text-muted">думают</div>
          </div>
          <div class="rounded-xl bg-rose-500/10 py-1.5">
            <div class="text-title font-extrabold text-rose-400">{{ notGoing.length }}</div>
            <div class="text-micro text-muted">нет</div>
          </div>
        </div>
        <div v-if="event.limit" class="h-1.5 rounded-full bg-white/10 overflow-hidden mb-1.5">
          <div class="h-full bg-accent" :style="{ width: occupancyPct + '%' }" />
        </div>
        <p class="text-label text-muted">
          {{ capacityCaption }}<span v-if="myCaption"> · {{ myCaption }}</span>
        </p>
      </div>

      <div class="card mt-2.5 px-3 py-2.5">
        <div class="text-meta text-muted uppercase tracking-wide text-micro font-bold mb-1.5">Ваш ответ</div>
        <div class="grid grid-cols-3 gap-1.5">
          <button
            class="rounded-xl py-1.5 text-meta font-semibold"
            :class="myStatus === 'yes' ? 'bg-accent text-bg' : 'bg-white/5 text-muted'"
            @click="respond('yes')"
          >
            Да
          </button>
          <button
            class="rounded-xl py-1.5 text-meta font-semibold"
            :class="myStatus === 'maybe' ? 'bg-accent text-bg' : 'bg-white/5 text-muted'"
            @click="respond('maybe')"
          >
            Возможно
          </button>
          <button
            class="rounded-xl py-1.5 text-meta font-semibold"
            :class="myStatus === 'no' ? 'bg-accent text-bg' : 'bg-white/5 text-muted'"
            @click="respond('no')"
          >
            Нет
          </button>
        </div>
        <label v-if="myStatus === 'yes'" class="flex items-center gap-1.5 mt-2 text-meta text-text">
          <input type="checkbox" :checked="plusOne" @change="togglePlusOne" />
          +1 гость
        </label>
      </div>

      <Section :title="'Кто ответил'" :count="totalAnswered">
        <div class="card divide-y divide-white/5">
          <div v-if="going.length" class="px-3 py-2">
            <div class="text-meta text-emerald-400 font-semibold mb-1">Едут · {{ going.length }}</div>
            <EntityLinkRow
              v-for="p in going"
              :key="p.id"
              :title="p.name"
              :meta="p.plusOne ? '+1 гость' : 'участник'"
              icon=""
            />
          </div>
          <div v-if="maybeList.length" class="px-3 py-2">
            <div class="text-meta text-amber-400 font-semibold mb-1">Думают · {{ maybeList.length }}</div>
            <EntityLinkRow v-for="p in maybeList" :key="p.id" :title="p.name" meta="участник" icon="" />
          </div>
          <div v-if="notGoing.length" class="px-3 py-2">
            <div class="text-meta text-rose-400 font-semibold mb-1">Не едут · {{ notGoing.length }}</div>
            <EntityLinkRow v-for="p in notGoing" :key="p.id" :title="p.name" meta="участник" icon="" />
          </div>
          <div v-if="!totalAnswered" class="px-3 py-2 text-meta text-muted">Пока никто не ответил</div>
        </div>
      </Section>
    </template>

    <div v-else class="mt-3">
      <PrimaryButton label="Создать мероприятие" @click="saveEdit" />
    </div>

    <BottomNav />
  </div>
</template>
