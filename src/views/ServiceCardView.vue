<script setup lang="ts">
import { ref, reactive } from 'vue'
import { PageHeader, Badge, InfoRow, PrimaryButton, Section, StarRating, ratingTone, BottomNav } from '@/components/ui'

const showForm = ref(false)
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

function submitReview() {
  if (!newReview.text.trim()) return
  place.reviews.unshift({ id: nextId++, author: 'Вы', quality: newReview.quality, speed: newReview.speed, price: newReview.price, text: newReview.text })
  newReview.text = ''
  newReview.quality = 5
  newReview.speed = 5
  newReview.price = 5
  showForm.value = false
}
</script>

<template>
  <div class="page-shell pb-16">
    <PageHeader />

    <figure class="relative rounded-2xl overflow-hidden mb-2.5">
      <img :src="place.photo" class="w-full h-40 object-cover" />
      <Badge :text="`${place.avgRating} / 5`" :tone="ratingTone(place.avgRating)" class="absolute top-2 right-2" />
    </figure>

    <h1 class="text-name font-bold text-text px-1">{{ place.name }}</h1>
    <p class="text-meta text-muted px-1 mt-0.5">{{ place.city }}</p>
    <div class="flex flex-wrap gap-1 px-1 mt-1">
      <span v-for="t in place.tags" :key="t" class="text-label text-accent2">#{{ t }}</span>
    </div>
    <p class="text-body text-muted mt-1.5 px-1 leading-snug">{{ place.description }}</p>

    <div class="card mt-2.5">
      <InfoRow label="Качество">
        <StarRating :value="place.ratingQuality" />
      </InfoRow>
      <InfoRow label="Скорость">
        <StarRating :value="place.ratingSpeed" />
      </InfoRow>
      <InfoRow label="Цена">
        <StarRating :value="place.ratingPrice" />
      </InfoRow>
      <div class="px-3 py-1 text-label text-muted">из {{ place.reviews.length }} отзывов</div>
    </div>

    <Section title="Отзывы" :count="place.reviews.length">
      <div class="card divide-y divide-white/5">
        <div v-for="r in place.reviews" :key="r.id" class="px-3 py-2">
          <div class="flex items-center justify-between">
            <span class="text-body font-semibold text-text">{{ r.author }}</span>
            <StarRating :value="Math.round((r.quality + r.speed + r.price) / 3)" />
          </div>
          <p class="text-body text-muted mt-0.5 leading-snug">{{ r.text }}</p>
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
        <div class="flex-1"><PrimaryButton label="Опубликовать" @click="submitReview" /></div>
        <PrimaryButton label="Отмена" size="sm" @click="showForm = false" />
      </div>
    </div>
    <div v-else class="mt-3">
      <PrimaryButton label="Оставить отзыв" @click="showForm = true" />
    </div>

    <BottomNav />
  </div>
</template>
