<template>
  <div class="card p-6">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-600">{{ title }}</p>
        <p class="text-3xl font-bold text-gray-900">{{ formatValue(value) }}</p>
        <div class="flex items-center mt-2">
          <component 
            :is="trendIcon" 
            :class="trendColorClass"
            class="h-4 w-4 mr-1"
          />
          <span :class="trendColorClass" class="text-sm font-medium">
            {{ Math.abs(changePercentage) }}%
          </span>
          <span class="text-sm text-gray-500 ml-1">
            {{ t('vs_previous_period') }}
          </span>
        </div>
      </div>
      <div class="flex-shrink-0">
        <component 
          :is="icon" 
          class="h-12 w-12 text-primary-600" 
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from '@/composables/useI18n'
import { 
  ArrowUpIcon, 
  ArrowDownIcon,
  CurrencyDollarIcon,
  ChartBarIcon,
  UsersIcon,
  ScaleIcon
} from '@heroicons/vue/24/outline'

interface Props {
  title: string
  value: number
  changePercentage: number
  trend: 'up' | 'down'
  type: 'currency' | 'number' | 'percentage' | 'weight'
  icon?: any
}

const props = withDefaults(defineProps<Props>(), {
  icon: CurrencyDollarIcon
})

const { t, formatNumber, formatCurrency } = useI18n()

const trendIcon = computed(() => {
  return props.trend === 'up' ? ArrowUpIcon : ArrowDownIcon
})

const trendColorClass = computed(() => {
  return props.trend === 'up' 
    ? 'text-green-600' 
    : 'text-red-600'
})

const formatValue = (value: number) => {
  switch (props.type) {
    case 'currency':
      return formatCurrency(value)
    case 'percentage':
      return `${formatNumber(value)}%`
    case 'weight':
      return `${formatNumber(value)} ${t('grams')}`
    default:
      return formatNumber(value)
  }
}
</script>