<template>
  <div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div 
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        @click="$emit('close')"
      ></div>

      <!-- Modal panel -->
      <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
        <!-- Header -->
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <div class="flex items-center justify-between">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
              {{ t('all_alerts') }}
            </h3>
            <button 
              @click="$emit('close')"
              class="text-gray-400 hover:text-gray-600"
            >
              <XMarkIcon class="h-6 w-6" />
            </button>
          </div>
        </div>

        <!-- Content -->
        <div class="bg-white px-4 pb-4 sm:px-6 sm:pb-6">
          <div v-if="loading" class="text-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
            <p class="mt-2 text-sm text-gray-500">{{ t('loading_alerts') }}</p>
          </div>

          <div v-else-if="alerts" class="space-y-6">
            <!-- Overdue Invoices -->
            <div v-if="alerts.overdue_invoices.count > 0">
              <h4 class="text-md font-medium text-gray-900 mb-3 flex items-center">
                <DocumentTextIcon class="h-5 w-5 text-red-600 mr-2" />
                {{ t('overdue_invoices') }} ({{ alerts.overdue_invoices.count }})
              </h4>
              <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="space-y-3">
                  <div 
                    v-for="invoice in alerts.overdue_invoices.items" 
                    :key="invoice.id"
                    class="flex items-center justify-between py-2 border-b border-red-200 last:border-b-0"
                  >
                    <div>
                      <p class="text-sm font-medium text-gray-900">
                        {{ invoice.invoice_number }} - {{ invoice.customer_name }}
                      </p>
                      <p class="text-xs text-gray-500">
                        {{ t('due_date') }}: {{ formatDate(invoice.due_date) }} 
                        ({{ invoice.days_overdue }} {{ t('days_overdue') }})
                      </p>
                    </div>
                    <div class="text-right">
                      <p class="text-sm font-medium text-gray-900">{{ formatCurrency(invoice.amount) }}</p>
                      <span 
                        :class="getSeverityBadgeClass(invoice.severity)"
                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                      >
                        {{ t(invoice.severity) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Cheques Due -->
            <div v-if="alerts.cheques_due.count > 0">
              <h4 class="text-md font-medium text-gray-900 mb-3 flex items-center">
                <CreditCardIcon class="h-5 w-5 text-yellow-600 mr-2" />
                {{ t('cheques_due') }} ({{ alerts.cheques_due.count }})
              </h4>
              <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="space-y-3">
                  <div 
                    v-for="cheque in alerts.cheques_due.items" 
                    :key="cheque.id"
                    class="flex items-center justify-between py-2 border-b border-yellow-200 last:border-b-0"
                  >
                    <div>
                      <p class="text-sm font-medium text-gray-900">
                        {{ cheque.cheque_number }} - {{ cheque.customer_name }}
                      </p>
                      <p class="text-xs text-gray-500">
                        {{ t('due_date') }}: {{ formatDate(cheque.due_date) }}
                      </p>
                    </div>
                    <div class="text-right">
                      <p class="text-sm font-medium text-gray-900">{{ formatCurrency(cheque.amount) }}</p>
                      <span 
                        :class="getSeverityBadgeClass(cheque.severity)"
                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                      >
                        {{ t(cheque.severity) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Low Inventory -->
            <div v-if="alerts.low_inventory.count > 0">
              <h4 class="text-md font-medium text-gray-900 mb-3 flex items-center">
                <CubeIcon class="h-5 w-5 text-orange-600 mr-2" />
                {{ t('low_inventory') }} ({{ alerts.low_inventory.count }})
              </h4>
              <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                <div class="space-y-3">
                  <div 
                    v-for="product in alerts.low_inventory.items" 
                    :key="product.id"
                    class="flex items-center justify-between py-2 border-b border-orange-200 last:border-b-0"
                  >
                    <div>
                      <p class="text-sm font-medium text-gray-900">{{ product.name }}</p>
                      <p class="text-xs text-gray-500">
                        {{ t('sku') }}: {{ product.sku }} | {{ t('category') }}: {{ product.category }}
                      </p>
                    </div>
                    <div class="text-right">
                      <p class="text-sm font-medium text-gray-900">
                        {{ product.current_stock }} / {{ product.minimum_stock }}
                      </p>
                      <span 
                        :class="getSeverityBadgeClass(product.severity)"
                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                      >
                        {{ product.stock_percentage }}%
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Credit Limit Warnings -->
            <div v-if="alerts.credit_limit_warnings.count > 0">
              <h4 class="text-md font-medium text-gray-900 mb-3 flex items-center">
                <UserIcon class="h-5 w-5 text-purple-600 mr-2" />
                {{ t('credit_limit_warnings') }} ({{ alerts.credit_limit_warnings.count }})
              </h4>
              <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                <div class="space-y-3">
                  <div 
                    v-for="customer in alerts.credit_limit_warnings.items" 
                    :key="customer.id"
                    class="flex items-center justify-between py-2 border-b border-purple-200 last:border-b-0"
                  >
                    <div>
                      <p class="text-sm font-medium text-gray-900">{{ customer.name }}</p>
                      <p class="text-xs text-gray-500">
                        {{ t('used') }}: {{ formatCurrency(customer.used_amount) }} / {{ formatCurrency(customer.credit_limit) }}
                      </p>
                    </div>
                    <div class="text-right">
                      <p class="text-sm font-medium text-gray-900">{{ customer.used_percentage }}%</p>
                      <span 
                        :class="getSeverityBadgeClass(customer.severity)"
                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                      >
                        {{ t(customer.severity) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
          <button 
            @click="$emit('close')"
            class="btn btn-secondary"
          >
            {{ t('close') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from '@/composables/useI18n'
import axios from 'axios'
import {
  XMarkIcon,
  DocumentTextIcon,
  CreditCardIcon,
  CubeIcon,
  UserIcon
} from '@heroicons/vue/24/outline'

const emit = defineEmits(['close'])

const { t, formatCurrency, formatDate } = useI18n()

const loading = ref(true)
const alerts = ref(null)

const loadAlerts = async () => {
  try {
    const response = await axios.get('/api/dashboard/alerts')
    alerts.value = response.data.data
  } catch (error) {
    console.error('Error loading alerts:', error)
  } finally {
    loading.value = false
  }
}

const getSeverityBadgeClass = (severity: string) => {
  switch (severity) {
    case 'critical': return 'bg-red-100 text-red-800'
    case 'high': return 'bg-orange-100 text-orange-800'
    case 'medium': return 'bg-yellow-100 text-yellow-800'
    default: return 'bg-blue-100 text-blue-800'
  }
}

onMounted(() => {
  loadAlerts()
})
</script>