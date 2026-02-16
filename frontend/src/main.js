import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import axios from 'axios' // TAMBAHKAN INI
import './style.css'

// Konfigurasi Dasar Axios
axios.defaults.baseURL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';
axios.defaults.withCredentials = true; // Penting untuk Sanctum

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.mount('#app')