<template>
  <div class="customer-notifications">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
        {{ $t('notifications.title') }}
      </h1>
      <div class="flex gap-3">
        <button
          @click="createBirthdayNotifications"
          class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg flex items-center gap-2"
        >
          <CakeIcon class="w-5 h-5" />
          {{ $t('notifications.create_birthday') }}
        </button>
        <button
          @click="processPendingNotifications"
          class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center gap-2"
        >
          <PlayIcon class="w-5 h-5" />
          {{ $t('notifications.process_pending') }}
        </button>
        <button
          @click="showCreateModal = true"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2"
        >
          <PlusIcon class="w-5 h-5" />
          {{ $t('notifications.create_custom') }}
        </button>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <BellIcon class="w-8 h-8 text-blue-600" />
          </div>
          <div class="mr-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
              {{ $t('notifications.total') }}
            </div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ statistics.total_notifications || 0 }}
            </div>
          </div>
        </div>
      </div>
      
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <ClockIcon class="w-8 h-8 text-yellow-600" />
          </div>
          <div class="mr-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
              {{ $t('notifications.pending') }}
            </div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ statistics.pending_notifications || 0 }}
            </div>
          </div>
        </div>
      </div>
      
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <CheckCircleIcon class="w-8 h-8 text-green-600" />
          </div>
          <div class="mr-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
              {{ $t('notifications.sent') }}
            </div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ statistics.sent_notifications || 0 }}
            </div>
          </div>
        </div>
      </div>
      
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <XCircleIcon class="w-8 h-8 text-red-600" />
          </div>
          <div class="mr-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
              {{ $t('notifications.failed') }}
            </div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ statistics.failed_notifications || 0 }}
            </div>
          </div>
        </div>
      </div>
      
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <ExclamationTriangleIcon class="w-8 h-8 text-orange-600" />
          </div>
          <div class="mr-4">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
              {{ $t('notifications.due') }}
            </div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ statistics.due_notifications || 0 }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('notifications.type') }}
          </label>
          <select
            v-model="filters.type"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            @change="loadNotifications"
          >
            <option value="">{{ $t('notifications.all_types') }}</option>
            <option value="birthday">{{ $t('notifications.birthday') }}</option>
            <option value="occasion">{{ $t('notifications.occasion') }}</option>
            <option value="overdue_payment">{{ $t('notifications.overdue_payment') }}</option>
            <option value="credit_limit_exceeded">{{ $t('notifications.credit_limit_exceeded') }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('notifications.status') }}
          </label>
          <select
            v-model="filters.status"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            @change="loadNotifications"
          >
            <option value="">{{ $t('notifications.all_statuses') }}</option>
            <option value="pending">{{ $t('notifications.pending') }}</option>
            <option value="sent">{{ $t('notifications.sent') }}</option>
            <option value="failed">{{ $t('notifications.failed') }}</option>
            <option value="cancelled">{{ $t('notifications.cancelled') }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('notifications.start_date') }}
          </label>
          <input
            v-model="filters.start_date"
            type="date"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            @change="loadNotifications"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('notifications.end_date') }}
          </label>
          <input
            v-model="filters.end_date"
            type="date"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            @change="loadNotifications"
          />
        </div>
      </div>
    </div>

    <!-- Notifications Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ $t('notifications.customer') }}
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ $t('notifications.type') }}
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ $t('notifications.title') }}
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ $t('notifications.scheduled_at') }}
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ $t('notifications.channels') }}
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ $t('notifications.status') }}
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                {{ $t('common.actions') }}
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="notification in notifications.data" :key="notification.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                {{ notification.customer?.name || '-' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  :class="getTypeColor(notification.type)"
                  class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                >
                  {{ getTypeLabel(notification.type) }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                <div class="max-w-xs truncate">{{ notification.localized_title || notification.title }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                {{ formatDateTime(notification.scheduled_at) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                <div class="flex gap-1">
                  <span
                    v-for="channel in notification.channels"
                    :key="channel"
                    class="px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 rounded"
                  >
                    {{ getChannelLabel(channel) }}
                  </span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  :class="getStatusColor(notification.status)"
                  class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                >
                  {{ getStatusLabel(notification.status) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex gap-2">
                  <button
                    @click="viewNotification(notification)"
                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                  >
                    <EyeIcon class="w-4 h-4" />
                  </button>
                  <button
                    v-if="notification.status === 'pending'"
                    @click="editNotification(notification)"
                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                  >
                    <PencilIcon class="w-4 h-4" />
                  </button>
                  <button
                    v-if="notification.status === 'pending'"
                    @click="cancelNotification(notification)"
                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                  >
                    <XMarkIcon class="w-4 h-4" />
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
            :disabled="notifications.current_page === 1"
            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
          >
            {{ $t('common.previous') }}
          </button>
          <button
            @click="nextPage"
            :disabled="notifications.current_page === notifications.last_page"
            class="mr-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
          >
            {{ $t('common.next') }}
          </button>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-gray-700 dark:text-gray-300">
              {{ $t('common.showing') }}
              <span class="font-medium">{{ notifications.from }}</span>
              {{ $t('common.to') }}
              <span class="font-medium">{{ notifications.to }}</span>
              {{ $t('common.of') }}
              <span class="font-medium">{{ notifications.total }}</span>
              {{ $t('common.results') }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from '@/composables/useI18n'
import {
  PlusIcon,
  CakeIcon,
  PlayIcon,
  BellIcon,
  ClockIcon,
  CheckCircleIcon,
  XCircleIcon,
  ExclamationTriangleIcon,
  EyeIcon,
  PencilIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'

const { t } = useI18n()

// Reactive data
const notifications = ref({
  data: [],
  current_page: 1,
  last_page: 1,
  from: 0,
  to: 0,
  total: 0
})

const statistics = ref({})
const loading = ref(false)
const showCreateModal = ref(false)

const filters = reactive({
  type: '',
  status: '',
  start_date: '',
  end_date: '',
  page: 1
})

// Methods
const loadNotifications = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    Object.entries(filters).forEach(([key, value]) => {
      if (value) params.append(key, value.toString())
    })

    const response = await fetch(`/api/customer-notifications?${params}`, {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Accept': 'application/json'
      }
    })

    if (response.ok) {
      const data = await response.json()
      notifications.value = data.data
    }
  } catch (error) {
    console.error('Error loading notifications:', error)
  } finally {
    loading.value = false
  }
}

const loadStatistics = async () => {
  try {
    const response = await fetch('/api/customer-notifications/statistics', {
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

const createBirthdayNotifications = async () => {
  try {
    const response = await fetch('/api/customer-notifications/create-birthday', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ days_ahead: 7 })
    })

    if (response.ok) {
      const data = await response.json()
      alert(`${data.data.created} birthday notifications created`)
      loadNotifications()
      loadStatistics()
    }
  } catch (error) {
    console.error('Error creating birthday notifications:', error)
  }
}

const processPendingNotifications = async () => {
  try {
    const response = await fetch('/api/customer-notifications/process-pending', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Accept': 'application/json'
      }
    })

    if (response.ok) {
      const data = await response.json()
      alert(`${data.data.processed} notifications processed`)
      loadNotifications()
      loadStatistics()
    }
  } catch (error) {
    console.error('Error processing notifications:', error)
  }
}

const cancelNotification = async (notification: any) => {
  if (confirm(t('notifications.confirm_cancel'))) {
    try {
      const response = await fetch(`/api/customer-notifications/${notification.id}/cancel`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Accept': 'application/json'
        }
      })

      if (response.ok) {
        loadNotifications()
        loadStatistics()
      }
    } catch (error) {
      console.error('Error cancelling notification:', error)
    }
  }
}

const viewNotification = (notification: any) => {
  console.log('View notification:', notification)
}

const editNotification = (notification: any) => {
  console.log('Edit notification:', notification)
}

const previousPage = () => {
  if (filters.page > 1) {
    filters.page--
    loadNotifications()
  }
}

const nextPage = () => {
  if (filters.page < notifications.value.last_page) {
    filters.page++
    loadNotifications()
  }
}

// Helper methods
const getTypeColor = (type: string) => {
  const colors = {
    birthday: 'bg-purple-100 text-purple-800 dark:bg-purple-800 dark:text-purple-100',
    occasion: 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100',
    overdue_payment: 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
    credit_limit_exceeded: 'bg-orange-100 text-orange-800 dark:bg-orange-800 dark:text-orange-100'
  }
  return colors[type] || 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100'
}

const getTypeLabel = (type: string) => {
  const labels = {
    birthday: t('notifications.birthday'),
    occasion: t('notifications.occasion'),
    overdue_payment: t('notifications.overdue_payment'),
    credit_limit_exceeded: t('notifications.credit_limit_exceeded')
  }
  return labels[type] || type
}

const getStatusColor = (status: string) => {
  const colors = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
    sent: 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
    failed: 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
    cancelled: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100'
  }
  return colors[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100'
}

const getStatusLabel = (status: string) => {
  const labels = {
    pending: t('notifications.pending'),
    sent: t('notifications.sent'),
    failed: t('notifications.failed'),
    cancelled: t('notifications.cancelled')
  }
  return labels[status] || status
}

const getChannelLabel = (channel: string) => {
  const labels = {
    email: t('notifications.email'),
    sms: t('notifications.sms'),
    whatsapp: t('notifications.whatsapp'),
    system: t('notifications.system')
  }
  return labels[channel] || channel
}

const formatDateTime = (dateTime: string) => {
  return new Date(dateTime).toLocaleString('fa-IR')
}

// Lifecycle
onMounted(() => {
  loadNotifications()
  loadStatistics()
})
</script>

<style scoped>
.customer-notifications {
  @apply p-6;
}
</style>