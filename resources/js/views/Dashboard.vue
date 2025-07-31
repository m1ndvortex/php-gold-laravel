<template>
  <div>
    <!-- Page Header -->
    <div class="mb-8 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">
          {{ t('dashboard') }}
        </h1>
        <p class="mt-2 text-sm text-gray-600">
          {{ t('dashboard_description') }}
        </p>
      </div>
      <div class="flex items-center space-x-4">
        <!-- Real-time Updates Toggle -->
        <div class="flex items-center space-x-2">
          <div
            class="w-2 h-2 rounded-full"
            :class="realTimeUpdates ? 'bg-green-500 animate-pulse' : 'bg-gray-400'"
          ></div>
          <button
            @click="realTimeUpdates = !realTimeUpdates"
            class="text-xs text-gray-600 hover:text-gray-800"
          >
            {{ realTimeUpdates ? t('realtime_on') : t('realtime_off') }}
          </button>
          <span v-if="lastUpdate" class="text-xs text-gray-500">
            {{ t('last_update') }}: {{ formatTime(lastUpdate) }}
          </span>
        </div>
        
        <select 
          v-model="selectedPeriod" 
          @change="loadDashboardData"
          class="form-select text-sm"
        >
          <option value="today">{{ t('today') }}</option>
          <option value="week">{{ t('this_week') }}</option>
          <option value="month">{{ t('this_month') }}</option>
          <option value="year">{{ t('this_year') }}</option>
        </select>
        <button 
          @click="refreshData"
          :disabled="loading"
          class="btn btn-secondary btn-sm"
        >
          <ArrowPathIcon class="h-4 w-4 mr-2" :class="{ 'animate-spin': loading }" />
          {{ t('refresh') }}
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading && !dashboardData" class="text-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mx-auto"></div>
      <p class="mt-4 text-gray-500">{{ t('loading_dashboard') }}</p>
    </div>

    <!-- Dashboard Content -->
    <div v-else-if="dashboardData">
      <!-- KPI Cards -->
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <KPIWidget
          :title="t('total_sales')"
          :value="dashboardData.kpis.sales.value"
          :change-percentage="dashboardData.kpis.sales.change_percentage"
          :trend="dashboardData.kpis.sales.trend"
          type="currency"
          :icon="CurrencyDollarIcon"
        />
        <KPIWidget
          :title="t('profit')"
          :value="dashboardData.kpis.profit.value"
          :change-percentage="dashboardData.kpis.profit.change_percentage"
          :trend="dashboardData.kpis.profit.trend"
          type="currency"
          :icon="ChartBarIcon"
        />
        <KPIWidget
          :title="t('new_customers')"
          :value="dashboardData.kpis.customers.new_customers"
          :change-percentage="dashboardData.kpis.customers.change_percentage"
          :trend="dashboardData.kpis.customers.trend"
          type="number"
          :icon="UsersIcon"
        />
        <KPIWidget
          :title="t('gold_sold')"
          :value="dashboardData.kpis.gold_metrics.gold_sold_grams"
          :change-percentage="dashboardData.kpis.gold_metrics.change_percentage"
          :trend="dashboardData.kpis.gold_metrics.trend"
          type="weight"
          :icon="ScaleIcon"
        />
      </div>

      <!-- Main Dashboard Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Sales Trend Chart -->
        <div class="lg:col-span-2">
          <SalesTrendChart 
            :data="salesTrendData"
            @period-changed="handleChartPeriodChange"
          />
        </div>

        <!-- Alerts Widget -->
        <div>
          <AlertWidget 
            :alerts="dashboardData.alerts"
            @view-all-alerts="showAlertsModal = true"
          />
        </div>
      </div>

      <!-- Secondary Widgets -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Top Products -->
        <TopProductsWidget 
          :products="dashboardData.top_products"
          @view-all="$router.push('/inventory')"
        />

        <!-- Quick Actions -->
        <div class="card p-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">{{ t('quick_actions') }}</h3>
          <div class="grid grid-cols-2 gap-4">
            <router-link
              v-for="action in quickActions"
              :key="action.name"
              :to="action.href"
              class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-primary-300 hover:bg-primary-50 transition-colors group"
            >
              <component 
                :is="action.icon" 
                class="h-6 w-6 text-gray-400 group-hover:text-primary-600 mr-3" 
              />
              <div>
                <p class="text-sm font-medium text-gray-900 group-hover:text-primary-700">
                  {{ t(action.name) }}
                </p>
                <p class="text-xs text-gray-500">{{ t(action.description) }}</p>
              </div>
            </router-link>
          </div>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="text-center py-12">
      <ExclamationTriangleIcon class="mx-auto h-12 w-12 text-red-400" />
      <h3 class="mt-4 text-lg font-medium text-gray-900">{{ t('error_loading_dashboard') }}</h3>
      <p class="mt-2 text-sm text-gray-500">{{ error }}</p>
      <button 
        @click="loadDashboardData" 
        class="mt-4 btn btn-primary"
      >
        {{ t('try_again') }}
      </button>
    </div>

    <!-- Alerts Modal -->
    <AlertsModal 
      v-if="showAlertsModal"
      @close="showAlertsModal = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useI18n } from '@/composables/useI18n'
