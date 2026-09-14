<template>
  <div class="site">
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
          <form class="login-inline" @submit.prevent="login">
            <input v-model="loginForm.username" placeholder="username" autocomplete="username" />
            <input v-model="loginForm.password" type="password" placeholder="password" autocomplete="current-password" />
            <button type="submit">Login</button>
          </form>
          <span class="hint">demo / demo1234</span>
        </div>

        <div class="topbar-actions" v-else>
          <span class="user-chip">{{ user.username }}</span>
          <button :disabled="busy" @click="sync">{{ busy ? 'Оновлення…' : 'Обновить курсы' }}</button>
          <button type="button" class="secondary" @click="logout">Logout</button>
        </div>
      </div>
      <p v-if="message" class="flash" :class="{ err: /fail|error|unauthorized/i.test(message) }">{{ message }}</p>
    </header>

    <main class="main">
      <template v-if="user">
        <section class="card">
          <h2>Додати для відстеження</h2>
          <p class="muted">Клік по монеті — одразу в таблицю нижче.</p>
          <div class="row" style="align-items:flex-start">
            <div class="dd" ref="ddRoot">
              <button type="button" class="dd-trigger" @click="ddOpen = !ddOpen">
                <span>Оберіть монету…</span>
                <span class="dd-caret">▾</span>
              </button>
              <div class="dd-panel" v-show="ddOpen">
                <input
                  v-model="ddFilter"
                  class="dd-filter"
                  placeholder="Пошук…"
                  @click.stop
                />
                <ul class="dd-list" @click.stop>
                  <li
                    v-for="c in filteredCatalog"
                    :key="c.id"
                    :class="{ disabled: isTracked(c.id) || addingId === c.id, checked: isTracked(c.id) }"
                    @click.stop.prevent="addCoin(c)"
                  >
                    <span class="dd-check">{{ isTracked(c.id) ? '✓' : '☐' }}</span>
                    {{ c.name }} <span class="muted">({{ c.symbol }})</span>
                    <span class="muted" v-if="isTracked(c.id)"> · вже є</span>
                    <span class="muted" v-else-if="addingId === c.id"> · додаємо…</span>
                  </li>
                  <li v-if="!filteredCatalog.length" class="muted">Нічого не знайдено</li>
                </ul>
              </div>
            </div>
          </div>
        </section>

        <section class="card">
          <h2>Відстеження · курс</h2>
          <p class="muted">
            Snapshot з CoinGecko API (worker ~15 хв або «Обновить курсы»). Free API часто округляє BTC до цілих $.
          </p>
          <table>
            <thead>
              <tr>
                <th>Монета</th>
                <th>Курс USD</th>
                <th>Знімок</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in dashboard.watchlist" :key="r.coingecko_id">
                <td>
                  {{ r.name || r.coingecko_id }}
                  <span class="muted">{{ r.symbol }}</span>
                  <span class="badge" v-if="r.unusual_volume">unusual vol</span>
                </td>
                <td class="price">{{ fmtMoney(r.price_usd) }}</td>
                <td class="muted mono">{{ r.captured_at || '—' }}</td>
                <td class="row">
                  <button class="secondary" @click="showChart(r)">Графік</button>
                  <button class="danger" @click="removeWatch(r.coingecko_id)">×</button>
                </td>
              </tr>
            </tbody>
          </table>

          <div class="chart-box" v-if="chartCoin">
            <div class="row" style="justify-content:space-between;margin-bottom:0.5rem">
              <strong>Графік: {{ chartCoin.name || chartCoin.coingecko_id }}</strong>
              <button class="secondary" @click="chartCoin = null; chartPoints = []">Закрити</button>
            </div>
            <p class="muted" v-if="chartPoints.length < 2">Мало точок — оновлюй курси з часом.</p>
            <PriceLineChart
              v-else
              :key="chartCoin.coingecko_id"
              :points="chartPoints"
              :label="chartCoin.symbol || 'USD'"
            />
            <div class="row muted" style="justify-content:space-between" v-if="chartPoints.length">
              <span>{{ chartPoints[0].t }}</span>
              <span>{{ fmtMoney(chartPoints[chartPoints.length - 1].price) }}</span>
              <span>{{ chartPoints[chartPoints.length - 1].t }}</span>
            </div>
          </div>
        </section>

        <div class="grid2">
          <section class="card">
            <h2>Converter</h2>
            <p class="muted">Монета + кількість → курси на інші з watchlist.</p>
            <div class="row" style="margin-bottom:0.85rem">
              <select v-model="conv.from">
                <option v-for="r in dashboard.watchlist" :key="'f-'+r.coingecko_id" :value="r.coingecko_id">
                  {{ r.name || r.coingecko_id }} ({{ r.symbol }})
                </option>
              </select>
              <input v-model="conv.amount" type="number" min="0" step="any" style="width:9rem" placeholder="amount" />
            </div>
            <table v-if="convertRows.length">
              <thead>
                <tr>
                  <th>To</th>
                  <th>You get</th>
                  <th>Rate (1 {{ fromSymbol }} →)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in convertRows" :key="row.coingecko_id">
                  <td>{{ row.name }} <span class="muted">{{ row.symbol }}</span></td>
                  <td><strong>{{ fmtAmount(row.amount_to) }}</strong></td>
                  <td class="muted">{{ fmtAmount(row.rate) }} {{ row.symbol }}</td>
                </tr>
              </tbody>
            </table>
            <p class="muted" v-else>Add ≥2 coins and Sync.</p>
          </section>

          <section class="card">
            <h2>Alerts & notifications</h2>
            <div class="row" style="margin-bottom:0.75rem">
              <select v-model="alertForm.coingecko_id">
                <option v-for="r in dashboard.watchlist" :key="'a-'+r.coingecko_id" :value="r.coingecko_id">
                  {{ r.name || r.coingecko_id }}
                </option>
              </select>
              <input v-model="alertForm.threshold_percent" placeholder="Δ% e.g. 5" style="width:6rem" />
              <button @click="addAlert">Add rule</button>
            </div>
            <ul class="list">
              <li v-for="a in alerts" :key="a.id" class="row">
                <span>{{ a.coingecko_id }} ≥ {{ a.threshold_percent }}%</span>
                <button class="danger" @click="removeAlert(a.id)">×</button>
              </li>
            </ul>
            <h2 style="margin-top:1rem">Inbox</h2>
            <ul class="list">
              <li v-for="n in notifications" :key="n.id">
                <span class="muted">{{ n.created_at }}</span><br />
                {{ n.message }}
              </li>
              <li v-if="!notifications.length" class="muted">No alerts fired yet — sync over time.</li>
            </ul>
          </section>
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
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import PriceLineChart from './PriceLineChart.vue'

