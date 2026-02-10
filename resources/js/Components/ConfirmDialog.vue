<script setup>
import Modal from './Modal.vue'
import Button from './Button.vue'
import { AlertTriangle } from 'lucide-vue-next'

defineProps({
  show: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: 'Confirmar Accion'
  },
  message: {
    type: String,
    default: '¿Estás seguro que deseas continuar?'
  },
  confirmText: {
    type: String,
    default: 'Confirmar'
  },
  cancelText: {
    type: String,
    default: 'Cancelar'
  },
  variant: {
    type: String,
    default: 'danger',
    validator: (value) => ['primary', 'danger'].includes(value)
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['confirm', 'cancel', 'close'])
</script>

<template>
  <Modal
    :show="show"
    :title="title"
    max-width="sm"
    @close="emit('close')"
  >
    <div class="flex gap-4">
      <div class="flex-shrink-0">
        <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
          <AlertTriangle class="w-5 h-5 text-red-600" />
        </div>
      </div>
      <div>
        <p class="text-sm text-slate-600">{{ message }}</p>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-3">
        <Button
          variant="secondary"
          :disabled="loading"
          @click="emit('cancel')"
        >
          {{ cancelText }}
        </Button>
        <Button
          :variant="variant"
          :loading="loading"
          @click="emit('confirm')"
        >
          {{ confirmText }}
        </Button>
      </div>
    </template>
  </Modal>
</template>
