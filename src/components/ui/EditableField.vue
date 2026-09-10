<script setup lang="ts">
withDefaults(
  defineProps<{
    label: string
    modelValue: string
    editing?: boolean
    type?: string
    placeholder?: string
  }>(),
  { editing: false, type: 'text', placeholder: '' }
)
defineEmits<{ 'update:modelValue': [string] }>()
</script>

<template>
  <div class="flex items-center justify-between px-3 py-1.5 border-b border-white/5 last:border-0 gap-3">
    <div class="text-meta text-muted shrink-0">{{ label }}</div>
    <input
      v-if="editing"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      class="text-body font-medium text-text bg-surface2 rounded-lg px-2 py-1 outline-none text-right flex-1 min-w-0"
    />
    <div v-else class="text-body font-medium text-text truncate">{{ modelValue || '—' }}</div>
  </div>
</template>
