<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { toActionableMessage } from '../api/http'
import { firstAllowedPath } from '../router/menu'
import { useAuthStore } from '../stores/auth'

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()

const username = ref('')
const password = ref('')
const error = ref('')

async function submitLogin() {
  error.value = ''

  try {
    await authStore.signIn(username.value.trim(), password.value)
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : ''
    await router.replace(
      redirect !== '' && redirect !== '/unauthorized'
        ? redirect
        : firstAllowedPath(authStore.user?.roles ?? []),
    )
  } catch (exception) {
    error.value = toActionableMessage(exception)
  }
}
</script>

<template>
  <main class="auth-page">
    <section class="auth-card">
      <h1>VetOps Sign In</h1>
      <p>Use your local LAN account credentials to access role-specific workspaces.</p>

      <p v-if="error" class="danger-text">{{ error }}</p>

      <form class="auth-form" @submit.prevent="submitLogin">
        <label>
          Username
          <input v-model="username" type="text" autocomplete="username" />
        </label>

        <label>
          Password
          <input v-model="password" type="password" autocomplete="current-password" />
        </label>

        <button :disabled="authStore.loading" type="submit">
          {{ authStore.loading ? 'Signing In...' : 'Sign In' }}
        </button>
      </form>
    </section>
  </main>
</template>
