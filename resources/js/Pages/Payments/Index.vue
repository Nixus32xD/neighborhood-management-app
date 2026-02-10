<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import Card from '@/Components/Card.vue'
import DataTable from '@/Components/DataTable.vue'
import Button from '@/Components/Button.vue'
import Modal from '@/Components/Modal.vue'
import FormInput from '@/Components/FormInput.vue'
import FormSelect from '@/Components/FormSelect.vue'
import FormTextarea from '@/Components/FormTextarea.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import {
    Plus,
    Eye,
    Filter,
    Calendar,
    DollarSign,
    Upload,
    FileText,
    Building,
    CreditCard
} from 'lucide-vue-next'

const props = defineProps({
    movements: {
        type: Array,
        default: () => [
            {
                id: 1,
                date: '2024-01-15',
                amount: 15000,
                description: 'Security service payment',
                recipient: 'Seguridad ABC S.A.',
                payment_method: 'Bank Transfer',
                bank_account: 'Banco Nación - 123456789',
                voucher_url: '/vouchers/001.pdf',
                is_high_value: true
            },
            {
                id: 2,
                date: '2024-01-14',
                amount: 5500,
                description: 'Garden maintenance',
                recipient: 'Jardinería Verde',
                payment_method: 'Bank Transfer',
                bank_account: 'Banco Nación - 123456789',
                voucher_url: '/vouchers/002.pdf',
                is_high_value: false
            },
            {
                id: 3,
                date: '2024-01-12',
                amount: 8200,
                description: 'Pool cleaning service',
                recipient: 'Piletas Limpias',
                payment_method: 'Cash',
                bank_account: null,
                voucher_url: '/vouchers/003.pdf',
                is_high_value: false
            },
            {
                id: 4,
                date: '2024-01-10',
                amount: 25000,
                description: 'Electrical repairs - common areas',
                recipient: 'Electricidad Total',
                payment_method: 'Bank Transfer',
                bank_account: 'Banco Galicia - 987654321',
                voucher_url: null,
                is_high_value: true
            },
            {
                id: 5,
                date: '2024-01-08',
                amount: 3200,
                description: 'Office supplies',
                recipient: 'Librería Central',
                payment_method: 'Cash',
                bank_account: null,
                voucher_url: '/vouchers/005.pdf',
                is_high_value: false
            }
        ]
    },
    bankAccounts: {
        type: Array,
        default: () => [
            { value: 'banco-nacion', label: 'Banco Nación - 123456789' },
            { value: 'banco-galicia', label: 'Banco Galicia - 987654321' }
        ]
    }
})

const columns = [
    { key: 'date', label: 'Fecha' },
    { key: 'description', label: 'Descripcion' },
    { key: 'recipient', label: 'Beneficiario' },
    { key: 'amount', label: 'Cantidad', class: 'text-right' },
    { key: 'payment_method', label: 'Metodo' },
    { key: 'voucher', label: 'Comprobante' }
]

// Modal states
const showCreateModal = ref(false)
const showDetailModal = ref(false)
const selectedMovement = ref(null)

const form = useForm({
    date: '',
    amount: '',
    description: '',
    recipient: '',
    payment_method: '',
    bank_account: '',
    voucher: null
})

const paymentMethodOptions = [
    { value: 'Bank Transfer', label: 'Transferencia Bancaria' },
    { value: 'Cash', label: 'Efectivo' },
    { value: 'Check', label: 'Cheque' }
]

const showVoucherModal = ref(false)

const isPdf = computed(() => {
    if (!selectedMovement.value?.voucher_url) return false
    return selectedMovement.value.voucher_url.toLowerCase().endsWith('.pdf')
})

// En tu <script setup>
const openVoucher = (movement) => {
    selectedMovement.value = movement; // <--- Esto es lo que faltaba
    showVoucherModal.value = true;
}


const openCreateModal = () => {
    form.reset()
    form.clearErrors()
    showCreateModal.value = true
}

const openDetailModal = (movement) => {
    selectedMovement.value = movement
    showDetailModal.value = true
}

