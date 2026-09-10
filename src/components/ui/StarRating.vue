<script setup lang="ts">
import { stars } from '@/components/ui'

defineProps<{ value: number }>()
</script>

<template>
  <span class="text-meta">{{ stars(value) }}</span>
</template>
