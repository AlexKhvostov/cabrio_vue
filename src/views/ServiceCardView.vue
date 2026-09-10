<script setup lang="ts">
import { ref, reactive } from 'vue'
import { PageHeader, Badge, InfoRow, PrimaryButton, Section, StarRating, ratingTone, BottomNav } from '@/components/ui'

const showForm = ref(false)
const editingReviewId = ref<number | null>(null)
const editingPlace = ref(false)
const newReview = reactive({ quality: 5, speed: 5, price: 5, text: '' })
let nextId = 3

const place = reactive({
  name: 'Мойка «Блеск»',
  city: 'Минск',
  description: 'Ручная мойка, аккуратно с мягким верхом. Специализируются на кабриолетах.',
  photo: 'https://images.unsplash.com/photo-1520340356584-f9917d1eea6f?w=800&h=450&fit=crop',
  tags: ['мойка', 'минск', 'ручная'],
  ratingQuality: 5,
  ratingSpeed: 4,
  ratingPrice: 4,
  avgRating: 4.3,
  reviews: [
    { id: 1, author: 'Иван Петров', quality: 5, speed: 4, price: 4, text: 'Аккуратно вымыли, крышу не залили — то, что нужно.' },
    { id: 2, author: 'Ольга Смирнова', quality: 5, speed: 5, price: 3, text: 'Быстро, чисто, но дороговато.' },
  ],
})
let placeSnapshot = { ...place }

function submitReview() {
  if (!newReview.text.trim()) return
  if (editingReviewId.value !== null) {
    const r = place.reviews.find((x) => x.id === editingReviewId.value)
    if (r) {
      r.quality = newReview.quality
      r.speed = newReview.speed
      r.price = newReview.price
      r.text = newReview.text
    }
  } else {
    place.reviews.unshift({ id: nextId++, author: 'Вы', quality: newReview.quality, speed: newReview.speed, price: newReview.price, text: newReview.text })
  }
  closeForm()
}
function closeForm() {
  newReview.text = ''
  newReview.quality = 5
  newReview.speed = 5
  newReview.price = 5
  editingReviewId.value = null
  showForm.value = false
}
function editReview(r: { id: number; quality: number; speed: number; price: number; text: string }) {
  editingReviewId.value = r.id
  newReview.quality = r.quality
  newReview.speed = r.speed
  newReview.price = r.price
  newReview.text = r.text
  showForm.value = true
}
function deleteReview(id: number) {
  if (!confirm('Удалить отзыв?')) return
  place.reviews = place.reviews.filter((r) => r.id !== id)
}

function startEditPlace() {
  placeSnapshot = { ...place }
  editingPlace.value = true
}
function cancelEditPlace() {
  Object.assign(place, placeSnapshot)
  editingPlace.value = false
}
function saveEditPlace() {
  editingPlace.value = false
}
function deletePlace() {
  if (!confirm('Место будет помечено как удалённое. Продолжить?')) return
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
    place.photo = reader.result as string
  }
  reader.readAsDataURL(file)
}
</script>

