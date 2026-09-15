import { defineStore } from 'pinia'
import { Centrifuge } from 'centrifuge'
import { api } from '../api.js'

let centrifuge = null

function realtimeWsUrl() {
  const proto = location.protocol === 'https:' ? 'wss:' : 'ws:'
  return `${proto}//${location.host}/connection/websocket`
}

export const useDeskStore = defineStore('desk', {
  state: () => ({
    user: null,
    busy: false,
    message: '',
    catalog: [],
    addingId: null,
    alerts: [],
    notifications: [],
    chartCoin: null,
    chartPoints: [],
    dashboard: {
      watchlist: [],
      unusual_volume: [],
    },
    loginForm: { username: 'demo', password: 'demo1234' },
    conv: { from: 'bitcoin', amount: '1' },
    alertForm: { coingecko_id: 'bitcoin', threshold_percent: '3' },
  }),

  getters: {
    isTracked: (state) => (id) =>
      state.dashboard.watchlist.some((r) => r.coingecko_id === id),
  },

  actions: {
    async login() {
      const data = await api('/api/auth/login', {
        method: 'POST',
        body: JSON.stringify(this.loginForm),
      })
      this.message = data.ok ? 'Logged in' : (data.error || 'Login failed')
      if (data.ok) {
        this.user = data.user
        await this.refresh()
      }
    },

    async logout() {
      this.disconnectRealtime()
      await api('/api/auth/logout', { method: 'POST' })
      this.user = null
    },

    async loadCatalog() {
      const data = await api('/api/catalog')
      if (data.ok) this.catalog = data.coins
      else this.message = data.error || 'Catalog failed'
    },

    async refresh() {
      const me = await api('/api/me')
      if (!me.ok) {
        this.user = null
        this.disconnectRealtime()
        return
      }
      this.user = me.user
      if (!this.catalog.length) await this.loadCatalog()
      const dash = await api('/api/dashboard')
      if (dash.ok) {
        Object.assign(this.dashboard, dash.data)
        if (this.dashboard.watchlist.length) {
          if (!this.dashboard.watchlist.find((r) => r.coingecko_id === this.conv.from)) {
            this.conv.from = this.dashboard.watchlist[0].coingecko_id
          }
          this.alertForm.coingecko_id = this.conv.from
        }
      }
      const a = await api('/api/alerts')
      if (a.ok) this.alerts = a.rules
      const n = await api('/api/notifications')
      if (n.ok) this.notifications = n.notifications
      this.connectRealtime()
    },

    async sync() {
      this.busy = true
      this.message = ''
      try {
        const data = await api('/api/sync', { method: 'POST' })
        this.message = data.ok
          ? `Курси оновлено (${data.sync.snapshots} snapshots, ${data.sync.captured_at})`
          : (data.error || 'Sync failed')
        await this.refresh()
        if (this.chartCoin) await this.showChart(this.chartCoin)
      } finally {
        this.busy = false
      }
    },

    async addCoin(c) {
      if (!c?.id || this.isTracked(c.id) || this.addingId) return
      this.addingId = c.id
      const priceFromCatalog = c.price_usd != null ? Number(c.price_usd) : null
      this.dashboard.watchlist.push({
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
          this.dashboard.watchlist = this.dashboard.watchlist.filter((r) => r.coingecko_id !== c.id)
          this.message = data.error || 'Add failed'
          return
        }
        if (data.sync?.error) {
          this.message = `Додано: ${c.name}` + (priceFromCatalog == null ? ' (курс пізніше — Sync now)' : '')
        } else {
          this.message = `Додано: ${c.name}`
        }
        const dash = await api('/api/dashboard')
        if (dash.ok) {
          const server = dash.data
          const merged = (server.watchlist || []).map((row) => {
            if (row.coingecko_id === c.id && row.price_usd == null && priceFromCatalog != null) {
              return { ...row, price_usd: priceFromCatalog }
            }
            return row
          })
          Object.assign(this.dashboard, { ...server, watchlist: merged })
        }
      } catch (e) {
        this.dashboard.watchlist = this.dashboard.watchlist.filter((r) => r.coingecko_id !== c.id)
        this.message = 'Add failed'
      } finally {
        this.addingId = null
      }
    },

    async removeWatch(id) {
      await api('/api/watchlist/' + encodeURIComponent(id), { method: 'DELETE' })
      if (this.chartCoin?.coingecko_id === id) {
        this.chartCoin = null
        this.chartPoints = []
      }
      await this.refresh()
    },

    async showChart(row) {
      this.chartCoin = row
      const data = await api('/api/history/' + encodeURIComponent(row.coingecko_id))
      this.chartPoints = data.ok ? data.points : []
    },

    closeChart() {
      this.chartCoin = null
      this.chartPoints = []
    },

    async addAlert() {
      await api('/api/alerts', {
        method: 'POST',
        body: JSON.stringify(this.alertForm),
      })
      await this.refresh()
    },

    async removeAlert(id) {
      await api('/api/alerts/' + id, { method: 'DELETE' })
      await this.refresh()
    },

    async connectRealtime() {
      if (!this.user || centrifuge) return
      try {
        const boot = await api('/api/realtime/token')
        if (!boot.ok || !boot.token || !boot.channel) return
        const client = new Centrifuge(realtimeWsUrl(), {
          token: boot.token,
          getToken: async () => {
            const data = await api('/api/realtime/token')
            if (!data.ok || !data.token) throw new Error(data.error || 'No realtime token')
            return data.token
          },
        })
        client.newSubscription(boot.channel).on('publication', (ctx) => {
          this.onRealtimePush(ctx.data)
        }).subscribe()
        client.connect()
        centrifuge = client
      } catch (e) {
        centrifuge = null
      }
    },

    disconnectRealtime() {
      centrifuge?.disconnect()
      centrifuge = null
    },

    async onRealtimePush(data) {
      if (data?.type !== 'synced' || this.busy) return
      this.message = `Курси оновлено (${data.snapshots} snapshots, ${data.captured_at})`
      await this.refresh()
      if (this.chartCoin) await this.showChart(this.chartCoin)
    },
  },
})
