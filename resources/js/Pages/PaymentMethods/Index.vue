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
        default: () => [
            {
                id: 1,
                uf_number: 'UF-101',
                owner: 'Juan Pérez',
                preferred_method: 'Bank Transfer',
                bank_name: 'Banco Nación',
                account_holder: 'Juan Carlos Pérez',
                cbu: '0110012345678901234567',
                alias: 'JUAN.PEREZ.BARRIO'
            },
            {
                id: 2,
                uf_number: 'UF-205',
                owner: 'María García',
                preferred_method: 'Bank Transfer',
                bank_name: 'Banco Galicia',
                account_holder: 'María Elena García',
                cbu: '0070012345678901234567',
                alias: 'MARIA.GARCIA.CC1'
            },
            {
                id: 3,
                uf_number: 'UF-312',
                owner: 'Carlos López',
                preferred_method: 'Cash',
                bank_name: null,
                account_holder: null,
                cbu: null,
                alias: null
            },
            {
                id: 4,
                uf_number: 'UF-108',
                owner: 'Roberto Sánchez',
                preferred_method: 'Bank Transfer',
                bank_name: 'Banco Santander',
                account_holder: 'Roberto Sánchez',
                cbu: '0720012345678901234567',
                alias: 'ROBERTO.SANCHEZ'
            },
            {
                id: 5,
                uf_number: 'UF-215',
                owner: 'Ana Martínez',
                preferred_method: 'Other',
                bank_name: null,
                account_holder: null,
                cbu: null,
                alias: null,
                custom_method: 'MercadoPago'
            }
        ]
    }
})

const columns = [
    { key: 'uf_number', label: 'UF' },
    { key: 'owner', label: 'Owner' },
    { key: 'preferred_method', label: 'Preferred Method' },
    { key: 'bank_name', label: 'Bank' },
    { key: 'cbu', label: 'CBU / Alias' }
]

// Modal state
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
    { value: 'Bank Transfer', label: 'Bank Transfer' },
    { value: 'Cash', label: 'Cash' },
    { value: 'Other', label: 'Other (Custom)' }
]

const bankOptions = [
    { value: 'Banco Nación', label: 'Banco Nación' },
    { value: 'Banco Galicia', label: 'Banco Galicia' },
    { value: 'Banco Santander', label: 'Banco Santander' },
    { value: 'Banco BBVA', label: 'Banco BBVA' },
    { value: 'Banco Macro', label: 'Banco Macro' },
    { value: 'Banco Ciudad', label: 'Banco Ciudad' },
    { value: 'Other', label: 'Other Bank' }
]

const openEditModal = (owner) => {
    selectedOwner.value = owner
    form.preferred_method = owner.preferred_method
    form.bank_name = owner.bank_name || ''
    form.account_holder = owner.account_holder || ''
    form.cbu = owner.cbu || ''
    form.alias = owner.alias || ''
    form.custom_method = owner.custom_method || ''
    form.clearErrors()
    showEditModal.value = true
}

