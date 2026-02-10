<script setup>
defineProps({
  title: {
    type: String,
    required: true
  },
  value: {
    type: [String, Number],
    required: true
  },
  subtitle: {
    type: String,
    default: ''
  },
  icon: {
    type: [Object, Function],
    required: true
  },
  trend: {
    type: String,
    default: null,
    validator: (value) => value === null || ['up', 'down', 'neutral'].includes(value)
  },
  trendValue: {
    type: String,
    default: ''
  }
})

const trendClasses = {
  up: 'text-emerald-600',
  down: 'text-red-600',
  neutral: 'text-slate-500'
}
</script>

<template>
  <div class="bg-white rounded-lg border border-slate-200 p-6">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-sm font-medium text-slate-500">{{ title }}</p>
        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ value }}</p>
        <div v-if="subtitle || trendValue" class="mt-1 flex items-center gap-2">
          <span v-if="trendValue" :class="['text-sm font-medium', trendClasses[trend]]">
            {{ trendValue }}
          </span>
          <span v-if="subtitle" class="text-sm text-slate-500">{{ subtitle }}</span>
        </div>
      </div>
      <div
        v-if="icon"
        class="w-10 h-10 bg-emerald-50 rounded-lg flex items-center justify-center"
      >
        <component :is="icon" class="w-5 h-5 text-emerald-600" />
      </div>
    </div>
  </div>
</template>
