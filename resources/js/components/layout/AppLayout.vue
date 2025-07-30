<template>
  <div 
    :dir="direction" 
    :class="[fontClass, 'min-h-screen bg-gray-50']"
  >
    <!-- Navigation -->
    <AppNavigation />
    
    <!-- Main Content -->
    <div :class="[
      'transition-all duration-300',
      isRTL ? 'lg:mr-64' : 'lg:ml-64'
    ]">
      <!-- Top Header -->
      <AppHeader />
      
      <!-- Page Content -->
      <main class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from '@/composables/useI18n'
import AppNavigation from './AppNavigation.vue'
import AppHeader from './AppHeader.vue'

const { direction, fontClass, isRTL, initializeLanguage } = useI18n()

onMounted(() => {
  initializeLanguage()
})
</script>