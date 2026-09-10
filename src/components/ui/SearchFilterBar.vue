<script setup lang="ts">
withDefaults(defineProps<{ placeholder?: string; modelValue: string }>(), {
  placeholder: 'Поиск...',
})
defineEmits<{ 'update:modelValue': [string] }>()
</script>

<template>
  <div class="flex items-center gap-2 mb-2">
    <div class="flex-1 flex items-center gap-1.5 bg-surface rounded-xl px-2.5 py-1.5">
      <svg class="w-3.5 h-3.5 text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
      </svg>
      <input
        :value="modelValue"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        :placeholder="placeholder"
        class="bg-transparent outline-none text-body text-text placeholder:text-muted w-full"
      />
    </div>
    <slot name="filter" />
  </div>
</template>
