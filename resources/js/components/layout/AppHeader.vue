<template>
  <div class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 bg-white px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8">
    <!-- Mobile menu button -->
    <button 
      type="button" 
      class="-m-2.5 p-2.5 text-gray-700 lg:hidden"
      @click="toggleMobileMenu"
    >
      <span class="sr-only">{{ t('navigation.menu') }}</span>
      <Bars3Icon class="h-6 w-6" aria-hidden="true" />
    </button>

    <!-- Separator -->
    <div class="h-6 w-px bg-gray-200 lg:hidden" aria-hidden="true" />

    <div class="flex flex-1 gap-x-4 self-stretch lg:gap-x-6">
      <!-- Search -->
      <form class="relative flex flex-1" action="#" method="GET">
        <label for="search-field" class="sr-only">{{ t('common.search') }}</label>
        <MagnifyingGlassIcon 
          :class="[
            'pointer-events-none absolute inset-y-0 h-full w-5 text-gray-400',
            isRTL ? 'right-0 pr-3' : 'left-0 pl-3'
          ]" 
          aria-hidden="true" 
        />
        <input
          id="search-field"
          :class="[
            'block h-full w-full border-0 py-0 text-gray-900 placeholder:text-gray-400 focus:ring-0 sm:text-sm',
            isRTL ? 'pr-11 pl-8 text-right' : 'pl-11 pr-8 text-left'
          ]"
          :placeholder="t('common.search')"
          type="search"
          name="search"
        />
      </form>
      
      <div class="flex items-center gap-x-4 lg:gap-x-6">
        <!-- Language Switcher -->
        <LanguageSwitcher />
        
        <!-- Notifications button -->
        <button type="button" class="-m-2.5 p-2.5 text-gray-400 hover:text-gray-500">
          <span class="sr-only">{{ t('navigation.notifications') }}</span>
          <BellIcon class="h-6 w-6" aria-hidden="true" />
        </button>

        <!-- Separator -->
        <div class="hidden lg:block lg:h-6 lg:w-px lg:bg-gray-200" aria-hidden="true" />

        <!-- Profile dropdown -->
        <div class="relative">
          <button 
            type="button" 
            class="-m-1.5 flex items-center p-1.5"
            @click="toggleProfileMenu"
          >
            <span class="sr-only">{{ t('navigation.profile') }}</span>
            <img
              class="h-8 w-8 rounded-full bg-gray-50"
              src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80"
              alt=""
            />
            <span :class="[
              'hidden text-sm font-semibold leading-6 text-gray-900 lg:flex lg:items-center',
              isRTL ? 'mr-4' : 'ml-4'
            ]">
              <span>کاربر نمونه</span>
              <ChevronDownIcon class="ml-2 h-5 w-5 text-gray-400" aria-hidden="true" />
            </span>
          </button>
          
          <!-- Profile dropdown menu -->
          <div 
            v-show="showProfileMenu"
            :class="[
              'absolute z-10 mt-2.5 w-32 origin-top-right rounded-md bg-white py-2 shadow-lg ring-1 ring-gray-900/5 focus:outline-none',
              isRTL ? 'left-0' : 'right-0'
            ]"
          >
            <a 
              href="#" 
              class="block px-3 py-1 text-sm leading-6 text-gray-900 hover:bg-gray-50"
            >
              {{ t('navigation.profile') }}
            </a>
            <a 
              href="#" 
              class="block px-3 py-1 text-sm leading-6 text-gray-900 hover:bg-gray-50"
            >
              {{ t('navigation.logout') }}
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from '@/composables/useI18n'
import {
  Bars3Icon,
  BellIcon,
  ChevronDownIcon,
  MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline'
import LanguageSwitcher from '../ui/LanguageSwitcher.vue'

const { t, isRTL } = useI18n()

const showProfileMenu = ref(false)
const showMobileMenu = ref(false)

const toggleProfileMenu = () => {
  showProfileMenu.value = !showProfileMenu.value
}

const toggleMobileMenu = () => {
  showMobileMenu.value = !showMobileMenu.value
}
</script>