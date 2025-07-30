<template>
  <div class="min-h-screen bg-gray-50">
    <div class="container mx-auto px-4 py-8">
      <div class="text-center">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">
          {{ $t('welcome') }}
        </h1>
        <p class="text-lg text-gray-600 mb-8">
          {{ $t('platform_description') }}
        </p>
        
        <div class="flex justify-center space-x-4 rtl:space-x-reverse">
          <button 
            @click="toggleLanguage" 
            class="btn-primary"
          >
            {{ $t('switch_language') }}
          </button>
        </div>
        
        <div class="mt-8 text-sm text-gray-500">
          {{ $t('current_language') }}: {{ $i18n.locale === 'fa' ? 'فارسی' : 'English' }}
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Dashboard',
  methods: {
    toggleLanguage() {
      this.$i18n.locale = this.$i18n.locale === 'fa' ? 'en' : 'fa';
      
      // Update document direction
      document.documentElement.dir = this.$i18n.locale === 'fa' ? 'rtl' : 'ltr';
    }
  }
}
</script>