const submitEdit = () => {
    form.put(`/payment-methods/${selectedOwner.value.id}`, {
        onSuccess: () => {
            showEditModal.value = false
            form.reset()
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
    // Format CBU with spaces for readability
    return cbu.replace(/(.{4})/g, '$1 ').trim()
}
</script>

<template>
    <DashboardLayout title="Payment Methods">
        <Card title="Owner Payment Methods" subtitle="Manage preferred payment methods and bank details for each unit">
            <template #default>
                <DataTable :columns="columns" :data="paymentMethods" empty-title="No payment methods found"
                    empty-description="Payment method information will appear here once owners are added.">
                    <template #cell-preferred_method="{ row, value }">
                        <div class="flex items-center gap-2">
                            <component :is="getMethodIcon(value)" class="w-4 h-4 text-slate-400" />
                            <StatusBadge :status="value" :variant="getMethodVariant(value)" />
                            <span v-if="row.custom_method" class="text-sm text-slate-500">
                                ({{ row.custom_method }})
                            </span>
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
                                <button class="p-1 hover:bg-slate-100 rounded transition-colors" title="Copy alias"
                                    @click.stop="copyToClipboard(row.alias, `alias-${row.id}`)">
                                    <Check v-if="copiedField === `alias-${row.id}`"
                                        class="w-3.5 h-3.5 text-emerald-500" />
                                    <Copy v-else class="w-3.5 h-3.5 text-slate-400" />
                                </button>
                            </div>
                            <div v-if="row.cbu" class="flex items-center gap-2">
                                <span class="text-xs text-slate-500 font-mono">{{ formatCBU(row.cbu) }}</span>
                                <button class="p-1 hover:bg-slate-100 rounded transition-colors" title="Copy CBU"
                                    @click.stop="copyToClipboard(row.cbu, `cbu-${row.id}`)">
                                    <Check v-if="copiedField === `cbu-${row.id}`"
                                        class="w-3.5 h-3.5 text-emerald-500" />
                                    <Copy v-else class="w-3.5 h-3.5 text-slate-400" />
                                </button>
                            </div>
                        </div>
                        <span v-else class="text-slate-400">-</span>
                    </template>

                    <template #actions="{ row }">
                        <button
                            class="p-1.5 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors"
                            title="Edit payment method" @click="openEditModal(row)">
                            <Pencil class="w-4 h-4" />
                        </button>
                    </template>
                </DataTable>
            </template>
        </Card>

        <!-- Payment Method Legend -->
        <div class="mt-6 bg-white border border-slate-200 rounded-lg p-4">
            <h4 class="text-sm font-medium text-slate-700 mb-3">Payment Method Types</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center">
                        <Building class="w-4 h-4 text-blue-600" />
                    </div>
                    <div>
                        <p class="font-medium text-slate-900">Bank Transfer</p>
                        <p class="text-sm text-slate-500">Transfer via CBU or Alias</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 bg-emerald-50 rounded-lg flex items-center justify-center">
                        <Banknote class="w-4 h-4 text-emerald-600" />
                    </div>
                    <div>
                        <p class="font-medium text-slate-900">Cash</p>
                        <p class="text-sm text-slate-500">In-person cash payment</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center">
                        <CreditCard class="w-4 h-4 text-slate-600" />
                    </div>
                    <div>
                        <p class="font-medium text-slate-900">Other</p>
                        <p class="text-sm text-slate-500">Custom payment method</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Payment Method Modal -->
        <Modal :show="showEditModal" :title="`Edit Payment Method - ${selectedOwner?.uf_number}`" max-width="lg"
            @close="showEditModal = false">
            <div v-if="selectedOwner" class="space-y-6">
                <!-- Owner Info -->
                <div class="bg-slate-50 rounded-lg p-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-slate-500">Unit</p>
                            <p class="font-medium text-slate-900">{{ selectedOwner.uf_number }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">Owner</p>
                            <p class="font-medium text-slate-900">{{ selectedOwner.owner }}</p>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="submitEdit" class="space-y-4">
                    <FormSelect v-model="form.preferred_method" label="Preferred Payment Method"
                        :options="methodOptions" :error="form.errors.preferred_method" required />

                    <FormInput v-if="form.preferred_method === 'Other'" v-model="form.custom_method"
                        label="Custom Method Name" placeholder="e.g., MercadoPago, PayPal"
                        :error="form.errors.custom_method" />

                    <!-- Bank details (only for Bank Transfer) -->
                    <div v-if="form.preferred_method === 'Bank Transfer'"
                        class="space-y-4 border-t border-slate-200 pt-4">
                        <h4 class="font-medium text-slate-900">Bank Account Details</h4>

                        <FormSelect v-model="form.bank_name" label="Bank Name" :options="bankOptions"
                            :error="form.errors.bank_name" />

                        <FormInput v-model="form.account_holder" label="Account Holder Name"
                            placeholder="Full name as it appears on the account" :error="form.errors.account_holder" />

                        <FormInput v-model="form.cbu" label="CBU" placeholder="22-digit CBU number"
                            :error="form.errors.cbu" />

                        <FormInput v-model="form.alias" label="Alias" placeholder="Account alias (optional)"
                            :error="form.errors.alias" />
                    </div>
                </form>
            </div>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button variant="secondary" @click="showEditModal = false">
                        Cancel
                    </Button>
                    <Button :loading="form.processing" @click="submitEdit">
                        Save Changes
                    </Button>
                </div>
            </template>
        </Modal>
    </DashboardLayout>
</template>
