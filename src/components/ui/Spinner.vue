<script setup lang="ts">
withDefaults(defineProps<{ overlay?: boolean }>(), { overlay: true })
</script>

<template>
  <div v-if="overlay" class="fixed inset-0 z-50 flex items-center justify-center bg-bg/40">
    <div class="w-8 h-8 rounded-full border-2 border-white/20 border-t-accent animate-spin" />
  </div>
  <div v-else class="w-8 h-8 rounded-full border-2 border-white/20 border-t-accent animate-spin" />
</template>
