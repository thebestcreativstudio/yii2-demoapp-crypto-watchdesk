<template>
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
              @click.stop.prevent="emit('add-coin', c)"
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
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'

const props = defineProps({
  catalog: { type: Array, default: () => [] },
  dashboard: { type: Object, required: true },
  addingId: { type: String, default: null },
})

const emit = defineEmits(['add-coin'])

const ddOpen = ref(false)
const ddFilter = ref('')
const ddRoot = ref(null)

const filteredCatalog = computed(() => {
  const q = ddFilter.value.trim().toLowerCase()
  if (!q) return props.catalog
  return props.catalog.filter(
    (c) =>
      c.name.toLowerCase().includes(q) ||
      c.symbol.toLowerCase().includes(q) ||
      c.id.toLowerCase().includes(q)
  )
})

function isTracked(id) {
  return props.dashboard.watchlist.some((r) => r.coingecko_id === id)
}

function onDocClick(e) {
  if (!ddOpen.value || !ddRoot.value) return
  if (!ddRoot.value.contains(e.target)) ddOpen.value = false
}

onMounted(() => document.addEventListener('click', onDocClick))
onUnmounted(() => document.removeEventListener('click', onDocClick))
</script>
