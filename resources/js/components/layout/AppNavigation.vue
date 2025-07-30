<template>
  <div :class="[
    'fixed inset-y-0 z-50 flex w-64 flex-col transition-transform duration-300',
    isRTL ? 'right-0' : 'left-0'
  ]">
    <!-- Sidebar component -->
    <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-white border-r border-gray-200 px-6 pb-4">
      <!-- Logo -->
      <div class="flex h-16 shrink-0 items-center">
        <img 
          class="h-8 w-auto" 
          src="/images/logo.svg" 
          :alt="t('platform_description')"
        />
        <h1 :class="[
          'text-xl font-bold text-gray-900 transition-all duration-200',
          isRTL ? 'mr-3' : 'ml-3'
        ]">
          {{ t('welcome') }}
        </h1>
      </div>
      
      <!-- Navigation Links -->
      <nav class="flex flex-1 flex-col">
        <ul role="list" class="flex flex-1 flex-col gap-y-7">
          <li>
            <ul role="list" class="-mx-2 space-y-1">
              <li v-for="item in navigation" :key="item.name">
                <router-link
                  :to="item.href"
                  :class="[
                    item.current
                      ? 'nav-link-active'
                      : 'nav-link-inactive',
                    'group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold'
                  ]"
                >
                  <component 
                    :is="item.icon" 
                    :class="[
                      item.current ? 'text-primary-600' : 'text-gray-400 group-hover:text-primary-600',
                      'h-6 w-6 shrink-0'
                    ]" 
                    aria-hidden="true" 
                  />
                  {{ t(item.name) }}
                </router-link>
              </li>
            </ul>
          </li>
        </ul>
      </nav>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from '@/composables/useI18n'
import {
  HomeIcon,
  DocumentTextIcon,
  UsersIcon,
  CubeIcon,
  CalculatorIcon,
  CogIcon,
} from '@heroicons/vue/24/outline'

const { t, isRTL } = useI18n()
const route = useRoute()

const navigation = computed(() => [
  { 
    name: 'dashboard', 
    href: '/', 
    icon: HomeIcon, 
    current: route.path === '/' 
  },
  { 
    name: 'invoices', 
    href: '/invoices', 
    icon: DocumentTextIcon, 
    current: route.path.startsWith('/invoices') 
  },
  { 
    name: 'customers', 
    href: '/customers', 
    icon: UsersIcon, 
    current: route.path.startsWith('/customers') 
  },
  { 
    name: 'inventory', 
    href: '/inventory', 
    icon: CubeIcon, 
    current: route.path.startsWith('/inventory') 
  },
  { 
    name: 'accounting', 
    href: '/accounting', 
    icon: CalculatorIcon, 
    current: route.path.startsWith('/accounting') 
  },
  { 
    name: 'settings', 
    href: '/settings', 
    icon: CogIcon, 
    current: route.path.startsWith('/settings') 
  },
])
</script>