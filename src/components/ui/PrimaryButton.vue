<script setup lang="ts">
withDefaults(defineProps<{ label: string; size?: 'sm' | 'md' }>(), {
  size: 'md',
})
defineEmits<{ click: [] }>()
</script>

<template>
  <button
    @click="$emit('click')"
    class="shrink-0 rounded-xl bg-accent text-bg font-bold active:scale-95 transition-transform"
    :class="size === 'sm' ? 'px-3 py-1.5 text-meta' : 'w-full py-2.5 text-title'"
  >
    {{ label }}
  </button>
</template>
