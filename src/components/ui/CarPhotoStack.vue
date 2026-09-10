<script setup lang="ts">
// Стопка фото автомобилей внахлёст — до 3 штук, если больше — счётчик "+N" на первом фото
defineProps<{ cars: { id: number; photo: string }[] }>()
</script>

<template>
  <div v-if="cars.length" class="flex items-center shrink-0" style="padding-right: 8px">
    <div
      v-for="(car, i) in cars.slice(0, 3)"
      :key="car.id"
      class="relative rounded-lg overflow-hidden ring-2 ring-surface w-8 h-8"
      :style="{ marginLeft: i === 0 ? 0 : '-10px', zIndex: 3 - i }"
    >
      <img :src="car.photo" class="w-full h-full object-cover" />
      <span
        v-if="i === 0 && cars.length > 3"
        class="absolute inset-0 bg-black/60 flex items-center justify-center text-micro font-bold text-text"
      >
        +{{ cars.length - 3 }}
      </span>
    </div>
  </div>
</template>
