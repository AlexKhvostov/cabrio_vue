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
    {
      path: '/cars/:id',
      name: 'car',
      component: () => import('@/views/CarCardView.vue'),
    },
    {
      path: '/map',
      name: 'map',
      component: () => import('@/views/MapView.vue'),
    },
    {
      path: '/events',
      name: 'events',
      component: () => import('@/views/EventsListView.vue'),
    },
    {
      path: '/events/:id',
      name: 'event',
      component: () => import('@/views/EventCardView.vue'),
    },
    {
      path: '/services',
      name: 'services',
      component: () => import('@/views/ServicesListView.vue'),
    },
    {
      path: '/services/:id',
      name: 'service',
      component: () => import('@/views/ServiceCardView.vue'),
    },
    {
      path: '/me',
      name: 'profile',
      component: () => import('@/views/ProfileView.vue'),
    },
  ],
})

export default router
