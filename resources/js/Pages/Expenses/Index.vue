<script setup>
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import Card from '@/Components/Card.vue'
import DataTable from '@/Components/DataTable.vue'
import Button from '@/Components/Button.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import StatCard from '@/Components/StatCard.vue'
import FormSelect from '@/Components/FormSelect.vue'
import FormInput from '@/Components/FormInput.vue'
import FormTextarea from '@/Components/FormTextarea.vue'
import Modal from '@/Components/Modal.vue'
import { DollarSign, TrendingUp, TrendingDown, AlertTriangle, Filter, Plus, CreditCard, CheckCircle, AlertCircle, CalendarPlus } from 'lucide-vue-next'

const props = defineProps({
    expenses: {
        type: Array,
        default: () => []
    },
    bankAccounts: {
        type: Array,
        default: () => []
    },
    summary: {
        type: Object,
        default: () => ({
            totalMonthly: 0,
            totalExtraordinary: 0,
            totalFines: 0,
            totalOutstanding: 0,
            totalCollected: 0
        })
    },
    neighborhoodConfig: {
        type: Object,
        default: () => ({ type: 'fixed', fixed_amount: 0 })
    }
})
// --- ESTADO Y FORMULARIOS ---

// Filtros
const statusFilter = ref('all')
const statusOptions = [
    { value: 'all', label: 'Todo el estado' },
    { value: 'paid', label: 'Pagado' },
    { value: 'pending', label: 'Pendiente' },
    { value: 'overdue', label: 'Atrasado' }
]

const periodFilter = ref('')
const periodFilterInitialized = ref(false)
const periodOptions = computed(() => {
    const periods = [...new Set(
        props.expenses
            .map(e => e.period)
            .filter(Boolean)
    )].sort().reverse()

    return [
        { value: 'all', label: 'Todos los períodos' },
        ...periods.map(p => ({ value: p, label: p }))
    ]
})


// Modal Pago
const showPaymentModal = ref(false)
const paymentForm = useForm({
    unit_id: '',
    amount: '',
    payment_date: '',
    payment_method: 'bank_transfer',
    bank_account: '',
    reference: ''
})

// Modal Generación
const showGenerateModal = ref(false)
const generateForm = useForm({
    period: new Date().toISOString().slice(0, 7), // YYYY-MM
    amount: '',      // Para CC1 (Fijo)
    base_amount: '', // Para CC2 (Total a dividir)
    base_meters: 500 // Para CC2 (Divisor default)
})

// Modal Extraordinaria
const showExtraordinaryModal = ref(false)
const extraordinaryForm = useForm({
    period: new Date().toISOString().slice(0, 7),
    amount: '',
    base_meters: 500
})

// Modal Multa Manual
const showFineModal = ref(false)
const selectedExpenseForFine = ref(null)
const fineForm = useForm({
    amount: '',
    reason: ''
})

// --- COMPUTED PROPERTIES (Usando props directamente para reactividad) ---

// Opciones para el select de unidad (solo las que deben plata)
const unitOptions = computed(() => {
    return props.expenses
        .filter(exp => exp.outstanding_debt > 0)
        .sort((a, b) => a.period.localeCompare(b.period)) // vencidos primero
        .map(exp => ({
            value: exp.id.toString(),
            label: `${exp.uf_number} · ${exp.period} · ${exp.status === 'overdue' ? 'VENCIDO' : 'PENDIENTE'}`
        }))
})


// Unidad seleccionada en el modal
const selectedUnit = computed(() => {
    if (!paymentForm.unit_id) return null
    return props.expenses.find(exp => exp.id.toString() === paymentForm.unit_id)
})

// Indicador visual de cobertura del pago
const paymentCoverage = computed(() => {
    if (!selectedUnit.value || !paymentForm.amount) return null
    const amount = parseFloat(paymentForm.amount) || 0
    const outstanding = selectedUnit.value.outstanding_debt

    // Math.max evita negativos visuales por errores de redondeo
    const remaining = Math.max(0, outstanding - amount)

    if (amount >= outstanding) {
        return {
            type: 'full',
            message: 'Pago total del período seleccionado',
            variant: 'success'
        }
    } else if (amount > 0) {
        return {
            type: 'partial',
            message: `Pago parcial - Quedarán ${formatCurrency(remaining)} pendientes`,
            variant: 'warning'
        }
    }
    return null
})

