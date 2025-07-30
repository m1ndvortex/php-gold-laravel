<template>
  <div class="relative">
    <button
      type="button"
      class="flex items-center gap-x-1 text-sm font-semibold leading-6 text-gray-900 hover:text-primary-600 transition-colors duration-200"
      @click="toggleDropdown"
    >
      <GlobeAltIcon class="h-5 w-5" />
      <span class="hidden sm:block">{{ currentLanguageLabel }}</span>
      <ChevronDownIcon class="h-4 w-4" />
    </button>

    <!-- Dropdown -->
    <div
      v-show="showDropdown"
      :class="[
        'absolute z-10 mt-2 w-40 origin-top rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none',
        isRTL ? 'left-0' : 'right-0'
      ]"
    >
      <button
        v-for="lang in languages"
        :key="lang.code"
        type="button"
        :class="[
          'flex w-full items-center px-4 py-2 text-sm transition-colors duration-200',
          currentLocale === lang.code
            ? 'bg-primary-50 text-primary-700'
            : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900'
        ]"
        @click="handleLanguageChange(lang.code)"
      >
        <span :class="['text-lg', isRTL ? 'ml-3' : 'mr-3']">{{ lang.flag }}</span>
        <div class="flex flex-col items-start">
          <span class="font-medium">{{ lang.name }}</span>
          <span class="text-xs text-gray-500">{{ lang.nativeName }}</span>
        </div>
        <CheckIcon
          v-if="currentLocale === lang.code"
          :class="['h-4 w-4 text-primary-600', isRTL ? 'mr-auto' : 'ml-auto']"
        />
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useI18n } from '@/composables/useI18n'
import type { SupportedLocales } from '@/types/i18n'
import {
  GlobeAltIcon,
  ChevronDownIcon,
  CheckIcon,
} from '@heroicons/vue/24/outline'

const { currentLocale, switchLanguage, isRTL } = useI18n()

const showDropdown = ref(false)

const languages = [
  {
    code: 'fa' as SupportedLocales,
    name: 'فارسی',
    nativeName: 'Persian',
    flag: '🇮🇷'
  },
  {
    code: 'en' as SupportedLocales,
    name: 'English',
    nativeName: 'انگلیسی',
    flag: '🇺🇸'
  }
]

const currentLanguageLabel = computed(() => {
  const current = languages.find(lang => lang.code === currentLocale.value)
  return current ? current.name : 'Language'
})

const toggleDropdown = () => {
  showDropdown.value = !showDropdown.value
}

const handleLanguageChange = (langCode: SupportedLocales) => {
  switchLanguage(langCode)
  showDropdown.value = false
}

const handleClickOutside = (event: Event) => {
  const target = event.target as HTMLElement
  if (!target.closest('.relative')) {
    showDropdown.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>