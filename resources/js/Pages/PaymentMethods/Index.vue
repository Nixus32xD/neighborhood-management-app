<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import Card from '@/Components/Card.vue'
import DataTable from '@/Components/DataTable.vue'
import Button from '@/Components/Button.vue'
import Modal from '@/Components/Modal.vue'
import FormInput from '@/Components/FormInput.vue'
import FormSelect from '@/Components/FormSelect.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import {
    Pencil,
    CreditCard,
    Banknote,
    Building,
    Copy,
    Check
} from 'lucide-vue-next'

const props = defineProps({
    paymentMethods: {
        type: Array,
        default: () => []
    }
})

const columns = [
    { key: 'uf_number', label: 'UF' },
    { key: 'owner', label: 'Propietario' },
    { key: 'preferred_method', label: 'Metodo preferido' },
    { key: 'bank_name', label: 'Banco' },
    { key: 'cbu', label: 'CBU / Alias' }
]

const showEditModal = ref(false)
const selectedOwner = ref(null)
const copiedField = ref(null)

const form = useForm({
    preferred_method: '',
    bank_name: '',
    account_holder: '',
    cbu: '',
    alias: '',
    custom_method: ''
})

const methodOptions = [
    { value: 'Bank Transfer', label: 'Transferencia bancaria' },
    { value: 'Cash', label: 'Efectivo' },
    { value: 'Other', label: 'Otro (personalizado)' }
]

const bankOptions = [
    { value: 'Banco Nacion', label: 'Banco Nacion' },
    { value: 'Banco Galicia', label: 'Banco Galicia' },
    { value: 'Banco Santander', label: 'Banco Santander' },
    { value: 'Banco BBVA', label: 'Banco BBVA' },
    { value: 'Banco Macro', label: 'Banco Macro' },
    { value: 'Banco Ciudad', label: 'Banco Ciudad' },
    { value: 'Otro', label: 'Otro banco' }
]

const openEditModal = (owner) => {
    selectedOwner.value = owner
    form.preferred_method = owner.preferred_method || 'Cash'
    form.bank_name = owner.bank_name || ''
    form.account_holder = owner.account_holder || ''
    form.cbu = owner.cbu || ''
    form.alias = owner.alias || ''
    form.custom_method = owner.custom_method || ''
    form.clearErrors()
    showEditModal.value = true
}

const submitEdit = () => {
    if (!selectedOwner.value) return

    form.put(route('payment-methods.update', selectedOwner.value.id), {
        onSuccess: () => {
            showEditModal.value = false
            form.reset()
            selectedOwner.value = null
        }
    })
}

const getMethodIcon = (method) => {
    switch (method) {
        case 'Bank Transfer':
            return Building
        case 'Cash':
            return Banknote
        default:
            return CreditCard
    }
}

const getMethodVariant = (method) => {
    switch (method) {
        case 'Bank Transfer':
            return 'info'
        case 'Cash':
            return 'success'
        default:
            return 'default'
    }
}

const copyToClipboard = async (text, field) => {
    try {
        await navigator.clipboard.writeText(text)
        copiedField.value = field
        setTimeout(() => {
            copiedField.value = null
        }, 2000)
    } catch (err) {
        console.error('Failed to copy:', err)
    }
}

const formatCBU = (cbu) => {
    if (!cbu) return '-'
    return cbu.replace(/(.{4})/g, '$1 ').trim()
}
</script>