import { useDashboardWebSocket } from '@/composables/useWebSocket'
import axios from 'axios'
import {
  DocumentTextIcon,
  UsersIcon,
  CubeIcon,
  CalculatorIcon,
  CurrencyDollarIcon,
  ChartBarIcon,
  ScaleIcon,
  ArrowPathIcon,
  ExclamationTriangleIcon,
  PlusIcon,
  EyeIcon,
  ClipboardDocumentListIcon,
  UserPlusIcon
} from '@heroicons/vue/24/outline'

// Import dashboard components
import KPIWidget from '@/components/dashboard/KPIWidget.vue'
import AlertWidget from '@/components/dashboard/AlertWidget.vue'
import SalesTrendChart from '@/components/dashboard/SalesTrendChart.vue'
import TopProductsWidget from '@/components/dashboard/TopProductsWidget.vue'
import AlertsModal from '@/components/dashboard/AlertsModal.vue'

const { t, isRTL, formatNumber, formatCurrency } = useI18n()

// WebSocket integration
const { dashboardData: wsData, lastUpdate, subscribeToDashboardUpdates } = useDashboardWebSocket()

// Reactive data
const loading = ref(false)
const error = ref('')
const selectedPeriod = ref('today')
const showAlertsModal = ref(false)
const dashboardData = ref(null)
const salesTrendData = ref([])
const realTimeUpdates = ref(true)

// Quick actions for the dashboard
const quickActions = [
  {
    name: 'create_invoice',
    description: 'create_new_invoice',
    href: '/invoices/create',
    icon: PlusIcon,
  },
  {
    name: 'add_customer',
    description: 'add_new_customer',
    href: '/customers/create',
    icon: UserPlusIcon,
  },
  {
    name: 'view_inventory',
    description: 'manage_inventory',
    href: '/inventory',
    icon: EyeIcon,
  },
  {
    name: 'view_reports',
    description: 'view_financial_reports',
    href: '/accounting',
    icon: ClipboardDocumentListIcon,
  },
]

// Methods
const loadDashboardData = async () => {
  loading.value = true
  error.value = ''
  
  try {
    const [dashboardResponse, salesTrendResponse] = await Promise.all([
      axios.get('/api/dashboard/data', {
        params: { period: selectedPeriod.value }
      }),
      axios.get('/api/dashboard/sales-trend', {
        params: { period: '7_days' }
      })
    ])
    
    dashboardData.value = dashboardResponse.data.data
    salesTrendData.value = salesTrendResponse.data.data
  } catch (err) {
    console.error('Error loading dashboard data:', err)
    error.value = err.response?.data?.message || t('error_loading_data')
  } finally {
    loading.value = false
  }
}

const refreshData = () => {
  loadDashboardData()
}

const handleChartPeriodChange = async (period: string) => {
  try {
    const response = await axios.get('/api/dashboard/sales-trend', {
      params: { period }
    })
    salesTrendData.value = response.data.data
  } catch (err) {
    console.error('Error loading sales trend:', err)
  }
}

const formatTime = (date: Date) => {
  return date.toLocaleTimeString()
}

// WebSocket subscription
let unsubscribeDashboard: (() => void) | null = null

// Lifecycle
onMounted(() => {
  loadDashboardData()
  
  // Subscribe to real-time dashboard updates
  unsubscribeDashboard = subscribeToDashboardUpdates()
})

onUnmounted(() => {
  if (unsubscribeDashboard) {
    unsubscribeDashboard()
  }
})

// Watch for WebSocket updates and merge with local data
const mergeWebSocketData = () => {
  if (wsData.value && dashboardData.value) {
    if (wsData.value.kpis) {
      dashboardData.value.kpis = { ...dashboardData.value.kpis, ...wsData.value.kpis }
    }
    if (wsData.value.alerts) {
      dashboardData.value.alerts = wsData.value.alerts
    }
    if (wsData.value.salesTrend) {
      salesTrendData.value = wsData.value.salesTrend
    }
  }
}

// Watch for WebSocket data changes
const watchWebSocketData = () => {
  if (realTimeUpdates.value) {
    mergeWebSocketData()
  }
}
</script>