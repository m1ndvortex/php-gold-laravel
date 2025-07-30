import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createI18n } from 'vue-i18n';
import router from './router';
import App from './App.vue';

// Import CSS
import '../css/app.css';

// Import translations
import en from './locales/en.json';
import fa from './locales/fa.json';

// Create i18n instance
const i18n = createI18n({
  legacy: true, // Use legacy mode to avoid CSP issues
  locale: 'fa', // Default to Persian
  fallbackLocale: 'en',
  messages: {
    en,
    fa
  }
});

// Create Pinia store
const pinia = createPinia();

// Create Vue app
const app = createApp(App);

// Use plugins
app.use(pinia);
app.use(router);
app.use(i18n);

// Mount app
app.mount('#app');