// Единая точка входа в UI Kit: любой экран импортирует компоненты отсюда,
// а не собирает разметку заново. Меняем компонент один раз — меняется везде.
export { default as PageHeader } from './PageHeader.vue'
export { default as Badge } from './Badge.vue'
export { default as PrimaryButton } from './PrimaryButton.vue'
export { default as InfoRow } from './InfoRow.vue'
export { default as EntityLinkRow } from './EntityLinkRow.vue'
export { default as CarMiniCard } from './CarMiniCard.vue'
export { default as Section } from './Section.vue'
export { default as Avatar } from './Avatar.vue'
export { default as BottomNav } from './BottomNav.vue'
export { default as SearchFilterBar } from './SearchFilterBar.vue'
export { default as StatTile } from './StatTile.vue'
export { default as StarRating } from './StarRating.vue'
export { default as EditableField } from './EditableField.vue'
export { default as CarPhotoStack } from './CarPhotoStack.vue'

export function stars(n: number): string {
  return '⭐'.repeat(n) + '☆'.repeat(5 - n)
}

export const ICONS = {
  event:
    '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
  review:
    '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>',
}