<template>
  <div class="page-shell pb-16">
    <PageHeader>
      <template #actions>
        <div v-if="editingPlace" class="flex gap-1.5">
          <button class="text-meta font-semibold text-muted px-2" @click="cancelEditPlace">Отмена</button>
          <button class="text-meta font-semibold text-accent px-2" @click="saveEditPlace">Сохранить</button>
        </div>
        <button v-else class="text-meta font-semibold text-accent2 px-2" @click="startEditPlace">Изменить</button>
      </template>
    </PageHeader>

    <figure class="relative rounded-2xl overflow-hidden mb-2.5">
      <img :src="place.photo" class="w-full h-40 object-cover" />
      <Badge :text="`${place.avgRating} / 5`" :tone="ratingTone(place.avgRating)" class="absolute top-2 right-2" />
      <button
        v-if="editingPlace"
        class="absolute bottom-2 right-2 bg-bg/80 text-text text-meta font-semibold px-2.5 py-1 rounded-lg"
        @click="pickPhoto"
      >
        📷 Фото
      </button>
      <input ref="photoInput" type="file" accept="image/*" class="hidden" @change="onPhotoChange" />
    </figure>

    <template v-if="!editingPlace">
      <h1 class="text-name font-bold text-text px-1">{{ place.name }}</h1>
      <p class="text-meta text-muted px-1 mt-0.5">{{ place.city }}</p>
      <div class="flex flex-wrap gap-1 px-1 mt-1">
        <span v-for="t in place.tags" :key="t" class="text-label text-accent2">#{{ t }}</span>
      </div>
      <p class="text-body text-muted mt-1.5 px-1 leading-snug">{{ place.description }}</p>
    </template>
    <template v-else>
      <input v-model="place.name" placeholder="Название" class="text-name font-bold text-text bg-surface2 rounded-lg px-2 py-1 outline-none mx-1 mb-1" />
      <input v-model="place.city" placeholder="Город" class="text-body text-text bg-surface2 rounded-lg px-2 py-1 outline-none mx-1 mb-1" />
      <input
        :value="place.tags.join(', ')"
        @change="place.tags = ($event.target as HTMLInputElement).value.split(',').map((t) => t.trim()).filter(Boolean)"
        placeholder="Теги через запятую"
        class="text-body text-text bg-surface2 rounded-lg px-2 py-1 outline-none mx-1 mb-1"
      />
      <textarea
        v-model="place.description"
        rows="3"
        placeholder="Описание"
        class="text-body text-text bg-surface2 rounded-lg px-2 py-1.5 outline-none resize-none mx-1"
      />
    </template>

    <div class="card mt-2.5">
      <InfoRow label="Качество">
        <StarRating v-if="!editingPlace" :value="place.ratingQuality" />
        <select v-else v-model.number="place.ratingQuality" class="bg-surface2 rounded-lg px-2 py-1 text-body text-text outline-none">
          <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
        </select>
      </InfoRow>
      <InfoRow label="Скорость">
        <StarRating v-if="!editingPlace" :value="place.ratingSpeed" />
        <select v-else v-model.number="place.ratingSpeed" class="bg-surface2 rounded-lg px-2 py-1 text-body text-text outline-none">
          <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
        </select>
      </InfoRow>
      <InfoRow label="Цена">
        <StarRating v-if="!editingPlace" :value="place.ratingPrice" />
        <select v-else v-model.number="place.ratingPrice" class="bg-surface2 rounded-lg px-2 py-1 text-body text-text outline-none">
          <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
        </select>
      </InfoRow>
      <div class="px-3 py-1 text-label text-muted">из {{ place.reviews.length }} отзывов</div>
    </div>

    <button
      v-if="editingPlace"
      class="w-full mt-2.5 py-2.5 rounded-xl bg-rose-500/15 text-rose-400 font-bold text-title active:scale-95 transition-transform"
      @click="deletePlace"
    >
      Удалить место
    </button>

    <Section title="Отзывы" :count="place.reviews.length">
      <div class="card divide-y divide-white/5">
        <div v-for="r in place.reviews" :key="r.id" class="px-3 py-2">
          <div class="flex items-center justify-between">
            <span class="text-body font-semibold text-text">{{ r.author }}</span>
            <StarRating :value="Math.round((r.quality + r.speed + r.price) / 3)" />
          </div>
          <p class="text-body text-muted mt-0.5 leading-snug">{{ r.text }}</p>
          <div v-if="r.author === 'Вы'" class="flex gap-3 mt-1">
            <button class="text-label font-semibold text-accent2" @click="editReview(r)">Изменить</button>
            <button class="text-label font-semibold text-rose-400" @click="deleteReview(r.id)">Удалить</button>
          </div>
        </div>
      </div>
    </Section>

    <div v-if="showForm" class="card mt-2.5 px-3 py-2.5">
      <div class="flex items-center justify-between mb-1.5">
        <span class="text-meta text-muted">Качество</span>
        <select v-model.number="newReview.quality" class="bg-surface2 rounded-lg px-2 py-1 text-body text-text outline-none">
          <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
        </select>
      </div>
      <div class="flex items-center justify-between mb-1.5">
        <span class="text-meta text-muted">Скорость</span>
        <select v-model.number="newReview.speed" class="bg-surface2 rounded-lg px-2 py-1 text-body text-text outline-none">
          <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
        </select>
      </div>
      <div class="flex items-center justify-between mb-1.5">
        <span class="text-meta text-muted">Цена</span>
        <select v-model.number="newReview.price" class="bg-surface2 rounded-lg px-2 py-1 text-body text-text outline-none">
          <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
        </select>
      </div>
      <textarea
        v-model="newReview.text"
        placeholder="Текст отзыва"
        rows="3"
        class="w-full bg-surface2 rounded-lg px-2 py-1.5 text-body text-text outline-none resize-none"
      />
      <div class="flex gap-2 mt-2">
        <div class="flex-1"><PrimaryButton :label="editingReviewId !== null ? 'Сохранить' : 'Опубликовать'" @click="submitReview" /></div>
        <PrimaryButton label="Отмена" size="sm" @click="closeForm" />
      </div>
    </div>
    <div v-else class="mt-3">
      <PrimaryButton label="Оставить отзыв" @click="showForm = true" />
    </div>

    <BottomNav />
  </div>
</template>
