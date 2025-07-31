<template>
  <div class="customer-management">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
        {{ $t('customers.title') }}
      </h1>
      <div class="flex gap-3">
        <button
          @click="showCreateModal = true"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2"
        >
          <PlusIcon class="w-5 h-5" />
          {{ $t('customers.add_customer') }}
        </button>
        <button
          @click="showImportModal = true"
          class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center gap-2"
        >
          <ArrowUpTrayIcon class="w-5 h-5" />
          {{ $t('customers.import') }}
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('common.search') }}
          </label>
          <input
            v-model="filters.search"
            type="text"
            :placeholder="$t('customers.search_placeholder')"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            @input="debouncedSearch"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('customers.group') }}
          </label>
          <select
            v-model="filters.group_id"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            @change="loadCustomers"
          >
            <option value="">{{ $t('customers.all_groups') }}</option>
            <option v-for="group in customerGroups" :key="group.id" :value="group.id">
              {{ group.localized_name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('customers.type') }}
          </label>
          <select
            v-model="filters.customer_type"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            @change="loadCustomers"
          >
            <option value="">{{ $t('customers.all_types') }}</option>
            <option value="individual">{{ $t('customers.individual') }}</option>
            <option value="business">{{ $t('customers.business') }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('customers.status') }}
          </label>
          <select
            v-model="filters.is_active"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            @change="loadCustomers"
          >
            <option value="">{{ $t('customers.all_statuses') }}</option>
            <option value="1">{{ $t('common.active') }}</option>
            <option value="0">{{ $t('common.inactive') }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <UsersIcon class="w-8 h-8 text-blue-600" />
          </div>
          <div class="mr-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
              {{ $t('customers.total_customers') }}
            </div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ statistics.total_customers || 0 }}
            </div>
          </div>
        </div>
      </div>
      
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <UserPlusIcon class="w-8 h-8 text-green-600" />
          </div>
          <div class="mr-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
              {{ $t('customers.new_this_month') }}
            </div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ statistics.new_this_month || 0 }}
            </div>
          </div>
        </div>
      </div>
      
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <ExclamationTriangleIcon class="w-8 h-8 text-red-600" />
          </div>
          <div class="mr-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
              {{ $t('customers.credit_exceeded') }}
            </div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ statistics.credit_limit_exceeded || 0 }}
            </div>
          </div>
        </div>
      </div>
      
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <CakeIcon class="w-8 h-8 text-purple-600" />
          </div>
          <div class="mr-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
              {{ $t('customers.upcoming_birthdays') }}
            </div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ statistics.upcoming_birthdays || 0 }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Customer Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ $t('customers.name') }}
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ $t('customers.contact') }}
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ $t('customers.group') }}
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ $t('customers.balance') }}
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ $t('customers.credit_limit') }}
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ $t('common.status') }}
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ $t('common.actions') }}
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="customer in customers.data" :key="customer.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="flex-shrink-0 h-10 w-10">
                    <div class="h-10 w-10 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                      <UserIcon class="w-6 h-6 text-gray-600 dark:text-gray-300" />
                    </div>
                  </div>
                  <div class="mr-4">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                      {{ customer.name }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                      {{ customer.customer_type === 'individual' ? $t('customers.individual') : $t('customers.business') }}
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                <div>{{ customer.phone }}</div>
                <div class="text-gray-500 dark:text-gray-400">{{ customer.email }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                {{ customer.group?.localized_name || '-' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">
                <div class="text-gray-900 dark:text-white">
                  {{ formatCurrency(customer.current_balance) }}
                </div>
                <div class="text-gray-500 dark:text-gray-400" v-if="customer.gold_balance > 0">
                  {{ customer.gold_balance }} {{ $t('common.gram') }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                {{ formatCurrency(customer.credit_limit) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  :class="{
                    'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100': customer.is_active,
                    'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100': !customer.is_active
                  }"
                  class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                >
                  {{ customer.is_active ? $t('common.active') : $t('common.inactive') }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex gap-2">
                  <button
                    @click="viewCustomer(customer)"
                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                  >
                    <EyeIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="editCustomer(customer)"
                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                  >
                    <PencilIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="deleteCustomer(customer)"
                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                  >
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Pagination -->
      <div class="bg-white dark:bg-gray-800 px-4 py-3 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 sm:px-6">
        <div class="flex-1 flex justify-between sm:hidden">
          <button
            @click="previousPage"
            :disabled="customers.current_page === 1"
            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
          >
            {{ $t('common.previous') }}
          </button>
          <button
            @click="nextPage"
            :disabled="customers.current_page === customers.last_page"
            class="mr-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
          >
            {{ $t('common.next') }}
          </button>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-gray-700 dark:text-gray-300">
              {{ $t('common.showing') }}
              <span class="font-medium">{{ customers.from }}</span>
              {{ $t('common.to') }}
              <span class="font-medium">{{ customers.to }}</span>
              {{ $t('common.of') }}
              <span class="font-medium">{{ customers.total }}</span>
              {{ $t('common.results') }}
            </p>
          </div>
          <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
              <button
                @click="previousPage"
                :disabled="customers.current_page === 1"
                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
              >
                <ChevronRightIcon class="h-5 w-5" />
              </button>
              <button
                @click="nextPage"
                :disabled="customers.current_page === customers.last_page"
                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
              >
                <ChevronLeftIcon class="h-5 w-5" />
              </button>
            </nav>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useI18n } from '@/composables/useI18n'
import {
  PlusIcon,
  ArrowUpTrayIcon,
  UsersIcon,
  UserPlusIcon,
  ExclamationTriangleIcon,
  CakeIcon,
  UserIcon,
  EyeIcon,
  PencilIcon,
  TrashIcon,
  ChevronLeftIcon,
  ChevronRightIcon
} from '@heroicons/vue/24/outline'

const { t } = useI18n()

// Reactive data
const customers = ref({
  data: [],
  current_page: 1,
  last_page: 1,
  from: 0,
  to: 0,
  total: 0
})

const customerGroups = ref([])
const statistics = ref({})
const loading = ref(false)
const showCreateModal = ref(false)
const showImportModal = ref(false)

const filters = reactive({
  search: '',
  group_id: '',
  customer_type: '',
  is_active: '',
  page: 1
})

// Computed
const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('fa-IR', {
    style: 'currency',
    currency: 'IRR',
    minimumFractionDigits: 0
  }).format(amount)
}

// Methods
const loadCustomers = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    Object.entries(filters).forEach(([key, value]) => {
      if (value) params.append(key, value.toString())
    })

    const response = await fetch(`/api/customers?${params}`, {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Accept': 'application/json'
      }
    })

    if (response.ok) {
      const data = await response.json()
      customers.value = data.data
    }
  } catch (error) {
    console.error('Error loading customers:', error)
  } finally {
    loading.value = false
  }
}

const loadCustomerGroups = async () => {
  try {
    const response = await fetch('/api/customer-groups?active_only=1', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Accept': 'application/json'
      }
    })

    if (response.ok) {
      const data = await response.json()
      customerGroups.value = data.data
    }
  } catch (error) {
    console.error('Error loading customer groups:', error)
  }
}

