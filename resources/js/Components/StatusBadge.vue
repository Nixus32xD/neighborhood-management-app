<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: {
    type: String,
    required: true
  },
  variant: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'success', 'warning', 'danger', 'info'].includes(value)
  }
})

const variantClasses = {
  default: 'bg-slate-100 text-slate-700',
  success: 'bg-emerald-100 text-emerald-700',
  warning: 'bg-amber-100 text-amber-700',
  danger: 'bg-red-100 text-red-700',
  info: 'bg-blue-100 text-blue-700'
}

const badgeClass = computed(() => [
  'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
  variantClasses[props.variant]
])
</script>

<template>
  <span v-if="status == 'paid'" :class="badgeClass">
    Pagado
  </span>
  <span v-if="status == 'pending'" :class="badgeClass">
    Pendiente
  </span>
  <span v-if="status == 'overdue'" :class="badgeClass">
    Atrasado
  </span>
  <span v-if="status == 'Paid'" :class="badgeClass">
    Pagado
  </span>
  <span v-if="status == 'Pending'" :class="badgeClass">
    Pendiente
  </span>
  <span v-if="status == 'Overdue'" :class="badgeClass">
    Atrasado
  </span>
  <span v-if="status == 'Active'" :class="badgeClass">
    Activo
  </span>
  <span v-if="status == 'Inactive'" :class="badgeClass">
    Inactivo
  </span>
  <span v-if="status == 'High Value'" :class="badgeClass">
    Alto valor
  </span>
</template>
