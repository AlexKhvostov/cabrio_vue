# CabrioRide — Vue 3

Новая версия Telegram Mini App клуба владельцев кабриолетов CabrioRide.
Переписывается на Vue 3 + TypeScript + Vite + Tailwind CSS (перенос со старого стека PHP/vanilla JS).

## Стек

- Vue 3 (`<script setup>`, Composition API)
- TypeScript
- Vite
- Vue Router
- Tailwind CSS

## Структура

- `src/components/ui/` — общий UI Kit (единые стили и компоненты для всего приложения: `PageHeader`, `Badge`, `PrimaryButton`, `InfoRow`, `EntityLinkRow`, `CarMiniCard`, `Section`, `Avatar`, `BottomNav`, `SearchFilterBar`, `StatTile`).
- `src/components/` — составные блоки экранов (например, `MemberListCard`, `CarListCard`).
- `src/views/` — экраны приложения.
- `src/router/` — маршруты.
- `tailwind.config.js` — единая тема приложения ("Открытая дорога": цвета, шрифты, шкала размеров текста).

## Запуск

```bash
npm install
npm run dev
```

## Сборка

```bash
npm run build
```

## Каталог экранов (UI Kit)

Статичный документ со всеми готовыми экранами приложения без необходимости запускать проект:
`../cabrio-vue3-preview/ui-kit-catalog.html`
