<template>
  <div class="site">
    <AppHeader
      :user="user"
      :busy="busy"
      :message="message"
      :login-form="loginForm"
      @login="login"
      @logout="logout"
      @sync="sync"
    />

    <main class="main">
      <template v-if="user">
        <AddWatch
          :catalog="catalog"
          :dashboard="dashboard"
          :adding-id="addingId"
          @add-coin="addCoin"
        />

        <Watchlist
          :dashboard="dashboard"
          :chart-coin="chartCoin"
          :chart-points="chartPoints"
          @show-chart="showChart"
          @remove-watch="removeWatch"
          @close-chart="closeChart"
        />

        <div class="grid2">
          <Converter
            :conv="conv"
            :dashboard="dashboard"
          />

          <Alerts
            :alerts="alerts"
            :notifications="notifications"
            :alert-form="alertForm"
            :dashboard="dashboard"
            @add-alert="addAlert"
            @remove-alert="removeAlert"
          />
        </div>
      </template>

      <section v-else class="card gate">
        <h2>Увійди, щоб відкрити desk</h2>
        <p class="muted">Демо: <code>demo</code> / <code>demo1234</code> — логін у шапці зверху.</p>
      </section>
    </main>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { api } from './api.js'
import AppHeader from './components/AppHeader.vue'
import AddWatch from './components/AddWatch.vue'
import Converter from './components/Converter.vue'
import Alerts from './components/Alerts.vue'
import Watchlist from './components/Watchlist.vue'

const user = ref(null)
const busy = ref(false)
const message = ref('')
const catalog = ref([])
const addingId = ref(null)
const alerts = ref([])
const notifications = ref([])
const chartCoin = ref(null)
const chartPoints = ref([])
const dashboard = reactive({
  watchlist: [],
  unusual_volume: [],
})

const loginForm = reactive({ username: 'demo', password: 'demo1234' })
const conv = reactive({ from: 'bitcoin', amount: '1' })
const alertForm = reactive({ coingecko_id: 'bitcoin', threshold_percent: '3' })

function isTracked(id) {
  return dashboard.watchlist.some((r) => r.coingecko_id === id)
}

async function login() {
  const data = await api('/api/auth/login', {
    method: 'POST',
    body: JSON.stringify(loginForm),
  })
  message.value = data.ok ? 'Logged in' : (data.error || 'Login failed')
  if (data.ok) {
    user.value = data.user
    await refresh()
  }
}

async function logout() {
  await api('/api/auth/logout', { method: 'POST' })
  user.value = null
}

async function loadCatalog() {
  const data = await api('/api/catalog')
  if (data.ok) catalog.value = data.coins
  else message.value = data.error || 'Catalog failed'
}

async function refresh() {
  const me = await api('/api/me')
  if (!me.ok) {
    user.value = null
    return
  }
  user.value = me.user
  // Catalog hits CoinGecko — load once, not on every refresh (rate limits)
  if (!catalog.value.length) await loadCatalog()
  const dash = await api('/api/dashboard')
  if (dash.ok) {
    Object.assign(dashboard, dash.data)
    if (dashboard.watchlist.length) {
      if (!dashboard.watchlist.find((r) => r.coingecko_id === conv.from)) {
        conv.from = dashboard.watchlist[0].coingecko_id
      }
      alertForm.coingecko_id = conv.from
    }
  }
  const a = await api('/api/alerts')
  if (a.ok) alerts.value = a.rules
  const n = await api('/api/notifications')
  if (n.ok) notifications.value = n.notifications
}

async function sync() {
  busy.value = true
  message.value = ''
  try {
    const data = await api('/api/sync', { method: 'POST' })
    message.value = data.ok
      ? `Курси оновлено (${data.sync.snapshots} snapshots, ${data.sync.captured_at})`
      : (data.error || 'Sync failed')
    await refresh()
    if (chartCoin.value) await showChart(chartCoin.value)
  } finally {
    busy.value = false
  }
}

async function addCoin(c) {
  if (!c?.id || isTracked(c.id) || addingId.value) return
  addingId.value = c.id
  const priceFromCatalog = c.price_usd != null ? Number(c.price_usd) : null
  // Optimistic: row + price from catalog (already loaded from CoinGecko)
  dashboard.watchlist.push({
    coingecko_id: c.id,
    symbol: c.symbol,
    name: c.name,
    price_usd: priceFromCatalog,
    unusual_volume: false,
  })
  try {
    const data = await api('/api/watchlist', {
      method: 'POST',
      body: JSON.stringify({
        coins: [{ id: c.id, symbol: c.symbol, name: c.name }],
      }),
    })
    if (!data.ok) {
      dashboard.watchlist = dashboard.watchlist.filter((r) => r.coingecko_id !== c.id)
      message.value = data.error || 'Add failed'
      return
    }
    if (data.sync?.error) {
      message.value = `Додано: ${c.name}` + (priceFromCatalog == null ? ' (курс пізніше — Sync now)' : '')
    } else {
      message.value = `Додано: ${c.name}`
    }
    const dash = await api('/api/dashboard')
    if (dash.ok) {
      // Keep catalog price if server still has null (sync failed)
      const server = dash.data
      const merged = (server.watchlist || []).map((row) => {
        if (row.coingecko_id === c.id && row.price_usd == null && priceFromCatalog != null) {
          return { ...row, price_usd: priceFromCatalog }
        }
        return row
      })
      Object.assign(dashboard, { ...server, watchlist: merged })
    }
  } catch (e) {
    dashboard.watchlist = dashboard.watchlist.filter((r) => r.coingecko_id !== c.id)
    message.value = 'Add failed'
  } finally {
    addingId.value = null
  }
}

async function removeWatch(id) {
  await api('/api/watchlist/' + encodeURIComponent(id), { method: 'DELETE' })
  if (chartCoin.value?.coingecko_id === id) {
    chartCoin.value = null
    chartPoints.value = []
  }
  await refresh()
}

async function showChart(row) {
  chartCoin.value = row
  const data = await api('/api/history/' + encodeURIComponent(row.coingecko_id))
  chartPoints.value = data.ok ? data.points : []
}

function closeChart() {
  chartCoin.value = null
  chartPoints.value = []
}

async function addAlert() {
  await api('/api/alerts', {
    method: 'POST',
    body: JSON.stringify(alertForm),
  })
  await refresh()
}

async function removeAlert(id) {
  await api('/api/alerts/' + id, { method: 'DELETE' })
  await refresh()
}

onMounted(() => {
  refresh()
})
</script>
