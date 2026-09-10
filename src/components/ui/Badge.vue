<script setup lang="ts">
withDefaults(defineProps<{ text: string; tone?: 'accent' | 'muted' }>(), {
  tone: 'accent',
})
</script>

<template>
  <span
    class="inline-flex items-center px-1.5 py-[1px] rounded-full text-micro font-bold shrink-0"
    :class="tone === 'accent' ? 'bg-accent/15 text-accent' : 'bg-white/10 text-muted'"
  >
    {{ text }}
  </span>
</template>
