<template>
  <div class="price-chart">
    <Line :data="chartData" :options="chartOptions" />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Filler,
  Tooltip,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip)
ChartJS.defaults.color = '#8b97ad'
ChartJS.defaults.font.family = '"IBM Plex Sans", "Segoe UI", system-ui, sans-serif'

const props = defineProps({
  points: { type: Array, default: () => [] },
  label: { type: String, default: 'USD' },
})

function fmtUsd(v) {
  const n = Number(v)
  if (!Number.isFinite(n)) return '—'
  const digits = Math.abs(n) >= 100 ? 2 : Math.abs(n) >= 1 ? 4 : 6
  return '$' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: digits })
}

function fmtTick(t) {
  const s = String(t || '')
  // "2026-09-14 15:30:00" → "14.09 15:30"
  const m = s.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/)
  if (!m) return s
  return `${m[3]}.${m[2]} ${m[4]}:${m[5]}`
}

function yBounds(prices) {
  const min = Math.min(...prices)
  const max = Math.max(...prices)
  const mid = (min + max) / 2
  const raw = max - min
  // Peg coins (USDT ~$1) otherwise fill the whole chart with noise
  const floor = Math.max(Math.abs(mid) * 0.02, 0.02)
  if (raw >= floor) {
    const pad = Math.max(raw * 0.1, floor * 0.05)
    return { min: min - pad, max: max + pad }
  }
  const half = floor / 2
  return { min: mid - half, max: mid + half }
}

const chartData = computed(() => {
  const pts = props.points
  return {
    labels: pts.map((p) => fmtTick(p.t)),
    datasets: [
      {
        label: props.label,
        data: pts.map((p) => Number(p.price)),
        borderColor: '#3d7eff',
        backgroundColor: 'rgba(61, 126, 255, 0.14)',
        borderWidth: 2,
        pointRadius: pts.length > 40 ? 0 : 3,
        pointHoverRadius: 5,
        pointBackgroundColor: '#3d7eff',
        tension: 0.25,
        fill: true,
      },
    ],
  }
})

const chartOptions = computed(() => {
  const prices = props.points.map((p) => Number(p.price)).filter(Number.isFinite)
  const y = prices.length ? yBounds(prices) : { min: 0, max: 1 }
  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#121a2b',
        borderColor: '#243049',
        borderWidth: 1,
        titleColor: '#e7ecf3',
        bodyColor: '#e7ecf3',
        callbacks: {
          label: (ctx) => fmtUsd(ctx.parsed.y),
        },
      },
    },
    scales: {
      x: {
        ticks: { color: '#8b97ad', maxRotation: 0, autoSkip: true, maxTicksLimit: 6 },
        grid: { color: 'rgba(36, 48, 73, 0.7)' },
      },
      y: {
        min: y.min,
        max: y.max,
        ticks: {
          color: '#8b97ad',
          callback: (v) => fmtUsd(v),
        },
        grid: { color: 'rgba(36, 48, 73, 0.7)' },
      },
    },
  }
})
</script>
