import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { firstAllowedPath, type AppRouteMeta } from './menu'
import LoginView from '../views/LoginView.vue'
import UnauthorizedView from '../views/UnauthorizedView.vue'
import NotFoundView from '../views/NotFoundView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { label: 'Login' } satisfies AppRouteMeta,
    },
    {
      path: '/unauthorized',
      name: 'unauthorized',
      component: UnauthorizedView,
      meta: { label: 'Unauthorized' } satisfies AppRouteMeta,
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: NotFoundView,
      meta: { label: 'Not Found' } satisfies AppRouteMeta,
    },
  ],
})

router.beforeEach(async (to) => {
  const authStore = useAuthStore()

  if (to.path === '/login' && authStore.isAuthenticated && authStore.user) {
    return firstAllowedPath(authStore.user.roles)
  }

  return true
})

export default router