const submitCreate = () => {
    form.post('/payments', {
        onSuccess: () => {
            showCreateModal.value = false
            form.reset()
        }
    })
}

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

const removeFile = () => {
    form.voucher = null;
}

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        minimumFractionDigits: 0
    }).format(amount)
}

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-AR', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    })
}

const clearFilters = () => {
    dateFrom.value = ''
    dateTo.value = ''
    amountFilter.value = 'all'
    typeFilter.value = 'all'
}

// Filters
const dateFrom = ref('')
const dateTo = ref('')
const amountFilter = ref('all')
const typeFilter = ref('all')

const amountOptions = [
    { value: 'all', label: 'Todos los montos' },
    { value: 'high', label: 'Alto Valor (>$10,000)' },
    { value: 'low', label: 'Bajo Valor (<$10,000)' }
]

const typeOptions = [
    { value: 'all', label: 'Todos los métodos' },
    { value: 'Bank Transfer', label: 'Transferencia Bancaria' }, // El value queda en inglés si así lo espera el backend
    { value: 'Cash', label: 'Efectivo' }
]

const filteredMovements = computed(() => {
    return props.movements.filter(m => {
        // Date filter
        if (dateFrom.value && m.date < dateFrom.value) return false
        if (dateTo.value && m.date > dateTo.value) return false

        // Amount filter
        if (amountFilter.value === 'high' && m.amount <= 10000) return false
        if (amountFilter.value === 'low' && m.amount > 10000) return false

        // Type filter
        if (typeFilter.value !== 'all' && m.payment_method !== typeFilter.value) return false

        return true
    })
})

const isDragging = ref(false)

const handleDrop = (e) => {
    isDragging.value = false
    const files = e.dataTransfer.files

    // Si soltó al menos un archivo, lo asignamos
    if (files.length > 0) {
        // Opcional: Acá podrías validar tipo o tamaño antes de asignar
        form.voucher = files[0]
    }
}
</script>

