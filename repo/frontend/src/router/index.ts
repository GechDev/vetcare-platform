import { createRouter, createWebHistory } from 'vue-router'
import type { RouteLocationNormalized } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { firstAllowedPath, type AppRouteMeta } from './menu'
import LoginView from '../views/LoginView.vue'
import AppShell from '../views/AppShellView.vue'
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
      path: '/',
      component: AppShell,
      meta: { requiresAuth: true } satisfies AppRouteMeta,
      children: [
        {
          path: '',
          redirect: () => {
            const authStore = useAuthStore()
            return firstAllowedPath(authStore.user?.roles ?? [])
          },
        },
      ],
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

function routeAllowsAccess(route: RouteLocationNormalized, authStore: ReturnType<typeof useAuthStore>): boolean {
  const permissions = ((route.meta.permissions as string[] | undefined) ?? [])
  const roles = ((route.meta.roles as string[] | undefined) ?? [])

  const permissionAllowed = permissions.length === 0 || permissions.every((permission) => authStore.hasPermission(permission))
  const roleAllowed = roles.length === 0 || authStore.hasAnyRole(roles)

  return permissionAllowed && roleAllowed
}

router.beforeEach(async (to) => {
  const authStore = useAuthStore()

  if (authStore.user === null && authStore.token) {
    await authStore.hydrate()
  }

  if (to.path === '/login' && authStore.isAuthenticated && authStore.user) {
    return firstAllowedPath(authStore.user.roles)
  }

  const requiresAuth = Boolean(to.meta.requiresAuth)
  if (requiresAuth && !authStore.isAuthenticated) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }

  if (requiresAuth && !routeAllowsAccess(to, authStore)) {
    return '/unauthorized'
  }

  return true
})

export default router
