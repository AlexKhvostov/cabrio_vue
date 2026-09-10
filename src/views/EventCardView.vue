<script setup lang="ts">
import { ref, computed, reactive } from 'vue'
import { useRoute } from 'vue-router'
import { PageHeader, Badge, InfoRow, EditableField, PrimaryButton, EntityLinkRow, Section, eventWhenLabel } from '@/components/ui'

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

const going = [
  { id: 1, name: 'Иван Петров' },
  { id: 2, name: 'Алексей Ковалёв' },
]
const maybeList = [{ id: 3, name: 'Ольга Смирнова' }]
const notGoing = [{ id: 4, name: 'Мария Волк' }]

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
const takenSeats = computed(() => going.length)
const tabs = ['Едут', 'Думают', 'Не едут'] as const
const activeTab = ref<(typeof tabs)[number]>('Едут')
const tabList = computed(() => {
  if (activeTab.value === 'Едут') return going
  if (activeTab.value === 'Думают') return maybeList
  return notGoing
})
const myStatus = ref<'going' | 'maybe' | null>('going')

function respond(status: 'going' | 'maybe') {
  myStatus.value = myStatus.value === status ? null : status
}
</script>

<template>
  <div class="page-shell">
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
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-meta text-muted">Регистрация</span>
          <span class="text-meta font-semibold text-text">{{ takenSeats }} / {{ event.limit }} мест</span>
        </div>
        <div class="h-1.5 rounded-full bg-white/10 overflow-hidden mb-2">
          <div class="h-full bg-accent" :style="{ width: Math.min(100, (takenSeats / event.limit) * 100) + '%' }" />
        </div>
        <div class="flex items-center gap-3 text-meta text-muted">
          <span>🚗 едут: {{ going.length }}</span>
          <span>🤔 думают: {{ maybeList.length }}</span>
          <span>❌ не едут: {{ notGoing.length }}</span>
        </div>
        <p v-if="myStatus" class="text-label text-accent mt-1.5">
          {{ myStatus === 'going' ? 'Вы едете на это мероприятие' : 'Вы отметили «думаю»' }}
        </p>
      </div>

      <Section :title="'Кто ответил'" :count="going.length + maybeList.length + notGoing.length">
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
    </template>

    <div v-else class="mt-3">
      <PrimaryButton label="Создать мероприятие" @click="saveEdit" />
    </div>
  </div>
</template>
