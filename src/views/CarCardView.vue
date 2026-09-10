<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRoute } from 'vue-router'
import { PageHeader, Badge, InfoRow, EditableField, Section, Avatar, PrimaryButton, carStatusTone, BottomNav } from '@/components/ui'

const route = useRoute()
const isNew = route.params.id === 'new'
const editing = ref(isNew)
const statusOptions = ['Активен', 'На модерации', 'Визитка', 'В архиве', 'Заблокирован', 'Удалён']

const car = reactive({
  brand: isNew ? '' : 'BMW',
  model: isNew ? '' : 'Z4',
  color: isNew ? '' : 'Синий металлик',
  year: isNew ? '' : '2019',
  roof: isNew ? '' : 'Мягкая',
  power: isNew ? '' : '197 л.с.',
  volume: isNew ? '' : '2.0 л',
  vin: isNew ? '' : 'WBASN91060J1XXXXX',
  regNumber: isNew ? '' : 'скрыт',
  hideReg: true,
  description: isNew ? '' : 'Взят новым, всё родное. Крыша открывается за 12 секунд на ходу до 40 км/ч.',
  status: 'Активен',
  photo: 'https://images.unsplash.com/photo-1555215695-3004980ad54e?w=800&h=450&fit=crop',
  owner: { name: 'Иван Петров', username: 'ivan_cabrio', avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop' },
})
let snapshot = { ...car }

function startEdit() {
  snapshot = { ...car }
  editing.value = true
}
function cancelEdit() {
  Object.assign(car, snapshot)
  editing.value = false
}
function saveEdit() {
  editing.value = false
}

const photoInput = ref<HTMLInputElement | null>(null)
function pickPhoto() {
  photoInput.value?.click()
}
function onPhotoChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => {
    car.photo = reader.result as string
  }
  reader.readAsDataURL(file)
}

function deleteCar() {
  if (!confirm('Машина будет помечена как удалённая. Продолжить?')) return
  editing.value = false
}
</script>

<template>
  <div class="page-shell pb-16">
    <PageHeader>
      <template #actions>
        <div v-if="editing && !isNew" class="flex gap-1.5">
          <button class="text-meta font-semibold text-muted px-2" @click="cancelEdit">Отмена</button>
          <button class="text-meta font-semibold text-accent px-2" @click="saveEdit">Сохранить</button>
        </div>
        <button v-else-if="!isNew" class="text-meta font-semibold text-accent2 px-2" @click="startEdit">Изменить</button>
      </template>
    </PageHeader>

    <figure class="relative rounded-2xl overflow-hidden mb-2.5">
      <img :src="car.photo" class="w-full h-40 object-cover" />
      <Badge :text="car.status" :tone="carStatusTone(car.status)" class="absolute top-2 right-2" />
      <button
        v-if="editing"
        class="absolute bottom-2 right-2 bg-bg/80 text-text text-meta font-semibold px-2.5 py-1 rounded-lg"
        @click="pickPhoto"
      >
        📷 Фото
      </button>
      <input ref="photoInput" type="file" accept="image/*" class="hidden" @change="onPhotoChange" />
    </figure>

    <div class="flex items-center justify-between px-1">
      <template v-if="!editing">
        <h1 class="text-name font-bold text-text">{{ car.brand }} {{ car.model }}</h1>
        <span class="text-meta text-muted">{{ car.year }} г.</span>
      </template>
      <template v-else>
        <input v-model="car.brand" placeholder="Марка" class="text-name font-bold text-text bg-surface2 rounded-lg px-2 py-1 outline-none flex-1 mr-1.5" />
        <input v-model="car.model" placeholder="Модель" class="text-name font-bold text-text bg-surface2 rounded-lg px-2 py-1 outline-none flex-1" />
      </template>
    </div>

    <button class="w-full flex items-center gap-2 px-1 py-1.5 mt-1 text-left">
      <Avatar :src="car.owner.avatar" :size="24" />
      <span class="text-meta text-muted">{{ car.owner.name }} · @{{ car.owner.username }}</span>
    </button>

    <Section title="Характеристики" :count="4">
      <div class="card">
        <EditableField label="Год" v-model="car.year" :editing="editing" placeholder="2019" />
        <EditableField label="Цвет" v-model="car.color" :editing="editing" placeholder="Синий" />
        <EditableField label="Крыша" v-model="car.roof" :editing="editing" placeholder="Мягкая/Жёсткая" />
        <EditableField label="Мощность" v-model="car.power" :editing="editing" placeholder="197 л.с." />
        <EditableField label="Объём" v-model="car.volume" :editing="editing" placeholder="2.0 л" />
      </div>
    </Section>

    <Section title="Идентификация" :count="2">
      <div class="card">
        <EditableField label="Гос. номер" v-model="car.regNumber" :editing="editing" placeholder="1234 AB-1" />
        <EditableField label="VIN" v-model="car.vin" :editing="editing" placeholder="VIN" />
        <label v-if="editing" class="flex items-center gap-1.5 px-3 py-1.5 text-meta text-text">
          <input type="checkbox" v-model="car.hideReg" />
          Скрывать номер от других участников
        </label>
      </div>
    </Section>

    <div v-if="editing" class="card mt-2.5 px-3 py-2">
      <div class="text-meta text-muted mb-1">Статус</div>
      <select v-model="car.status" class="w-full bg-surface2 rounded-lg px-2 py-1.5 text-body text-text outline-none">
        <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
      </select>
    </div>
    <div v-else class="card mt-2.5">
      <InfoRow label="Статус" :value="car.status" />
    </div>

    <div class="card mt-2.5 px-3 py-2">
      <div class="text-meta text-muted mb-0.5">Описание</div>
      <p v-if="!editing" class="text-body text-text leading-snug">{{ car.description }}</p>
      <textarea
        v-else
        v-model="car.description"
        rows="3"
        placeholder="Описание"
        class="w-full bg-surface2 rounded-lg px-2 py-1.5 text-body text-text outline-none resize-none"
      />
    </div>

    <div v-if="isNew" class="mt-3">
      <PrimaryButton label="Добавить машину" @click="saveEdit" />
    </div>
    <button
      v-if="editing && !isNew"
      class="w-full mt-3 py-2.5 rounded-xl bg-rose-500/15 text-rose-400 font-bold text-title active:scale-95 transition-transform"
      @click="deleteCar"
    >
      Удалить машину
    </button>

    <BottomNav />
  </div>
</template>
