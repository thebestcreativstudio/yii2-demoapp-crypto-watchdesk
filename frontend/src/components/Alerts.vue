<template>
  <section class="card">
    <h2>Alerts & notifications</h2>
    <div class="row" style="margin-bottom:0.75rem">
      <select v-model="alertForm.coingecko_id">
        <option v-for="r in dashboard.watchlist" :key="'a-' + r.coingecko_id" :value="r.coingecko_id">
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
</template>

<script setup>
defineProps({
  alerts: { type: Array, default: () => [] },
  notifications: { type: Array, default: () => [] },
  alertForm: { type: Object, required: true },
  dashboard: { type: Object, required: true },
})

const emit = defineEmits(['add-alert', 'remove-alert'])

function addAlert() {
  emit('add-alert')
}

function removeAlert(id) {
  emit('remove-alert', id)
}
</script>
