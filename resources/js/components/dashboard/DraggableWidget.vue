<template>
  <div 
    :class="[
      'widget-container',
      isDragging ? 'dragging' : '',
      `col-span-${width} row-span-${height}`
    ]"
    :style="{ 
      gridColumnStart: positionX + 1, 
      gridRowStart: positionY + 1,
      gridColumnEnd: `span ${width}`,
      gridRowEnd: `span ${height}`
    }"
    draggable="true"
    @dragstart="handleDragStart"
    @dragend="handleDragEnd"
    @dragover.prevent
    @drop="handleDrop"
  >
    <div class="widget-header" v-if="showHeader">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-medium text-gray-700">{{ title }}</h3>
        <div class="flex items-center space-x-2">
          <button 
            @click="$emit('configure')"
            class="text-gray-400 hover:text-gray-600"
            :title="t('configure_widget')"
          >
            <CogIcon class="h-4 w-4" />
          </button>
          <button 
            @click="$emit('remove')"
            class="text-gray-400 hover:text-red-600"
            :title="t('remove_widget')"
          >
            <XMarkIcon class="h-4 w-4" />
          </button>
          <div class="drag-handle cursor-move text-gray-400 hover:text-gray-600">
            <Bars3Icon class="h-4 w-4" />
          </div>
        </div>
      </div>
    </div>
    
    <div class="widget-content">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from '@/composables/useI18n'
import { CogIcon, XMarkIcon, Bars3Icon } from '@heroicons/vue/24/outline'

interface Props {
  id: string
  title?: string
  positionX: number
  positionY: number
  width: number
  height: number
  showHeader?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  showHeader: true,
  width: 1,
  height: 1
})

const emit = defineEmits(['drag-start', 'drag-end', 'drop', 'configure', 'remove'])

const { t } = useI18n()
const isDragging = ref(false)

const handleDragStart = (event: DragEvent) => {
  isDragging.value = true
  if (event.dataTransfer) {
    event.dataTransfer.setData('text/plain', props.id)
    event.dataTransfer.effectAllowed = 'move'
  }
  emit('drag-start', { id: props.id, event })
}

const handleDragEnd = (event: DragEvent) => {
  isDragging.value = false
  emit('drag-end', { id: props.id, event })
}

const handleDrop = (event: DragEvent) => {
  event.preventDefault()
  const draggedId = event.dataTransfer?.getData('text/plain')
  if (draggedId && draggedId !== props.id) {
    emit('drop', { 
      draggedId, 
      targetId: props.id,
      event 
    })
  }
}
</script>

<style scoped>
.widget-container {
  @apply bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden;
  transition: all 0.2s ease;
}

.widget-container:hover {
  @apply shadow-md;
}

.widget-container.dragging {
  @apply opacity-50 transform rotate-2;
}

.widget-header {
  @apply px-4 py-3 bg-gray-50 border-b border-gray-200;
}

.widget-content {
  @apply p-0;
}

.drag-handle {
  cursor: grab;
}

.drag-handle:active {
  cursor: grabbing;
}
</style>