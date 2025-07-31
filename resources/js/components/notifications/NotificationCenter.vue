<template>
  <div class="relative">
    <!-- Notification Bell Button -->
    <button
      @click="toggleNotifications"
      class="relative p-2 text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white transition-colors"
      :class="{ 'text-blue-600 dark:text-blue-400': showNotifications }"
    >
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
      </svg>
      
      <!-- Unread Count Badge -->
      <span
        v-if="unreadCount > 0"
        class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"
      >
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>

    <!-- Notifications Dropdown -->
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <div
        v-if="showNotifications"
        class="absolute right-0 mt-2 w-96 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50"
        @click.stop
      >
        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ $t('notifications.title') }}
          </h3>
          <div class="flex items-center space-x-2">
            <!-- Connection Status -->
            <div class="flex items-center space-x-1">
              <div
                class="w-2 h-2 rounded-full"
                :class="{
                  'bg-green-500': connectionStatus === 'connected',
                  'bg-yellow-500': connectionStatus === 'connecting',
                  'bg-red-500': connectionStatus === 'error'
                }"
              ></div>
              <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ getConnectionStatusText() }}
              </span>
            </div>
            
            <!-- Mark All Read -->
            <button
              v-if="unreadCount > 0"
              @click="markAllAsRead"
              class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
            >
              {{ $t('notifications.markAllRead') }}
            </button>
          </div>
        </div>

        <!-- Notifications List -->
        <div class="max-h-96 overflow-y-auto">
          <div v-if="notifications.length === 0" class="p-4 text-center text-gray-500 dark:text-gray-400">
            {{ $t('notifications.empty') }}
          </div>
          
          <div v-else>
            <div
              v-for="notification in notifications"
              :key="notification.id"
              class="p-4 border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
              :class="{ 'bg-blue-50 dark:bg-blue-900/20': !notification.read }"
            >
              <div class="flex items-start space-x-3">
                <!-- Severity Icon -->
                <div class="flex-shrink-0 mt-1">
                  <div
                    class="w-2 h-2 rounded-full"
                    :class="getSeverityColor(notification.severity)"
                  ></div>
                </div>
                
                <!-- Content -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between">
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white truncate">
                      {{ notification.title }}
                    </h4>
                    <button
                      @click="removeNotification(notification.id)"
                      class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                  
                  <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                    {{ notification.message }}
                  </p>
                  
                  <div class="flex items-center justify-between mt-2">
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                      {{ formatTime(notification.created_at) }}
                    </span>
                    
                    <button
                      v-if="!notification.read"
                      @click="markAsRead(notification.id)"
                      class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                    >
                      {{ $t('notifications.markRead') }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="p-4 border-t border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <button
              @click="clearNotifications"
              class="text-sm text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
            >
              {{ $t('notifications.clearAll') }}
            </button>
            
            <button
              @click="testConnection"
              class="text-sm text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
            >
              {{ $t('notifications.testConnection') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Click Outside to Close -->
    <div
      v-if="showNotifications"
      class="fixed inset-0 z-40"
      @click="showNotifications = false"
    ></div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { useWebSocket } from '@/composables/useWebSocket';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const {
  isConnected,
  connectionStatus,
  notifications,
  unreadCount,
  testConnection,
  markAsRead,
  markAllAsRead,
  clearNotifications,
  removeNotification,
  onNotification,
} = useWebSocket();

const showNotifications = ref(false);

const toggleNotifications = () => {
  showNotifications.value = !showNotifications.value;
};

const getConnectionStatusText = () => {
  switch (connectionStatus.value) {
    case 'connected':
      return t('notifications.connected');
    case 'connecting':
      return t('notifications.connecting');
    case 'error':
      return t('notifications.connectionError');
    default:
      return t('notifications.disconnected');
  }
};

const getSeverityColor = (severity: string) => {
  switch (severity) {
    case 'error':
      return 'bg-red-500';
    case 'warning':
      return 'bg-yellow-500';
    case 'success':
      return 'bg-green-500';
    default:
      return 'bg-blue-500';
  }
};

const formatTime = (timestamp: string) => {
  const date = new Date(timestamp);
  const now = new Date();
  const diff = now.getTime() - date.getTime();
  
  if (diff < 60000) { // Less than 1 minute
    return t('notifications.justNow');
  } else if (diff < 3600000) { // Less than 1 hour
    const minutes = Math.floor(diff / 60000);
    return t('notifications.minutesAgo', { count: minutes });
  } else if (diff < 86400000) { // Less than 1 day
    const hours = Math.floor(diff / 3600000);
    return t('notifications.hoursAgo', { count: hours });
  } else {
    return date.toLocaleDateString();
  }
};

// Set up notification listener
let unsubscribeNotifications: (() => void) | null = null;

onMounted(() => {
  unsubscribeNotifications = onNotification((notification) => {
    // Show browser notification if permission granted
    if (Notification.permission === 'granted') {
      new Notification(notification.title, {
        body: notification.message,
        icon: '/favicon.ico',
      });
    }
  });

  // Request notification permission
  if (Notification.permission === 'default') {
    Notification.requestPermission();
  }
});

onUnmounted(() => {
  if (unsubscribeNotifications) {
    unsubscribeNotifications();
  }
});
</script>