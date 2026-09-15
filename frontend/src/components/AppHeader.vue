<template>
  <header class="topbar">
    <div class="topbar-inner">
      <a class="brand" href="/" @click.prevent>
        <span class="brand-mark" aria-hidden="true"></span>
        <span class="brand-text">
          <span class="brand-name">Crypto Watchdesk</span>
          <span class="brand-tag">CoinGecko snapshots · watchlist · alerts</span>
        </span>
      </a>

      <div class="topbar-actions" v-if="!user">
        <form class="login-inline" @submit.prevent="emit('login')">
          <input v-model="loginForm.username" placeholder="username" autocomplete="username" />
          <input v-model="loginForm.password" type="password" placeholder="password" autocomplete="current-password" />
          <button type="submit">Login</button>
        </form>
        <span class="hint">demo / demo1234</span>
      </div>

      <div class="topbar-actions" v-else>
        <span class="user-chip">{{ user.username }}</span>
        <button :disabled="busy" @click="emit('sync')">{{ busy ? 'Оновлення…' : 'Обновить курсы' }}</button>
        <button type="button" class="secondary" @click="emit('logout')">Logout</button>
      </div>
    </div>
    <p v-if="message" class="flash" :class="{ err: /fail|error|unauthorized/i.test(message) }">{{ message }}</p>
  </header>
</template>

<script setup>
defineProps({
  user: { type: Object, default: null },
  busy: { type: Boolean, default: false },
  message: { type: String, default: '' },
  loginForm: { type: Object, required: true },
})

const emit = defineEmits(['login', 'logout', 'sync'])

</script>
