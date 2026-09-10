import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import './style.css'
import { initTelegram } from './services/telegram'

initTelegram()
createApp(App).use(router).mount('#app')
