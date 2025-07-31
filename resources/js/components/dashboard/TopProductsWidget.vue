<template>
  <div class="card p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-medium text-gray-900">{{ t('top_products') }}</h3>
      <button 
        @click="$emit('view-all')"
        class="text-sm text-primary-600 hover:text-primary-500 font-medium"
      >
        {{ t('view_all') }}
      </button>
    </div>

    <div v-if="products.length === 0" class="text-center py-8">
      <CubeIcon class="mx-auto h-12 w-12 text-gray-400" />
      <p class="mt-2 text-sm text-gray-500">{{ t('no_sales_data') }}</p>
    </div>

    <div v-else class="space-y-4">
      <div 
        v-for="(product, index) in products" 
        :key="product.sku"
        class="flex items-center justify-between"
      >
        <div class="flex items-center flex-1 min-w-0">
          <div class="flex-shrink-0 w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center">
            <span class="text-sm font-medium text-primary-600">{{ index + 1 }}</span>
          </div>
          <div :class="['flex-1 min-w-0', isRTL ? 'mr-3' : 'ml-3']">
            <p class="text-sm font-medium text-gray-900 truncate">{{ product.name }}</p>
            <p class="text-xs text-gray-500">{{ t('sku') }}: {{ product.sku }}</p>
          </div>
        </div>
        <div class="text-right">
          <p class="text-sm font-medium text-gray-900">{{ formatCurrency(product.total_revenue) }}</p>
          <p class="text-xs text-gray-500">{{ formatNumber(product.total_sold) }} {{ t('sold') }}</p>
        </div>
      </div>
    </div>

    <div v-if="products.length > 0" class="mt-6 pt-4 border-t border-gray-200">
      <div class="flex justify-between text-sm">
        <span class="text-gray-500">{{ t('total_revenue') }}</span>
        <span class="font-medium text-gray-900">{{ formatCurrency(totalRevenue) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from '@/composables/useI18n'
import { CubeIcon } from '@heroicons/vue/24/outline'

interface Product {
  name: string
  sku: string
  total_sold: number
  total_revenue: number
}

interface Props {
  products: Product[]
}

const props = defineProps<Props>()
const emit = defineEmits(['view-all'])

const { t, formatCurrency, formatNumber, isRTL } = useI18n()

const totalRevenue = computed(() => {
  return props.products.reduce((sum, product) => sum + Number(product.total_revenue), 0)
})
</script>