const loadStatistics = async () => {
  try {
    const response = await fetch('/api/customers/statistics', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Accept': 'application/json'
      }
    })

    if (response.ok) {
      const data = await response.json()
      statistics.value = data.data
    }
  } catch (error) {
    console.error('Error loading statistics:', error)
  }
}

const debouncedSearch = (() => {
  let timeout: NodeJS.Timeout
  return () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
      loadCustomers()
    }, 500)
  }
})()

const previousPage = () => {
  if (filters.page > 1) {
    filters.page--
    loadCustomers()
  }
}

const nextPage = () => {
  if (filters.page < customers.value.last_page) {
    filters.page++
    loadCustomers()
  }
}

const viewCustomer = (customer: any) => {
  // Navigate to customer details page
  console.log('View customer:', customer)
}

const editCustomer = (customer: any) => {
  // Open edit modal
  console.log('Edit customer:', customer)
}

const deleteCustomer = async (customer: any) => {
  if (confirm(t('customers.confirm_delete'))) {
    try {
      const response = await fetch(`/api/customers/${customer.id}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Accept': 'application/json'
        }
      })

      if (response.ok) {
        loadCustomers()
      }
    } catch (error) {
      console.error('Error deleting customer:', error)
    }
  }
}

// Lifecycle
onMounted(() => {
  loadCustomers()
  loadCustomerGroups()
  loadStatistics()
})
</script>

<style scoped>
.customer-management {
  @apply p-6;
}
</style>