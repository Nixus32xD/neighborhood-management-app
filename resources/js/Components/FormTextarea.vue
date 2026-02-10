<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  label: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: ''
  },
  rows: {
    type: Number,
    default: 3
  },
  error: {
    type: String,
    default: ''
  },
  required: {
    type: Boolean,
    default: false
  },
  disabled: {
    type: Boolean,
    default: false
  },
  id: {
    type: String,
    default: () => `textarea-${Math.random().toString(36).substr(2, 9)}`
  }
})

const emit = defineEmits(['update:modelValue'])

const textareaClasses = computed(() => [
  'w-full px-3 py-2 border rounded-lg text-sm transition-colors resize-none',
  'focus:outline-none focus:ring-2 focus:ring-offset-0',
  props.error
    ? 'border-red-300 focus:border-red-500 focus:ring-red-200'
    : 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-200',
  props.disabled ? 'bg-slate-100 cursor-not-allowed' : 'bg-white'
])
</script>

<template>
  <div class="space-y-1.5">
    <label
      v-if="label"
      :for="id"
      class="block text-sm font-medium text-slate-700"
    >
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <textarea
      :id="id"
      :value="modelValue"
      :placeholder="placeholder"
      :rows="rows"
      :required="required"
      :disabled="disabled"
      :class="textareaClasses"
      @input="emit('update:modelValue', $event.target.value)"
    />

    <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
  </div>
</template>
