import { computed } from 'vue'
import { useI18n as useVueI18n } from 'vue-i18n'
import type { SupportedLocales } from '@/types/i18n'

export function useI18n() {
  const { locale, t, availableLocales } = useVueI18n()

  const currentLocale = computed(() => locale.value as SupportedLocales)
  
  const isRTL = computed(() => currentLocale.value === 'fa')
  
  const direction = computed(() => isRTL.value ? 'rtl' : 'ltr')
  
  const fontClass = computed(() => isRTL.value ? 'font-vazir' : 'font-inter')

  const switchLanguage = (newLocale: SupportedLocales) => {
    locale.value = newLocale
    // Store preference in localStorage
    localStorage.setItem('preferred-language', newLocale)
    // Update document direction
    document.documentElement.dir = newLocale === 'fa' ? 'rtl' : 'ltr'
    document.documentElement.lang = newLocale
  }

  const initializeLanguage = () => {
    // Get saved preference or default to Persian
    const savedLocale = localStorage.getItem('preferred-language') as SupportedLocales
    const defaultLocale: SupportedLocales = 'fa'
    const initialLocale = savedLocale && availableLocales.includes(savedLocale) 
      ? savedLocale 
      : defaultLocale
    
    switchLanguage(initialLocale)
  }

  const formatNumber = (num: number, usePersianDigits = isRTL.value) => {
    if (usePersianDigits) {
      return num.toString().replace(/\d/g, (digit) => {
        const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹']
        return persianDigits[parseInt(digit)]
      })
    }
    return num.toString()
  }

  const formatCurrency = (amount: number, currency = 'IRR') => {
    const formattedAmount = new Intl.NumberFormat(
      currentLocale.value === 'fa' ? 'fa-IR' : 'en-US',
      {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }
    ).format(amount)
    
    return formattedAmount
  }

  return {
    t,
    currentLocale,
    isRTL,
    direction,
    fontClass,
    switchLanguage,
    initializeLanguage,
    formatNumber,
    formatCurrency,
  }
}