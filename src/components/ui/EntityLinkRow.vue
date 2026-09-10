<script setup lang="ts">
defineProps<{ icon: string; title: string; meta: string; extra?: string | number }>()
defineEmits<{ click: [] }>()
</script>

<template>
  <button
    @click="$emit('click')"
    class="w-full flex items-center gap-2 px-3 py-1.5 border-b border-white/5 last:border-0 hover:bg-white/[0.03] transition-colors text-left"
  >
    <div class="w-5 h-5 flex items-center justify-center text-accent2 shrink-0" v-html="icon"></div>
    <div class="min-w-0 flex-1">
      <span class="text-body font-medium text-text">{{ title }}</span>
      <span class="text-meta text-muted"> · {{ meta }}</span>
    </div>
    <div v-if="extra" class="text-meta font-semibold text-accent shrink-0">{{ extra }}</div>
    <svg class="w-3 h-3 text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6" />
    </svg>
  </button>
</template>