<template>
    <DashboardLayout title="Metodos de pago">
        <Card title="Metodos de pago por propietario" subtitle="Administra como quiere pagar cada unidad funcional.">
            <template #default>
                <DataTable :columns="columns" :data="paymentMethods" empty-title="No hay metodos de pago cargados"
                    empty-description="Cuando existan propietarios podras configurar su metodo preferido.">
                    <template #cell-preferred_method="{ row, value }">
                        <div class="flex items-center gap-2">
                            <component :is="getMethodIcon(value)" class="w-4 h-4 text-slate-400" />
                            <StatusBadge :status="value || 'Sin definir'" :variant="getMethodVariant(value)" />
                            <span v-if="row.custom_method" class="text-sm text-slate-500">({{ row.custom_method }})</span>
                        </div>
                    </template>

                    <template #cell-bank_name="{ value }">
                        <span v-if="value">{{ value }}</span>
                        <span v-else class="text-slate-400">-</span>
                    </template>

                    <template #cell-cbu="{ row }">
                        <div v-if="row.cbu || row.alias" class="space-y-1">
                            <div v-if="row.alias" class="flex items-center gap-2">
                                <span class="text-sm font-medium text-emerald-600">{{ row.alias }}</span>
                                <button class="p-1 hover:bg-slate-100 rounded transition-colors" title="Copiar alias"
                                    @click.stop="copyToClipboard(row.alias, `alias-${row.id}`)">
                                    <Check v-if="copiedField === `alias-${row.id}`" class="w-3.5 h-3.5 text-emerald-500" />
                                    <Copy v-else class="w-3.5 h-3.5 text-slate-400" />
                                </button>
                            </div>
                            <div v-if="row.cbu" class="flex items-center gap-2">
                                <span class="text-xs text-slate-500 font-mono">{{ formatCBU(row.cbu) }}</span>
                                <button class="p-1 hover:bg-slate-100 rounded transition-colors" title="Copiar CBU"
                                    @click.stop="copyToClipboard(row.cbu, `cbu-${row.id}`)">
                                    <Check v-if="copiedField === `cbu-${row.id}`" class="w-3.5 h-3.5 text-emerald-500" />
                                    <Copy v-else class="w-3.5 h-3.5 text-slate-400" />
                                </button>
                            </div>
                        </div>
                        <span v-else class="text-slate-400">-</span>
                    </template>

                    <template #actions="{ row }">
                        <button
                            class="p-1.5 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors"
                            title="Editar metodo de pago" @click="openEditModal(row)">
                            <Pencil class="w-4 h-4" />
                        </button>
                    </template>
                </DataTable>
            </template>
        </Card>

        <Modal :show="showEditModal" :title="`Editar metodo - ${selectedOwner?.uf_number || ''}`" max-width="lg"
            @close="showEditModal = false">
            <div v-if="selectedOwner" class="space-y-6">
                <div class="bg-slate-50 rounded-lg p-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-slate-500">Unidad</p>
                            <p class="font-medium text-slate-900">{{ selectedOwner.uf_number }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">Propietario</p>
                            <p class="font-medium text-slate-900">{{ selectedOwner.owner }}</p>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="submitEdit" class="space-y-4">
                    <FormSelect v-model="form.preferred_method" label="Metodo preferido"
                        :options="methodOptions" :error="form.errors.preferred_method" required />

                    <FormInput v-if="form.preferred_method === 'Other'" v-model="form.custom_method"
                        label="Metodo personalizado" placeholder="Ej: MercadoPago"
                        :error="form.errors.custom_method" />

                    <div v-if="form.preferred_method === 'Bank Transfer'" class="space-y-4 border-t border-slate-200 pt-4">
                        <h4 class="font-medium text-slate-900">Datos de transferencia</h4>

                        <FormSelect v-model="form.bank_name" label="Banco" :options="bankOptions"
                            :error="form.errors.bank_name" />
                        <FormInput v-model="form.account_holder" label="Titular de la cuenta"
                            :error="form.errors.account_holder" />
                        <FormInput v-model="form.cbu" label="CBU" :error="form.errors.cbu" />
                        <FormInput v-model="form.alias" label="Alias" :error="form.errors.alias" />
                    </div>
                </form>
            </div>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button variant="secondary" @click="showEditModal = false">Cancelar</Button>
                    <Button :loading="form.processing" @click="submitEdit">Guardar cambios</Button>
                </div>
            </template>
        </Modal>
    </DashboardLayout>
</template>

