<template>
  <div class="card p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-medium text-gray-900">{{ t('alerts') }}</h3>
      <span v-if="totalAlerts > 0" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
        {{ totalAlerts }}
      </span>
    </div>

    <div v-if="totalAlerts === 0" class="text-center py-8">
      <CheckCircleIcon class="mx-auto h-12 w-12 text-green-400" />
      <p class="mt-2 text-sm text-gray-500">{{ t('no_alerts') }}</p>
    </div>

    <div v-else class="space-y-3">
      <div 
        v-for="alert in alertItems" 
        :key="alert.type"
        class="flex items-center justify-between p-3 rounded-lg border"
        :class="getAlertBorderClass(alert.severity)"
      >
        <div class="flex items-center">
          <component 
            :is="alert.icon" 
            :class="getAlertIconClass(alert.severity)"
            class="h-5 w-5 mr-3" 
          />
          <div>
            <p class="text-sm font-medium text-gray-900">{{ t(alert.title) }}</p>
            <p class="text-xs text-gray-500">{{ alert.description }}</p>
          </div>
        </div>
        <span 
          :class="getAlertBadgeClass(alert.severity)"
          class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
        >
          {{ alert.count }}
        </span>
      </div>
    </div>

    <div v-if="totalAlerts > 0" class="mt-4 pt-4 border-t border-gray-200">
      <button 
        @click="$emit('view-all-alerts')"
        class="w-full text-center text-sm text-primary-600 hover:text-primary-500 font-medium"
      >
        {{ t('view_all_alerts') }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from '@/composables/useI18n'
import { 
  CheckCircleIcon,
  ExclamationTriangleIcon,
  DocumentTextIcon,
  CreditCardIcon,
  CubeIcon,
  UserIcon
} from '@heroicons/vue/24/outline'

interface Alert {
  type: string
  title: string
  description: string
  count: number
  severity: 'low' | 'medium' | 'high' | 'critical'
  icon: any
}

interface Props {
  alerts: {
    overdue_invoices: number
    cheques_due: number
    low_inventory: number
    credit_warnings: number
  }
}

const props = defineProps<Props>()
const emit = defineEmits(['view-all-alerts'])

const { t } = useI18n()

const totalAlerts = computed(() => {
  return Object.values(props.alerts).reduce((sum, count) => sum + count, 0)
})

const alertItems = computed((): Alert[] => {
  const items: Alert[] = []
  
  if (props.alerts.overdue_invoices > 0) {
    items.push({
      type: 'overdue_invoices',
      title: 'overdue_invoices',
      description: t('overdue_invoices_desc'),
      count: props.alerts.overdue_invoices,
      severity: props.alerts.overdue_invoices > 10 ? 'critical' : 'high',
      icon: DocumentTextIcon
    })
  }
  
  if (props.alerts.cheques_due > 0) {
    items.push({
      type: 'cheques_due',
      title: 'cheques_due',
      description: t('cheques_due_desc'),
      count: props.alerts.cheques_due,
      severity: 'medium',
      icon: CreditCardIcon
    })
  }
  
  if (props.alerts.low_inventory > 0) {
    items.push({
      type: 'low_inventory',
      title: 'low_inventory',
      description: t('low_inventory_desc'),
      count: props.alerts.low_inventory,
      severity: 'medium',
      icon: CubeIcon
    })
  }
  
  if (props.alerts.credit_warnings > 0) {
    items.push({
      type: 'credit_warnings',
      title: 'credit_warnings',
      description: t('credit_warnings_desc'),
      count: props.alerts.credit_warnings,
      severity: 'high',
      icon: UserIcon
    })
  }
  
  return items
})

const getAlertBorderClass = (severity: string) => {
  switch (severity) {
    case 'critical': return 'border-red-300 bg-red-50'
    case 'high': return 'border-orange-300 bg-orange-50'
    case 'medium': return 'border-yellow-300 bg-yellow-50'
    default: return 'border-blue-300 bg-blue-50'
  }
}

const getAlertIconClass = (severity: string) => {
  switch (severity) {
    case 'critical': return 'text-red-600'
    case 'high': return 'text-orange-600'
    case 'medium': return 'text-yellow-600'
    default: return 'text-blue-600'
  }
}

const getAlertBadgeClass = (severity: string) => {
  switch (severity) {
    case 'critical': return 'bg-red-100 text-red-800'
    case 'high': return 'bg-orange-100 text-orange-800'
    case 'medium': return 'bg-yellow-100 text-yellow-800'
    default: return 'bg-blue-100 text-blue-800'
  }
}
</script>