// Lista filtrada para la tabla
const filteredExpenses = computed(() => {
    return props.expenses.filter(exp => {
        const statusOk =
            statusFilter.value === 'all' ||
            exp.status === statusFilter.value

        const periodOk =
            periodFilter.value === 'all' ||
            exp.period === periodFilter.value

        return statusOk && periodOk
    })
})


// Resumen filtrado para cards y totales de tabla
const filteredSummary = computed(() => {
    return filteredExpenses.value.reduce((acc, exp) => ({
        monthly: acc.monthly + Number(exp.monthly_expense || 0),
        extraordinary: acc.extraordinary + Number(exp.extraordinary || 0),
        fines: acc.fines + Number(exp.fines || 0),
        outstanding: acc.outstanding + Number(exp.outstanding_debt || 0),
        total: acc.total + Number(exp.total_balance || 0),
        collected: acc.collected + Number(exp.paid_amount || 0),
    }), {
        monthly: 0,
        extraordinary: 0,
        fines: 0,
        outstanding: 0,
        total: 0,
        collected: 0,
    })
})

// --- HELPERS ---
const getCurrentPeriod = () => {
    const date = new Date()
    const offset = date.getTimezoneOffset()
    date.setMinutes(date.getMinutes() - offset)
    return date.toISOString().slice(0, 7) // YYYY-MM
}

watch(
    () => props.expenses,
    (expenses) => {
        if (!expenses.length) return

        const currentPeriod = getCurrentPeriod()
        const availablePeriods = new Set(expenses.map(e => e.period).filter(Boolean))

        if (!periodFilterInitialized.value) {
            const exists = availablePeriods.has(currentPeriod)
            periodFilter.value = exists ? currentPeriod : 'all'
            periodFilterInitialized.value = true
            return
        }

        if (periodFilter.value && periodFilter.value !== 'all' && !availablePeriods.has(periodFilter.value)) {
            periodFilter.value = availablePeriods.has(currentPeriod) ? currentPeriod : 'all'
        }
    },
    { immediate: true }
)

// Obtiene fecha local (YYYY-MM-DD) respetando timezone de Argentina/Usuario
const getLocalDate = () => {
    const date = new Date();
    const offset = date.getTimezoneOffset();
    date.setMinutes(date.getMinutes() - offset);
    return date.toISOString().split('T')[0];
}

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        minimumFractionDigits: 2 // Cambié a 2 para ver centavos si es necesario
    }).format(amount)
}

const getStatusVariant = (status) => {
    const variants = { paid: 'success', pending: 'warning', overdue: 'danger' }
    return variants[status] || 'default'
}

const getStatusLabel = (status) => {
    const labels = { paid: 'Paid', pending: 'Pending', overdue: 'Overdue' }
    return labels[status] || status
}

// --- ACCIONES ---

const openPaymentModal = () => {
    periodFilter.value = 'all' // 👈 CLAVE
    paymentForm.reset()
    paymentForm.payment_date = getLocalDate()
    paymentForm.payment_method = 'bank_transfer'
    showPaymentModal.value = true
}


const closePaymentModal = () => {
    showPaymentModal.value = false
    paymentForm.reset()
}

const fillFullAmount = () => {
    if (selectedUnit.value) {
        paymentForm.amount = selectedUnit.value.outstanding_debt.toString()
    }
}

const submitPayment = () => {
    paymentForm.post(route('expenses.store'), {
        onSuccess: () => closePaymentModal()
    })
}

const openGenerateModal = () => {
    if (props.neighborhoodConfig.type === 'fixed') {
        generateForm.amount = props.neighborhoodConfig.fixed_amount || ''
    }
    showGenerateModal.value = true
}

const submitGeneration = () => {
    generateForm.post(route('expenses.generate'), {
        onSuccess: () => {
            showGenerateModal.value = false
            generateForm.reset()
        }
    })
}

const openExtraordinaryModal = () => {
    extraordinaryForm.reset()
    extraordinaryForm.period = getCurrentPeriod()
    extraordinaryForm.base_meters = 500
    showExtraordinaryModal.value = true
}

const closeExtraordinaryModal = () => {
    showExtraordinaryModal.value = false
    extraordinaryForm.reset()
}