const user = ref(null)
const busy = ref(false)
const message = ref('')
const catalog = ref([])
const addingId = ref(null)
const ddOpen = ref(false)
const ddFilter = ref('')
const ddRoot = ref(null)
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

const fromCoin = computed(() =>
  dashboard.watchlist.find((r) => r.coingecko_id === conv.from) || null
)
const fromSymbol = computed(() => fromCoin.value?.symbol || conv.from)

const filteredCatalog = computed(() => {
  const q = ddFilter.value.trim().toLowerCase()
  if (!q) return catalog.value
  return catalog.value.filter(
    (c) =>
      c.name.toLowerCase().includes(q) ||
      c.symbol.toLowerCase().includes(q) ||
      c.id.toLowerCase().includes(q)
  )
})

function closeDd() {
  ddOpen.value = false
}

function onDocClick(e) {
  if (!ddOpen.value || !ddRoot.value) return
  if (!ddRoot.value.contains(e.target)) closeDd()
}

const convertRows = computed(() => {
  const from = fromCoin.value
  const amount = Number(conv.amount)
  if (!from || from.price_usd == null || !(amount >= 0)) return []
  const priceFrom = Number(from.price_usd)
  if (!(priceFrom > 0)) return []
  return dashboard.watchlist
    .filter((r) => r.coingecko_id !== conv.from && r.price_usd != null && Number(r.price_usd) > 0)
    .map((r) => {
      const priceTo = Number(r.price_usd)
      const rate = priceFrom / priceTo
      return {
        coingecko_id: r.coingecko_id,
        name: r.name || r.coingecko_id,
        symbol: r.symbol,
        rate,
        amount_to: amount * rate,
      }
    })
})

function isTracked(id) {
  return dashboard.watchlist.some((r) => r.coingecko_id === id)
}

async function api(path, opts = {}) {
  const res = await fetch(path, {
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', ...(opts.headers || {}) },
    ...opts,
  })
  return res.json()
}

function fmtMoney(v, digits = 2) {
  if (v === null || v === undefined || Number.isNaN(Number(v))) return '—'
  return '$' + Number(v).toLocaleString(undefined, { maximumFractionDigits: digits })
}
function fmtAmount(v) {
  if (v === null || v === undefined || Number.isNaN(Number(v))) return '—'
  const n = Number(v)
  if (n >= 1000) return n.toLocaleString(undefined, { maximumFractionDigits: 4 })
  if (n >= 1) return n.toLocaleString(undefined, { maximumFractionDigits: 6 })
  return n.toLocaleString(undefined, { maximumFractionDigits: 10 })
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
  document.addEventListener('click', onDocClick)
  refresh()
})
onUnmounted(() => document.removeEventListener('click', onDocClick))
</script>
