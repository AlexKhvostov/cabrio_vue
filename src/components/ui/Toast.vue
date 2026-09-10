<script setup lang="ts">
withDefaults(defineProps<{ message: string; tone?: 'default' | 'error' }>(), { tone: 'default' })
</script>

<template>
  <transition name="toast-fade">
    <div
      v-if="message"
      class="fixed bottom-24 left-1/2 -translate-x-1/2 z-50 px-3 py-1.5 rounded-full text-meta shadow max-w-[85vw] text-center"
      :class="tone === 'error' ? 'bg-rose-500/90 text-white' : 'bg-surface text-text'"
    >
      {{ message }}
    </div>
  </transition>
</template>

<style scoped>
.toast-fade-enter-active,
.toast-fade-leave-active {
  transition: opacity 0.2s;
}
.toast-fade-enter-from,
.toast-fade-leave-to {
  opacity: 0;
}
</style>