const submitExtraordinary = () => {
    extraordinaryForm.post(route('expenses.extraordinary'), {
        onSuccess: () => closeExtraordinaryModal()
    })
}

const openFineModal = (row) => {
    selectedExpenseForFine.value = row
    fineForm.reset()

    const suggestedFine = Number(row.outstanding_debt) > 0
        ? (Number(row.outstanding_debt) * 0.1).toFixed(2)
        : ''

    fineForm.amount = suggestedFine
    showFineModal.value = true
}

const closeFineModal = () => {
    showFineModal.value = false
    selectedExpenseForFine.value = null
    fineForm.reset()
}

const submitFine = () => {
    if (!selectedExpenseForFine.value) return

    fineForm.post(route('expenses.fine', selectedExpenseForFine.value.id), {
        onSuccess: () => closeFineModal()
    })
}

// Configuración de columnas para DataTable
const columns = [
    { key: 'period', label: 'Período' },
    { key: 'uf_number', label: 'UF' },
    { key: 'owner', label: 'Propietario' },
    { key: 'monthly_expense', label: 'Mensual', class: 'text-right' },
    { key: 'extraordinary', label: 'Extra.', class: 'text-right' },
    { key: 'fines', label: 'Multas', class: 'text-right' },
    { key: 'outstanding_debt', label: 'Pendiente', class: 'text-right' },
    { key: 'total_balance', label: 'Total', class: 'text-right' },
    { key: 'status', label: 'Estado' },
    { key: 'actions', label: 'Acciones' }
]

const paymentMethodOptions = [
    { value: 'cash', label: 'Efectivo' },
    { value: 'bank_transfer', label: 'Transferencia Bancaria' },
    { value: 'check', label: 'Cheque' },
    { value: 'other', label: 'Otro' }
]

watch(
    () => paymentForm.payment_method,
    (method) => {
        if (method === 'cash') {
            paymentForm.bank_account = ''
        }
    }
)

const ahora = new Date();
const dia = String(ahora.getDate()).padStart(2, '0');
const mes = String(ahora.getMonth() + 1).padStart(2, '0'); // Enero es 0
const anio = ahora.getFullYear();

const fechaActual = `${dia}/${mes}/${anio}`;
console.log(fechaActual); // Resultado: "dd/mm/yyyy"

</script>

