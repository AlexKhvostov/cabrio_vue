import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'home',
      component: () => import('@/views/HomeView.vue'),
    },
    {
      path: '/participants',
      name: 'participants',
      component: () => import('@/views/ParticipantsListView.vue'),
    },
    {
      path: '/participants/:id',
      name: 'participant',
      component: () => import('@/views/ParticipantCardView.vue'),
    },
    {
      path: '/cars',
      name: 'cars',
      component: () => import('@/views/CarsListView.vue'),
    },
  ],
})

export default router
