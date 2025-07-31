import { ref, onMounted, onUnmounted, computed } from 'vue';
import { webSocketService, type NotificationData, type DashboardUpdateData, type InventoryUpdateData } from '@/services/websocket';

export function useWebSocket() {
  const isConnected = ref(false);
  const connectionError = ref<string | null>(null);
  const notifications = ref<NotificationData[]>([]);
  const unreadCount = ref(0);

  // Connection status
  const connectionStatus = computed(() => {
    if (isConnected.value) return 'connected';
    if (connectionError.value) return 'error';
    return 'connecting';
  });

  /**
   * Initialize WebSocket connection
   */
  const connect = async () => {
    try {
      await webSocketService.initialize();
      isConnected.value = true;
      connectionError.value = null;
    } catch (error) {
      connectionError.value = error instanceof Error ? error.message : 'Connection failed';
      isConnected.value = false;
    }
  };

  /**
   * Disconnect WebSocket
   */
  const disconnect = () => {
    webSocketService.disconnect();
    isConnected.value = false;
    connectionError.value = null;
  };

  /**
   * Test WebSocket connection
   */
  const testConnection = async (): Promise<boolean> => {
    return await webSocketService.testConnection();
  };

  /**
   * Send notification
   */
  const sendNotification = async (
    type: string,
    title: string,
    message: string,
    data?: any,
    userId?: number
  ): Promise<boolean> => {
    return await webSocketService.sendNotification(type, title, message, data, userId);
  };

  /**
   * Add notification to local list
   */
  const addNotification = (notification: NotificationData) => {
    notifications.value.unshift(notification);
    unreadCount.value++;
    
    // Keep only last 50 notifications
    if (notifications.value.length > 50) {
      notifications.value = notifications.value.slice(0, 50);
    }
  };

  /**
   * Mark notification as read
   */
  const markAsRead = (notificationId: string) => {
    const notification = notifications.value.find(n => n.id === notificationId);
    if (notification && !notification.read) {
      notification.read = true;
      unreadCount.value = Math.max(0, unreadCount.value - 1);
    }
  };

  /**
   * Mark all notifications as read
   */
  const markAllAsRead = () => {
    notifications.value.forEach(notification => {
      notification.read = true;
    });
    unreadCount.value = 0;
  };

  /**
   * Clear all notifications
   */
  const clearNotifications = () => {
    notifications.value = [];
    unreadCount.value = 0;
  };

  /**
   * Remove specific notification
   */
  const removeNotification = (notificationId: string) => {
    const index = notifications.value.findIndex(n => n.id === notificationId);
    if (index > -1) {
      const notification = notifications.value[index];
      if (!notification.read) {
        unreadCount.value = Math.max(0, unreadCount.value - 1);
      }
      notifications.value.splice(index, 1);
    }
  };

  // Auto-connect on mount
  onMounted(() => {
    connect();
  });

  // Disconnect on unmount
  onUnmounted(() => {
    disconnect();
  });

  return {
    // State
    isConnected: computed(() => isConnected.value),
    connectionError: computed(() => connectionError.value),
    connectionStatus,
    notifications: computed(() => notifications.value),
    unreadCount: computed(() => unreadCount.value),

    // Methods
    connect,
    disconnect,
    testConnection,
    sendNotification,
    addNotification,
    markAsRead,
    markAllAsRead,
    clearNotifications,
    removeNotification,

    // WebSocket service methods
    onDashboardUpdate: webSocketService.onDashboardUpdate.bind(webSocketService),
    onInventoryUpdate: webSocketService.onInventoryUpdate.bind(webSocketService),
    onNotification: (listener: (data: NotificationData) => void) => {
      const unsubscribe = webSocketService.onNotification((data) => {
        addNotification(data);
        listener(data);
      });
      return unsubscribe;
    },
  };
}

// Dashboard-specific composable
export function useDashboardWebSocket() {
  const { onDashboardUpdate, onNotification } = useWebSocket();
  
  const dashboardData = ref<any>(null);
  const lastUpdate = ref<Date | null>(null);

  const subscribeToDashboardUpdates = () => {
    return onDashboardUpdate((data: DashboardUpdateData) => {
      if (data.type === 'kpi_update' && data.kpis) {
        dashboardData.value = { ...dashboardData.value, kpis: data.kpis };
      } else if (data.type === 'alert_update' && data.alerts) {
        dashboardData.value = { ...dashboardData.value, alerts: data.alerts };
      } else if (data.type === 'sales_trend_update' && data.sales_data) {
        dashboardData.value = { ...dashboardData.value, salesTrend: data.sales_data };
      }
      
      lastUpdate.value = new Date();
    });
  };

  return {
    dashboardData: computed(() => dashboardData.value),
    lastUpdate: computed(() => lastUpdate.value),
    subscribeToDashboardUpdates,
    onNotification,
  };
}

// Inventory-specific composable
export function useInventoryWebSocket() {
  const { onInventoryUpdate, onNotification } = useWebSocket();
  
  const inventoryUpdates = ref<InventoryUpdateData[]>([]);
  const lastInventoryUpdate = ref<Date | null>(null);

  const subscribeToInventoryUpdates = () => {
    return onInventoryUpdate((data: InventoryUpdateData) => {
      inventoryUpdates.value.unshift(data);
      
      // Keep only last 20 updates
      if (inventoryUpdates.value.length > 20) {
        inventoryUpdates.value = inventoryUpdates.value.slice(0, 20);
      }
      
      lastInventoryUpdate.value = new Date();
    });
  };

  const getProductUpdates = (productId: number) => {
    return computed(() => 
      inventoryUpdates.value.filter(update => update.product_id === productId)
    );
  };

  return {
    inventoryUpdates: computed(() => inventoryUpdates.value),
    lastInventoryUpdate: computed(() => lastInventoryUpdate.value),
    subscribeToInventoryUpdates,
    getProductUpdates,
    onNotification,
  };
}