<template>
    <DashboardLayout title="Expensas y Honorarios">

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <StatCard title="Expensas Mensuales" :value="formatCurrency(filteredSummary.monthly)" :icon="DollarSign" />
            <StatCard title="Extraordinaria" :value="formatCurrency(filteredSummary.extraordinary)" :icon="TrendingUp" />
            <StatCard title="Multas" :value="formatCurrency(filteredSummary.fines)" :icon="AlertTriangle" />
            <StatCard title="Pendiente Cobro" :value="formatCurrency(filteredSummary.outstanding)" :icon="TrendingDown" />
            <StatCard title="Total Recaudado" :value="formatCurrency(filteredSummary.collected)" :icon="DollarSign" />
        </div>

        <Card title="Panorama Financiero de la UF" subtitle="Expensas mensuales, multas y saldos pendientes">
            <template #header-actions>
                <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center">
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <Filter class="w-4 h-4 text-slate-400" />
                        <FormSelect v-model="statusFilter" :options="statusOptions" placeholder="Filtrar por estado"
                            class="w-full sm:w-40" />
                    </div>

                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <CalendarPlus class="w-4 h-4 text-slate-400" />
                        <FormSelect v-model="periodFilter" :options="periodOptions" placeholder="Filtrar por período"
                            class="w-full sm:w-40" />
                    </div>

                    <Button class="w-full sm:w-auto" variant="primary" @click="openGenerateModal">
                        <CalendarPlus class="w-4 h-4 mr-2" />
                        Generar Período
                    </Button>
                    <Button class="w-full sm:w-auto" variant="warning" @click="openExtraordinaryModal">
                        <TrendingUp class="w-4 h-4 mr-2" />
                        Extraordinaria
                    </Button>

                    <Button class="w-full sm:w-auto" variant="primary" @click="openPaymentModal">
                        <Plus class="w-4 h-4 mr-2" />
                        Registrar Pago
                    </Button>

                </div>
            </template>

            <template #default>
                <DataTable :columns="columns" :data="filteredExpenses" empty-title="No se encontraron Expensas"
                    empty-description="No hay registros de expensas que coincidan con el filtro actual.">

                    <template #cell-monthly_expense="{ value }">
                        <span class="font-medium">{{ formatCurrency(value) }}</span>
                    </template>

                    <template #cell-extraordinary="{ value }">
                        <span :class="value > 0 ? 'text-amber-600' : 'text-slate-400'">
                            {{ formatCurrency(value) }}
                        </span>
                    </template>

                    <template #cell-fines="{ value }">
                        <span :class="value > 0 ? 'text-red-600 font-medium' : 'text-slate-400'">
                            {{ formatCurrency(value) }}
                        </span>
                    </template>

                    <template #cell-outstanding_debt="{ value }">
                        <span :class="value > 0 ? 'text-red-600 font-semibold' : 'text-emerald-600'">
                            {{ formatCurrency(value) }}
                        </span>
                    </template>

                    <template #cell-total_balance="{ value }">
                        <span class="font-semibold text-slate-900">{{ formatCurrency(value) }}</span>
                    </template>

                    <template #cell-status="{ value }">
                        <StatusBadge :status="getStatusLabel(value)" :variant="getStatusVariant(value)" />
                    </template>

                    <template #cell-actions="{ row }">
                        <Button variant="danger" @click="openFineModal(row)" :disabled="Number(row.outstanding_debt) <= 0">
                            <AlertTriangle class="w-4 h-4" />
                        </Button>
                    </template>

                </DataTable>

                <div class="border-t-2 border-slate-300 bg-slate-50 px-4 py-3 -mx-6 -mb-4 mt-4 rounded-b-lg overflow-x-auto">
                    <div class="grid min-w-[700px] grid-cols-8 gap-4 text-sm">
                        <div class="col-span-2 font-semibold text-slate-700">Totales</div>
                        <div class="text-right font-semibold text-slate-900">{{ formatCurrency(filteredSummary.monthly) }}</div>
                        <div class="text-right font-semibold text-amber-600">{{ formatCurrency(filteredSummary.extraordinary) }}
                        </div>
                        <div class="text-right font-semibold text-red-600">{{ formatCurrency(filteredSummary.fines) }}</div>
                        <div class="text-right font-semibold text-red-600">{{ formatCurrency(filteredSummary.outstanding) }}
                        </div>
                        <div class="text-right font-bold text-slate-900">{{ formatCurrency(filteredSummary.total) }}</div>
                        <div></div>
                    </div>
                </div>
            </template>
        </Card>

        <div class="mt-6 flex flex-col items-start gap-4 text-sm sm:flex-row sm:flex-wrap sm:items-center sm:gap-6">
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                <span class="text-slate-600">Pagado - Sin saldo pendiente</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                <span class="text-slate-600">Pendiente - Pago vence este mes</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-red-500"></span>
                <span class="text-slate-600">Vencido - Pagos vencidos</span>
            </div>
        </div>

        <Modal :show="showPaymentModal" title="Registrar Pago" max-width="lg" @close="closePaymentModal">
            <p class="text-xs text-slate-500">
                Se muestran todas las unidades con saldo pendiente, sin importar el período seleccionado.
            </p>

            <form @submit.prevent="submitPayment" class="space-y-5">
                <div>
                    <FormSelect v-model="paymentForm.unit_id" label="Seleccionar unidad (UF)" :options="unitOptions"
                        placeholder="Elija una unidad con saldo pendiente" :error="paymentForm.errors.unit_id"
                        required />

                    <p v-if="unitOptions.length === 0" class="mt-1 text-sm text-slate-500">
                        No hay unidades con saldo pendiente
                    </p>
                </div>

                <div v-if="selectedUnit" class="bg-slate-50 rounded-lg p-4 border border-slate-200">
                    <div class="flex items-center gap-3 mb-3">
                        <CreditCard class="w-5 h-5 text-slate-400" />
                        <span class="font-medium text-slate-900">Detalles de la unidad</span>
                    </div>
                    <div class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <span class="text-slate-500">Propietario:</span>
                            <span class="ml-2 font-medium text-slate-900">{{ selectedUnit.owner }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500">Unidad:</span>
                            <span class="ml-2 font-medium text-slate-900">{{ selectedUnit.uf_number }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500">Saldo pendiente:</span>
                            <span class="ml-2 font-semibold text-red-600">{{
                                formatCurrency(selectedUnit.outstanding_debt)
                            }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500">Estado:</span>
                            <StatusBadge :status="getStatusLabel(selectedUnit.status)"
                                :variant="getStatusVariant(selectedUnit.status)" />
                        </div>
                    </div>
                </div>

                <div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <div class="w-full sm:flex-1">
                            <FormInput v-model="paymentForm.amount" type="number" label="Monto del pago"
                                placeholder="0.00" :error="paymentForm.errors.amount" min="0" step="0.01" required />
                        </div>
                        <Button v-if="selectedUnit" type="button" variant="secondary" size="sm" @click="fillFullAmount"
                            class="w-full sm:mb-1 sm:w-auto">
                            Monto total
                        </Button>
                    </div>
                </div>

                <div v-if="paymentCoverage" :class="[
                    'flex items-center gap-2 p-3 rounded-lg text-sm',
                    paymentCoverage.variant === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'
                ]">
                    <CheckCircle v-if="paymentCoverage.variant === 'success'" class="w-4 h-4 flex-shrink-0" />
                    <AlertCircle v-else class="w-4 h-4 flex-shrink-0" />
                    <span>{{ paymentCoverage.message }}</span>
                </div>

                <FormInput v-model="paymentForm.payment_date" type="date" label="Fecha de pago"
                    :error="paymentForm.errors.payment_date" required />

                <FormSelect v-model="paymentForm.payment_method" label="Método de pago" :options="paymentMethodOptions"
                    :error="paymentForm.errors.payment_method" required />

                <FormSelect v-model="paymentForm.bank_account" label="Cuenta bancaria" :options="bankAccounts"
                    :error="paymentForm.errors.bank_account"
                    :disabled="paymentForm.payment_method === 'cash'" />

                <FormTextarea v-model="paymentForm.reference" label="Referencia / Notas"
                    placeholder="Referencia de transacción, número de recibo o notas adicionales..." :rows="2"
                    :error="paymentForm.errors.reference" />
            </form>

            <template #footer>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                    <Button class="w-full sm:w-auto" type="button" variant="secondary" @click="closePaymentModal">
                        Cancelar
                    </Button>
                    <Button class="w-full sm:w-auto" type="button" variant="primary"
                        :disabled="!paymentForm.unit_id || !paymentForm.amount || paymentForm.processing"
                        :loading="paymentForm.processing" @click="submitPayment">
                        <DollarSign class="w-4 h-4 mr-2" />
                        Registrar Pago
                    </Button>
                </div>
            </template>
        </Modal>

        <Modal :show="showGenerateModal" title="Generar Expensas Masivas" max-width="md"
            @close="showGenerateModal = false">
            <form @submit.prevent="submitGeneration" class="space-y-5">

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 flex gap-3">
                    <div class="mt-0.5">
                        <CalendarPlus class="w-5 h-5 text-blue-600" />
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-blue-900">Generación Mensual</h4>
                        <p class="text-xs text-blue-700 mt-1">
                            Esto generará la deuda para <strong>todas las unidades</strong> del barrio activo.
                            Las unidades que ya tengan expensa en este período serán omitidas.
                        </p>
                    </div>
                </div>

                <FormInput v-model="generateForm.period" type="month" label="Período a Liquidar"
                    :error="generateForm.errors.period" required />

                <div v-if="neighborhoodConfig.type === 'fixed'">
                    <FormInput v-model="generateForm.amount" type="number" label="Monto Fijo por Lote"
                        placeholder="Ej: 50000" :error="generateForm.errors.amount" required />
                    <p class="text-xs text-slate-500 mt-1">Este monto se aplicará igual a todos los lotes.</p>
                </div>

                <div v-if="neighborhoodConfig.type === 'proportional'" class="space-y-4">
                    <FormInput v-model="generateForm.base_amount" type="number" label="Gasto Total a Distribuir ($)"
                        placeholder="Ej: 46000" :error="generateForm.errors.base_amount" required />

                    <FormInput v-model="generateForm.base_meters" type="number" label="Metros Base para el cálculo"
                        placeholder="Ej: 500" :error="generateForm.errors.base_meters" required />

                    <div class="text-xs text-slate-500 bg-slate-50 p-3 rounded border">
                        <strong>Fórmula estimada:</strong> <br>
                        (${{ generateForm.base_amount || 0 }} / {{ generateForm.base_meters || 1 }}m²) × Metros de cada
                        Lote
                    </div>
                </div>

            </form>

            <template #footer>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button class="w-full sm:w-auto" variant="secondary" @click="showGenerateModal = false">Cancelar</Button>
                    <Button class="w-full sm:w-auto" @click="submitGeneration" :loading="generateForm.processing">
                        Confirmar Generación
                    </Button>
                </div>
            </template>
        </Modal>

        <Modal :show="showExtraordinaryModal" title="Generar Expensa Extraordinaria" max-width="md"
            @close="closeExtraordinaryModal">
            <form @submit.prevent="submitExtraordinary" class="space-y-5">
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 flex gap-3">
                    <div class="mt-0.5">
                        <TrendingUp class="w-5 h-5 text-amber-600" />
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-amber-900">Aplicación Masiva</h4>
                        <p class="text-xs text-amber-700 mt-1">
                            {{
                                neighborhoodConfig.type === 'proportional'
                                    ? 'En CC2 se calculara proporcionalmente segun los metros del lote.'
                                    : 'Se sumara el monto extraordinario a todas las unidades del periodo seleccionado.'
                            }}
                        </p>
                    </div>
                </div>

                <FormInput v-model="extraordinaryForm.period" type="month" label="Período"
                    :error="extraordinaryForm.errors.period" required />

                <FormInput v-model="extraordinaryForm.amount" type="number"
                    :label="neighborhoodConfig.type === 'proportional' ? 'Monto base extraordinario ($)' : 'Monto Extraordinario por Unidad'"
                    placeholder="Ej: 75000" :error="extraordinaryForm.errors.amount" min="1" step="0.01" required />

                <div v-if="neighborhoodConfig.type === 'proportional'" class="space-y-3">
                    <FormInput v-model="extraordinaryForm.base_meters" type="number" label="Metros Base para el calculo"
                        placeholder="Ej: 500" :error="extraordinaryForm.errors.base_meters" min="0.01" step="0.01"
                        required />

                    <div class="text-xs text-slate-500 bg-slate-50 p-3 rounded border">
                        <strong>Formula estimada:</strong> <br>
                        (${{ extraordinaryForm.amount || 0 }} / {{ extraordinaryForm.base_meters || 1 }}m²) × metros de cada lote
                    </div>
                </div>
            </form>

            <template #footer>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button class="w-full sm:w-auto" variant="secondary" @click="closeExtraordinaryModal">Cancelar</Button>
                    <Button class="w-full sm:w-auto" @click="submitExtraordinary" :loading="extraordinaryForm.processing">
                        Confirmar Extraordinaria
                    </Button>
                </div>
            </template>
        </Modal>

        <Modal :show="showFineModal" title="Aplicar Multa Manual" max-width="md" @close="closeFineModal">
            <form @submit.prevent="submitFine" class="space-y-5">
                <div v-if="selectedExpenseForFine" class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm">
                    <p class="text-red-900 font-medium">
                        {{ selectedExpenseForFine.uf_number }} · {{ selectedExpenseForFine.period }}
                    </p>
                    <p class="text-red-700 mt-1">
                        Saldo pendiente actual: {{ formatCurrency(selectedExpenseForFine.outstanding_debt) }}
                    </p>
                </div>

                <FormInput v-model="fineForm.amount" type="number" label="Monto de la multa"
                    :error="fineForm.errors.amount" min="1" step="0.01" required />

                <FormTextarea v-model="fineForm.reason" label="Motivo (opcional)"
                    :error="fineForm.errors.reason" :rows="2" placeholder="Ej: Interés por mora / multa administrativa" />
            </form>

            <template #footer>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button class="w-full sm:w-auto" variant="secondary" @click="closeFineModal">Cancelar</Button>
                    <Button class="w-full sm:w-auto" variant="danger" @click="submitFine" :loading="fineForm.processing"
                        :disabled="!fineForm.amount || fineForm.processing">
                        Aplicar Multa
                    </Button>
                </div>
            </template>
        </Modal>
    </DashboardLayout>
</template>
