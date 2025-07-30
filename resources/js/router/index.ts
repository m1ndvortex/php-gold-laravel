import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import Dashboard from '../views/Dashboard.vue'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'Dashboard',
    component: Dashboard,
    meta: {
      title: 'dashboard'
    }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// Navigation guard for setting page title
router.beforeEach((to) => {
  if (to.meta?.title) {
    document.title = `${to.meta.title} - Jewelry Platform`
  }
})

export default router