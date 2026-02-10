<script setup>
defineProps({
  title: {
    type: String,
    default: ''
  },
  subtitle: {
    type: String,
    default: ''
  },
  noPadding: {
    type: Boolean,
    default: false
  }
})
</script>

<template>
  <div class="bg-white rounded-lg border border-slate-200 shadow-sm">
    <div
      v-if="title || $slots.header"
      class="px-6 py-4 border-b border-slate-200"
    >
      <slot name="header">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-lg font-semibold text-slate-900">{{ title }}</h3>
            <p v-if="subtitle" class="mt-1 text-sm text-slate-500">{{ subtitle }}</p>
          </div>
          <slot name="header-actions" />
        </div>
      </slot>
    </div>

    <div :class="{ 'px-6 py-4': !noPadding }">
      <slot />
    </div>

    <div
      v-if="$slots.footer"
      class="px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-lg"
    >
      <slot name="footer" />
    </div>
  </div>
</template>
