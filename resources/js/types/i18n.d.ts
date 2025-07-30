export interface LocaleMessages {
  welcome: string
  platform_description: string
  switch_language: string
  current_language: string
  dashboard: string
  invoices: string
  customers: string
  inventory: string
  accounting: string
  settings: string
  navigation: {
    home: string
    profile: string
    logout: string
  }
  common: {
    save: string
    cancel: string
    delete: string
    edit: string
    add: string
    search: string
    loading: string
    error: string
    success: string
  }
}

export type SupportedLocales = 'fa' | 'en'