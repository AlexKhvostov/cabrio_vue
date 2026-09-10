<script setup lang="ts">
withDefaults(defineProps<{ text: string; tone?: 'accent' | 'muted' | 'accent2' | 'success' | 'warning' | 'danger' }>(), {
  tone: 'accent',
})

const toneClasses: Record<string, string> = {
  accent: 'bg-accent/15 text-accent',
  muted: 'bg-white/10 text-muted',
  accent2: 'bg-accent2/15 text-accent2',
  success: 'bg-emerald-500/15 text-emerald-400',
  warning: 'bg-amber-500/15 text-amber-400',
  danger: 'bg-rose-500/15 text-rose-400',
}
</script>

<template>
  <span class="inline-flex items-center px-1.5 py-[1px] rounded-full text-micro font-bold shrink-0" :class="toneClasses[tone]">
    {{ text }}
  </span>
</template>
