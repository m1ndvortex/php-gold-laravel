import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher available globally for Laravel Echo
(window as any).Pusher = Pusher;

interface WebSocketConfig {
  key: string;
  cluster: string;
  host?: string;
  port?: number;
  scheme?: string;
  encrypted?: boolean;
  forceTLS?: boolean;
}

interface NotificationData {
  id: string;
  type: string;
  title: string;
  message: string;
  data?: any;
  severity: 'info' | 'success' | 'warning' | 'error';
  created_at: string;
}

interface DashboardUpdateData {
  type: string;
  kpis?: any;
  alerts?: any;
  sales_data?: any;
  timestamp: string;
}

interface InventoryUpdateData {
  product_id: number;
  product_name: string;
  old_stock: number;
  new_stock: number;
  change_type: string;
  change_amount: number;
  timestamp: string;
}

class WebSocketService {
  private echo: Echo | null = null;
  private config: WebSocketConfig | null = null;
  private isConnected = false;
  private reconnectAttempts = 0;
  private maxReconnectAttempts = 5;
  private reconnectDelay = 1000;

  // Event listeners
  private dashboardUpdateListeners: ((data: DashboardUpdateData) => void)[] = [];
  private inventoryUpdateListeners: ((data: InventoryUpdateData) => void)[] = [];
  private notificationListeners: ((data: NotificationData) => void)[] = [];

  /**
   * Initialize WebSocket connection
   */
  async initialize(): Promise<void> {
    try {
      // Get WebSocket configuration from API
      const response = await fetch('/api/websocket/connection-info', {
        headers: {
          'Authorization': `Bearer ${this.getAuthToken()}`,
          'Accept': 'application/json',
        },
      });

      if (!response.ok) {
        throw new Error('Failed to get WebSocket configuration');
      }

      const { data } = await response.json();
      this.config = data.pusher_config;

      // Initialize Laravel Echo
      this.echo = new Echo({
        broadcaster: 'pusher',
        key: this.config.key,
        cluster: this.config.cluster,
        host: this.config.host,
        port: this.config.port,
        scheme: this.config.scheme,
        encrypted: true,
        forceTLS: this.config.scheme === 'https',
        auth: {
          headers: {
            Authorization: `Bearer ${this.getAuthToken()}`,
          },
        },
      });

      // Set up connection event listeners
      this.setupConnectionListeners();

      // Subscribe to channels
      this.subscribeToChannels(data);

      this.isConnected = true;
      this.reconnectAttempts = 0;

      console.log('WebSocket connection initialized successfully');
    } catch (error) {
      console.error('Failed to initialize WebSocket connection:', error);
      this.scheduleReconnect();
    }
  }

  /**
   * Set up connection event listeners
   */
  private setupConnectionListeners(): void {
    if (!this.echo) return;

    // Connection established
    this.echo.connector.pusher.connection.bind('connected', () => {
      console.log('WebSocket connected');
      this.isConnected = true;
      this.reconnectAttempts = 0;
    });

    // Connection lost
    this.echo.connector.pusher.connection.bind('disconnected', () => {
      console.log('WebSocket disconnected');
      this.isConnected = false;
      this.scheduleReconnect();
    });

    // Connection error
    this.echo.connector.pusher.connection.bind('error', (error: any) => {
      console.error('WebSocket connection error:', error);
      this.isConnected = false;
      this.scheduleReconnect();
    });
  }

  /**
   * Subscribe to WebSocket channels
   */
  private subscribeToChannels(connectionData: any): void {
    if (!this.echo) return;

    const { user_id, tenant_id } = connectionData;

    // Subscribe to tenant dashboard updates
    this.echo.private(`dashboard.${tenant_id}`)
      .listen('.dashboard.updated', (data: { data: DashboardUpdateData }) => {
        this.dashboardUpdateListeners.forEach(listener => listener(data.data));
      });

    // Subscribe to tenant inventory updates
    this.echo.private(`inventory.${tenant_id}`)
      .listen('.inventory.updated', (data: InventoryUpdateData) => {
        this.inventoryUpdateListeners.forEach(listener => listener(data));
      });

    // Subscribe to tenant notifications
    this.echo.private(`notifications.${tenant_id}`)
      .listen('.notification.sent', (data: { notification: NotificationData }) => {
        this.notificationListeners.forEach(listener => listener(data.notification));
      });

    // Subscribe to user-specific notifications
    this.echo.private(`user.${user_id}`)
      .listen('.notification.sent', (data: { notification: NotificationData }) => {
        this.notificationListeners.forEach(listener => listener(data.notification));
      });

    console.log('Subscribed to WebSocket channels');
  }

  /**
   * Schedule reconnection attempt
   */
  private scheduleReconnect(): void {
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      console.error('Max reconnection attempts reached');
      return;
    }

    this.reconnectAttempts++;
    const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1);

    console.log(`Scheduling reconnection attempt ${this.reconnectAttempts} in ${delay}ms`);

    setTimeout(() => {
      this.initialize();
    }, delay);
  }

  /**
   * Get authentication token
   */
  private getAuthToken(): string {
    return localStorage.getItem('auth_token') || '';
  }

  /**
   * Add dashboard update listener
   */
  onDashboardUpdate(listener: (data: DashboardUpdateData) => void): () => void {
    this.dashboardUpdateListeners.push(listener);
    
    // Return unsubscribe function
    return () => {
      const index = this.dashboardUpdateListeners.indexOf(listener);
      if (index > -1) {
        this.dashboardUpdateListeners.splice(index, 1);
      }
    };
  }

  /**
   * Add inventory update listener
   */
  onInventoryUpdate(listener: (data: InventoryUpdateData) => void): () => void {
    this.inventoryUpdateListeners.push(listener);
    
    // Return unsubscribe function
    return () => {
      const index = this.inventoryUpdateListeners.indexOf(listener);
      if (index > -1) {
        this.inventoryUpdateListeners.splice(index, 1);
      }
    };
  }

  /**
   * Add notification listener
   */
  onNotification(listener: (data: NotificationData) => void): () => void {
    this.notificationListeners.push(listener);
    
    // Return unsubscribe function
    return () => {
      const index = this.notificationListeners.indexOf(listener);
      if (index > -1) {
        this.notificationListeners.splice(index, 1);
      }
    };
  }

  /**
   * Test WebSocket connection
   */
  async testConnection(): Promise<boolean> {
    try {
      const response = await fetch('/api/websocket/test-connection', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${this.getAuthToken()}`,
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      });

      return response.ok;
    } catch (error) {
      console.error('WebSocket connection test failed:', error);
      return false;
    }
  }

  /**
   * Send custom notification
   */
  async sendNotification(type: string, title: string, message: string, data?: any, userId?: number): Promise<boolean> {
    try {
      const response = await fetch('/api/websocket/send-notification', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${this.getAuthToken()}`,
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          type,
          title,
          message,
          data,
          user_id: userId,
        }),
      });

      return response.ok;
    } catch (error) {
      console.error('Failed to send notification:', error);
      return false;
    }
  }

  /**
   * Get connection status
   */
  isWebSocketConnected(): boolean {
    return this.isConnected;
  }

  /**
   * Disconnect WebSocket
   */
  disconnect(): void {
    if (this.echo) {
      this.echo.disconnect();
      this.echo = null;
    }
    this.isConnected = false;
    this.reconnectAttempts = 0;
  }
}

// Create singleton instance
export const webSocketService = new WebSocketService();

// Export types
export type {
  NotificationData,
  DashboardUpdateData,
  InventoryUpdateData,
};