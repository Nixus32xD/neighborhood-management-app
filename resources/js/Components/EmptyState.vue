<script setup>
import { Inbox } from 'lucide-vue-next'

defineProps({
  title: {
    type: String,
    default: 'No data found'
  },
  description: {
    type: String,
    default: ''
  },
  icon: {
    type: [Object, Function],
    default: () => Inbox
  }
})
</script>

<template>
  <div class="flex flex-col items-center justify-center text-center py-8 px-4">
    <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mb-4">
      <component :is="icon" class="w-6 h-6 text-slate-400" />
    </div>
    <h3 class="text-sm font-medium text-slate-900 mb-1">{{ title }}</h3>
    <p v-if="description" class="text-sm text-slate-500 max-w-sm">{{ description }}</p>
    <div v-if="$slots.action" class="mt-4">
      <slot name="action" />
    </div>
  </div>
</template>
