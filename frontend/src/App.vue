<template>
  <div class="site">
    <AppHeader
      :user="user"
      :busy="busy"
      :message="message"
      :login-form="loginForm"
      @login="desk.login"
      @logout="desk.logout"
      @sync="desk.sync"
    />

    <main class="main">
      <template v-if="user">
        <AddWatch
          :catalog="catalog"
          :dashboard="dashboard"
          :adding-id="addingId"
          @add-coin="desk.addCoin"
        />

        <Watchlist
          :dashboard="dashboard"
          :chart-coin="chartCoin"
          :chart-points="chartPoints"
          @show-chart="desk.showChart"
          @remove-watch="desk.removeWatch"
          @close-chart="desk.closeChart"
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
            @add-alert="desk.addAlert"
            @remove-alert="desk.removeAlert"
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
import { onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useDeskStore } from './stores/desk.js'
import AppHeader from './components/AppHeader.vue'
import AddWatch from './components/AddWatch.vue'
import Converter from './components/Converter.vue'
import Alerts from './components/Alerts.vue'
import Watchlist from './components/Watchlist.vue'

const desk = useDeskStore()
const {
  user,
  busy,
  message,
  catalog,
  addingId,
  alerts,
  notifications,
  chartCoin,
  chartPoints,
  dashboard,
  loginForm,
  conv,
  alertForm,
} = storeToRefs(desk)

onMounted(() => {
  desk.refresh()
})
</script>
