<template>
  <section class="card">
    <h2>Converter</h2>
    <p class="muted">Монета + кількість → курси на інші з watchlist.</p>
    <div class="row" style="margin-bottom:0.85rem">
      <select v-model="conv.from">
        <option v-for="r in dashboard.watchlist" :key="'f-' + r.coingecko_id" :value="r.coingecko_id">
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
</template>

<script setup>
import { computed } from 'vue'
import { fmtAmount } from '../format.js'

const props = defineProps({
  conv: { type: Object, required: true },
  dashboard: { type: Object, required: true },
})

const fromCoin = computed(() =>
  props.dashboard.watchlist.find((r) => r.coingecko_id === props.conv.from) || null
)
const fromSymbol = computed(() => fromCoin.value?.symbol || props.conv.from)

const convertRows = computed(() => {
  const from = fromCoin.value
  const amount = Number(props.conv.amount)
  if (!from || from.price_usd == null || !(amount >= 0)) return []
  const priceFrom = Number(from.price_usd)
  if (!(priceFrom > 0)) return []
  return props.dashboard.watchlist
    .filter((r) => r.coingecko_id !== props.conv.from && r.price_usd != null && Number(r.price_usd) > 0)
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
</script>
