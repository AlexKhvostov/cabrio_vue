<script setup lang="ts">
withDefaults(defineProps<{ src: string; size?: number }>(), {
  size: 48,
})
</script>

<template>
  <img
    :src="src"
    :style="{ width: size + 'px', height: size + 'px' }"
    class="rounded-full object-cover ring-1 ring-accent/40 shrink-0"
  />
</template>