<template>
    <DashboardLayout title="Pagos & Movimientos Bancarios">
        <!-- Filters Card -->
        <Card class="mb-6">
            <div class="flex flex-wrap items-end gap-4">
                <div class="flex items-center gap-2">
                    <Filter class="w-4 h-4 text-slate-400" />
                    <span class="text-sm font-medium text-slate-700">Filtros:</span>
                </div>

                <div class="flex items-center gap-2">
                    <Calendar class="w-4 h-4 text-slate-400" />
                    <FormInput v-model="dateFrom" type="date" placeholder="De" class="w-36" />
                    <span class="text-slate-400">A</span>
                    <FormInput v-model="dateTo" type="date" placeholder="A" class="w-36" />
                </div>

                <FormSelect v-model="amountFilter" :options="amountOptions" class="w-44" />

                <FormSelect v-model="typeFilter" :options="typeOptions" class="w-36" />

                <Button variant="ghost" size="sm" @click="clearFilters">
                    Limpiar Filtros
                </Button>
            </div>
        </Card>

        <!-- Movements Table -->
        <Card title="Movimientos de cuentas bancarias"
            subtitle="Realice un seguimiento de todos los pagos y transacciones para mayor claridad en la auditoría.">
            <template #header-actions>
                <Button @click="openCreateModal">
                    <Plus class="w-4 h-4" />
                    Añadir Movimientos
                </Button>
            </template>

            <template #default>
                <DataTable :columns="columns" :data="filteredMovements" empty-title="No se encontraron movimientos"
                    empty-description="No hay transacciones que coincidan con los filtros actuales.">
                    <template #cell-date="{ value }">
                        <span class="text-slate-600">{{ formatDate(value) }}</span>
                    </template>

                    <template #cell-description="{ row, value }">
                        <div class="flex items-center gap-2">
                            <span :class="{ 'font-medium': row.is_high_value }">{{ value }}</span>
                            <StatusBadge v-if="row.is_high_value" status="High Value" variant="warning" />
                        </div>
                    </template>

                    <template #cell-amount="{ row, value }">
                        <span :class="[
                            'font-semibold',
                            row.is_high_value ? 'text-amber-600' : 'text-slate-900'
                        ]">
                            {{ formatCurrency(value) }}
                        </span>
                    </template>

                    <template #cell-payment_method="{ value }">
                        <div class="flex items-center gap-1.5">
                            <CreditCard v-if="value === 'Bank Transfer'" class="w-4 h-4 text-slate-400" />
                            <DollarSign v-else class="w-4 h-4 text-slate-400" />
                            <span>
                                {{ value === 'Bank Transfer' ? 'Transferencia' : (value === 'Cash' ? 'Efectivo' :
                                    value) }}
                            </span>
                        </div>
                    </template>

                    <template #cell-voucher="{ row }">
                        <button v-if="row.voucher_url" @click="openVoucher(row)"
                            class="inline-flex items-center gap-1 text-emerald-600 hover:text-emerald-700 text-sm">
                            <FileText class="w-4 h-4" />
                            Vista
                        </button>
                        <span v-else class="text-slate-400 text-sm">Sin Comprobante</span>
                    </template>

                    <template #actions="{ row }">
                        <button
                            class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded transition-colors"
                            title="View details" @click="openDetailModal(row)">
                            <Eye class="w-4 h-4" />
                        </button>
                    </template>
                </DataTable>
            </template>
        </Card>

        <!-- Create Movement Modal -->
        <Modal :show="showCreateModal" title="Agregar movimiento bancario" max-width="lg"
            @close="showCreateModal = false">
            <form @submit.prevent="submitCreate" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <FormInput v-model="form.date" type="date" label="Fecha" :error="form.errors.date" required />
                    <FormInput v-model="form.amount" type="number" label="Cantidad" placeholder="0.00"
                        :error="form.errors.amount" required />
                </div>

                <FormInput v-model="form.description" label="Descripción" placeholder="Describe el gasto"
                    :error="form.errors.description" required />

                <FormInput v-model="form.recipient" label="Nombre del destinatario"
                    placeholder="¿Quién recibió el pago?" :error="form.errors.recipient" required />

                <div class="grid grid-cols-2 gap-4">
                    <FormSelect v-model="form.payment_method" label="Método de pago" :options="paymentMethodOptions"
                        :error="form.errors.payment_method" required />
                    <FormSelect v-model="form.bank_account" label="Cuenta bancaria" :options="bankAccounts"
                        :error="form.errors.bank_account" :disabled="form.payment_method === 'Cash'" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        Comprobante / Recibo
                    </label>

                    <label v-if="!form.voucher" @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false" @drop.prevent="handleDrop" :class="[
                            'block border-2 border-dashed rounded-lg p-6 text-center transition-all cursor-pointer group',
                            isDragging ? 'border-emerald-500 bg-emerald-50' : 'border-slate-300 hover:border-emerald-400 hover:bg-slate-50'
                        ]">
                        <div class="space-y-1">
                            <Upload :class="[
                                'w-8 h-8 mx-auto transition-colors',
                                isDragging ? 'text-emerald-600' : 'text-slate-400 group-hover:text-emerald-500'
                            ]" />

                            <p class="text-sm text-slate-600">
                                <span v-if="isDragging" class="font-medium text-emerald-700">¡Soltalo ahora!</span>
                                <span v-else>Hacé clic o arrastrá el archivo acá</span>
                            </p>
                            <p class="text-xs text-slate-400">PDF, PNG, JPG hasta 10MB</p>
                        </div>

                        <input type="file" class="hidden" accept=".pdf,.png,.jpg,.jpeg"
                            @change="form.voucher = $event.target.files[0]" />
                    </label>

                    <div v-else
                        class="relative flex items-center p-4 border border-emerald-200 bg-emerald-50 rounded-lg">
                        <FileText class="w-8 h-8 text-emerald-600 mr-3" />

                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-emerald-900 truncate">
                                {{ form.voucher.name }}
                            </p>
                            <p class="text-xs text-emerald-600">
                                {{ formatFileSize(form.voucher.size) }}
                            </p>
                        </div>

                        <button type="button" @click="removeFile"
                            class="ml-4 p-1 rounded-full text-emerald-600 hover:bg-emerald-200 transition-colors"
                            title="Eliminar archivo">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>

                    <p v-if="form.errors.voucher" class="text-sm text-red-600 mt-1">
                        {{ form.errors.voucher }}
                    </p>
                </div>
            </form>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button variant="secondary" @click="showCreateModal = false">
                        Cancelar
                    </Button>
                    <Button :loading="form.processing" @click="submitCreate">
                        Agregar Movimiento
                    </Button>
                </div>
            </template>
        </Modal>

        <!-- Movement Detail Modal -->
        <Modal :show="showDetailModal" title="Detalles del Movimiento" max-width="lg" @close="showDetailModal = false">
            <div v-if="selectedMovement" class="space-y-6">
                <!-- High value warning -->
                <div v-if="selectedMovement.is_high_value"
                    class="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-start gap-3">
                    <DollarSign class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="font-medium text-amber-800">Transacción de Alto Valor</p>
                        <p class="text-sm text-amber-700">Esta transacción supera los $10,000 y requiere atención de
                            auditoría
                            adicional.</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Fecha</p>
                        <p class="mt-1 text-slate-900">{{ formatDate(selectedMovement.date) }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500">Monto</p>
                        <p class="mt-1 text-xl font-semibold text-slate-900">
                            {{ formatCurrency(selectedMovement.amount) }}
                        </p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-sm font-medium text-slate-500">Descripcion</p>
                        <p class="mt-1 text-slate-900">{{ selectedMovement.description }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500">Beneficiario</p>
                        <p class="mt-1 text-slate-900">{{ selectedMovement.recipient }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500">Metodo de Pago</p>
                        <p class="mt-1 text-slate-900">{{ selectedMovement.payment_method }}</p>
                    </div>
                    <div class="col-span-2" v-if="selectedMovement.bank_account">
                        <p class="text-sm font-medium text-slate-500">Cuenta Bancaria</p>
                        <div class="mt-1 flex items-center gap-2">
                            <Building class="w-4 h-4 text-slate-400" />
                            <span class="text-slate-900">{{ selectedMovement.bank_account }}</span>
                        </div>
                    </div>
                </div>

                <!-- Voucher section -->
                <div class="border-t border-slate-200 pt-4">
                    <p class="text-sm font-medium text-slate-500 mb-2">Comprobante / Recibo</p>
                    <div v-if="selectedMovement.voucher_url"
                        class="bg-slate-50 border border-slate-200 rounded-lg p-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <FileText class="w-8 h-8 text-slate-400" />
                            <div>
                                <p class="font-medium text-slate-700">Recibo Adjunto</p>
                                <p class="text-sm text-slate-500">Haga clic para ver o descargar</p>
                            </div>
                        </div>
                        <button @click="openVoucher(selectedMovement)"
                            class="text-emerald-600 hover:text-emerald-700 font-medium">
                            Vista
                        </button>

                    </div>
                    <div v-else class="bg-slate-50 border border-slate-200 rounded-lg p-4 text-center text-slate-500">
                        No hay ningún voucher adjunto a este movimiento
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end">
                    <Button variant="secondary" @click="showDetailModal = false">
                        Cerrar
                    </Button>
                </div>
            </template>
        </Modal>

        <Modal :show="showVoucherModal" title="Vista previa del comprobante" max-width="xl"
            @close="showVoucherModal = false">
            <div class="h-[75vh] flex items-center justify-center bg-slate-100 rounded">
                <!-- PDF -->
                <iframe v-if="isPdf" :src="selectedMovement.voucher_url" class="w-full h-full rounded" />

                <!-- Imagen -->
                <img v-else :src="selectedMovement.voucher_url" class="max-h-full max-w-full object-contain rounded" />
            </div>

            <template #footer>
                <div class="flex justify-between w-full">
                    <a :href="selectedMovement.voucher_url" target="_blank"
                        class="text-sm text-slate-500 hover:text-slate-700">
                        Abrir en nueva pestaña
                    </a>

                    <Button variant="secondary" @click="showVoucherModal = false">
                        Cerrar
                    </Button>
                </div>
            </template>
        </Modal>

    </DashboardLayout>
</template>
