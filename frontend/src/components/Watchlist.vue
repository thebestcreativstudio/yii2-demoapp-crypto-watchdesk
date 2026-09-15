<template>
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
            <button class="secondary" @click="emit('show-chart', r)">Графік</button>
            <button class="danger" @click="emit('remove-watch', r.coingecko_id)">×</button>
          </td>
        </tr>
      </tbody>
    </table>

    <div class="chart-box" v-if="chartCoin">
      <div class="row" style="justify-content:space-between;margin-bottom:0.5rem">
        <strong>Графік: {{ chartCoin.name || chartCoin.coingecko_id }}</strong>
        <button class="secondary" @click="emit('close-chart')">Закрити</button>
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
</template>

<script setup>
import PriceLineChart from './PriceLineChart.vue'
import { fmtMoney } from '../format.js'

defineProps({
  dashboard: { type: Object, required: true },
  chartCoin: { type: Object, default: null },
  chartPoints: { type: Array, default: () => [] },
})

const emit = defineEmits(['show-chart', 'remove-watch', 'close-chart'])
</script>
