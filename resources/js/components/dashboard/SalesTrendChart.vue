<template>
  <div class="card p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-medium text-gray-900">{{ t('sales_trend') }}</h3>
      <div class="flex space-x-2">
        <button
          v-for="period in periods"
          :key="period.value"
          @click="selectedPeriod = period.value"
          :class="[
            'px-3 py-1 text-xs font-medium rounded-md',
            selectedPeriod === period.value
              ? 'bg-primary-100 text-primary-700'
              : 'text-gray-500 hover:text-gray-700'
          ]"
        >
          {{ t(period.label) }}
        </button>
      </div>
    </div>

    <div class="h-64" ref="chartContainer">
      <canvas ref="chartCanvas"></canvas>
    </div>

    <div class="mt-4 grid grid-cols-3 gap-4 text-center">
      <div>
        <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(totalSales) }}</p>
        <p class="text-sm text-gray-500">{{ t('total_sales') }}</p>
      </div>
      <div>
        <p class="text-2xl font-bold text-gray-900">{{ totalOrders }}</p>
        <p class="text-sm text-gray-500">{{ t('total_orders') }}</p>
      </div>
      <div>
        <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(averageOrder) }}</p>
        <p class="text-sm text-gray-500">{{ t('average_order') }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useI18n } from '@/composables/useI18n'
import Chart from 'chart.js/auto'

interface SalesData {
  date: string
  total: number
  count: number
}

interface Props {
  data: SalesData[]
}

const props = defineProps<Props>()
const emit = defineEmits(['period-changed'])

const { t, formatCurrency, isRTL } = useI18n()

const chartCanvas = ref<HTMLCanvasElement>()
const chartContainer = ref<HTMLElement>()
const selectedPeriod = ref('7_days')
let chartInstance: Chart | null = null

const periods = [
  { value: '7_days', label: '7_days' },
  { value: '30_days', label: '30_days' },
  { value: '90_days', label: '90_days' }
]

const totalSales = computed(() => {
  return props.data.reduce((sum, item) => sum + item.total, 0)
})

const totalOrders = computed(() => {
  return props.data.reduce((sum, item) => sum + item.count, 0)
})

const averageOrder = computed(() => {
  return totalOrders.value > 0 ? totalSales.value / totalOrders.value : 0
})

const createChart = async () => {
  if (!chartCanvas.value || !props.data.length) return

  await nextTick()

  const ctx = chartCanvas.value.getContext('2d')
  if (!ctx) return

  // Destroy existing chart
  if (chartInstance) {
    chartInstance.destroy()
  }

  const labels = props.data.map(item => {
    const date = new Date(item.date)
    return date.toLocaleDateString(isRTL.value ? 'fa-IR' : 'en-US', {
      month: 'short',
      day: 'numeric'
    })
  })

  const salesData = props.data.map(item => item.total)
  const ordersData = props.data.map(item => item.count)

  chartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: t('sales_amount'),
          data: salesData,
          borderColor: 'rgb(59, 130, 246)',
          backgroundColor: 'rgba(59, 130, 246, 0.1)',
          tension: 0.4,
          fill: true,
          yAxisID: 'y'
        },
        {
          label: t('order_count'),
          data: ordersData,
          borderColor: 'rgb(16, 185, 129)',
          backgroundColor: 'rgba(16, 185, 129, 0.1)',
          tension: 0.4,
          fill: false,
          yAxisID: 'y1'
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false,
      },
      plugins: {
        legend: {
          position: 'top',
          rtl: isRTL.value,
          labels: {
            usePointStyle: true,
            padding: 20
          }
        },
        tooltip: {
          rtl: isRTL.value,
          callbacks: {
            label: function(context) {
              const label = context.dataset.label || ''
              const value = context.dataset.yAxisID === 'y' 
                ? formatCurrency(context.parsed.y)
                : context.parsed.y.toString()
              return `${label}: ${value}`
            }
          }
        }
      },
      scales: {
        x: {
          display: true,
          title: {
            display: true,
            text: t('date')
          },
          reverse: isRTL.value
        },
        y: {
          type: 'linear',
          display: true,
          position: isRTL.value ? 'right' : 'left',
          title: {
            display: true,
            text: t('sales_amount')
          },
          ticks: {
            callback: function(value) {
              return formatCurrency(Number(value))
            }
          }
        },
        y1: {
          type: 'linear',
          display: true,
          position: isRTL.value ? 'left' : 'right',
          title: {
            display: true,
            text: t('order_count')
          },
          grid: {
            drawOnChartArea: false,
          },
        }
      }
    }
  })
}

watch(() => props.data, createChart, { deep: true })
watch(selectedPeriod, (newPeriod) => {
  emit('period-changed', newPeriod)
})

onMounted(() => {
  createChart()
})
</script>