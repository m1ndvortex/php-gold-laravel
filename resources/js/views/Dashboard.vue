<template>
  <div>
    <!-- Page Header -->
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-gray-900">
        {{ t('dashboard') }}
      </h1>
      <p class="mt-2 text-sm text-gray-600">
        {{ t('platform_description') }}
      </p>
    </div>

    <!-- Welcome Card -->
    <div class="card mb-8">
      <div class="text-center">
        <h2 class="text-3xl font-bold text-gray-900 mb-4">
          {{ t('welcome') }}
        </h2>
        <p class="text-lg text-gray-600 mb-6">
          {{ t('platform_description') }}
        </p>
        
        <div class="flex justify-center items-center gap-4">
          <span class="text-sm text-gray-500">
            {{ t('current_language') }}: {{ currentLanguageLabel }}
          </span>
        </div>
      </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
      <div v-for="stat in stats" :key="stat.name" class="card">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <component 
              :is="stat.icon" 
              class="h-8 w-8 text-primary-600" 
              aria-hidden="true" 
            />
          </div>
          <div :class="['flex-1', isRTL ? 'mr-4' : 'ml-4']">
            <div class="text-sm font-medium text-gray-500">
              {{ t(stat.name) }}
            </div>
            <div class="text-2xl font-bold text-gray-900">
              {{ formatNumber(stat.value) }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Navigation Grid -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <router-link
        v-for="item in navigationItems"
        :key="item.name"
        :to="item.href"
        class="card hover:shadow-md transition-shadow duration-200 cursor-pointer group"
      >
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <component 
              :is="item.icon" 
              class="h-8 w-8 text-gray-400 group-hover:text-primary-600 transition-colors duration-200" 
              aria-hidden="true" 
            />
          </div>
          <div :class="['flex-1', isRTL ? 'mr-4' : 'ml-4']">
            <div class="text-lg font-medium text-gray-900 group-hover:text-primary-600 transition-colors duration-200">
              {{ t(item.name) }}
            </div>
            <div class="text-sm text-gray-500">
              {{ t(item.description) }}
            </div>
          </div>
        </div>
      </router-link>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from '@/composables/useI18n'
import {
  DocumentTextIcon,
  UsersIcon,
  CubeIcon,
  CalculatorIcon,
  CurrencyDollarIcon,
  ChartBarIcon,
  UserGroupIcon,
  ShoppingBagIcon,
} from '@heroicons/vue/24/outline'

const { t, isRTL, currentLocale, formatNumber } = useI18n()

const currentLanguageLabel = computed(() => {
  return currentLocale.value === 'fa' ? 'فارسی' : 'English'
})

const stats = [
  {
    name: 'invoices',
    value: 142,
    icon: DocumentTextIcon,
  },
  {
    name: 'customers',
    value: 89,
    icon: UsersIcon,
  },
  {
    name: 'inventory',
    value: 1247,
    icon: CubeIcon,
  },
  {
    name: 'accounting',
    value: 25,
    icon: CalculatorIcon,
  },
]

const navigationItems = [
  {
    name: 'invoices',
    description: 'invoices',
    href: '/invoices',
    icon: DocumentTextIcon,
  },
  {
    name: 'customers',
    description: 'customers',
    href: '/customers',
    icon: UsersIcon,
  },
  {
    name: 'inventory',
    description: 'inventory',
    href: '/inventory',
    icon: CubeIcon,
  },
  {
    name: 'accounting',
    description: 'accounting',
    href: '/accounting',
    icon: CalculatorIcon,
  },
]
</script>