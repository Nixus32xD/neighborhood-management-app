<script setup>
import { computed, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import Card from '@/Components/Card.vue'
import Button from '@/Components/Button.vue'
import FormSelect from '@/Components/FormSelect.vue'
import FormInput from '@/Components/FormInput.vue'
import StatCard from '@/Components/StatCard.vue'
import { Filter, Printer } from 'lucide-vue-next'

const props = defineProps({
    owners: {
        type: Array,
        default: () => []
    },
    filters: {
        type: Object,
        default: () => ({})
    },
    statement: {
        type: Object,
        default: null
    }
})

const form = reactive({
    owner_id: props.filters.owner_id || props.owners?.[0]?.id || '',
    filter_type: props.filters.filter_type || 'period',
    period_from: props.filters.period_from || '',
    period_to: props.filters.period_to || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || ''
})

const ownerOptions = computed(() => props.owners.map((owner) => ({
    value: owner.id,
    label: `${owner.uf} - ${owner.name}`
})))

const filterTypeOptions = [
    { value: 'period', label: 'Por periodo' },
    { value: 'date', label: 'Por rango de fechas' },
]

const formatCurrency = (amount) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(amount || 0)

const applyFilters = () => {
    router.get(route('owner-statements.index'), form, {
        preserveScroll: true,
        preserveState: true,
    })
}

const printStatement = () => {
    const printUrl = route('owner-statements.print', form)
    window.open(printUrl, '_blank')
}
</script>

<template>
    <DashboardLayout title="Estado Individual de Propietario">
        <Card title="Consulta de cuenta corriente" subtitle="Filtra por propietario, periodo o fechas e imprime el informe">
            <template #header-actions>
                <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center">
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <Filter class="w-4 h-4 text-slate-400" />
                        <FormSelect v-model="form.owner_id" :options="ownerOptions" class="w-full sm:w-72"
                            placeholder="Seleccionar propietario" />
                    </div>

                    <FormSelect v-model="form.filter_type" :options="filterTypeOptions" class="w-full sm:w-56" />

                    <template v-if="form.filter_type === 'period'">
                        <FormInput v-model="form.period_from" type="month" class="w-full sm:w-44" />
                        <FormInput v-model="form.period_to" type="month" class="w-full sm:w-44" />
                    </template>

                    <template v-else>
                        <FormInput v-model="form.date_from" type="date" class="w-full sm:w-44" />
                        <FormInput v-model="form.date_to" type="date" class="w-full sm:w-44" />
                    </template>

                    <Button variant="primary" class="w-full sm:w-auto" @click="applyFilters">Aplicar</Button>
                    <Button variant="secondary" class="w-full sm:w-auto" :disabled="!statement" @click="printStatement">
                        <Printer class="w-4 h-4" />
                        Imprimir
                    </Button>
                </div>
            </template>

            <div v-if="statement">
                <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2 lg:grid-cols-4">
                    <StatCard title="Total Cargado" :value="formatCurrency(statement.summary.charged_total)" />
                    <StatCard title="Pagado (filtro)" :value="formatCurrency(statement.summary.paid_in_filter_total)" />
                    <StatCard title="Pagado histórico" :value="formatCurrency(statement.summary.paid_total)" />
                    <StatCard title="Saldo pendiente" :value="formatCurrency(statement.summary.outstanding_total)" />
                </div>

                <div class="rounded-lg border border-slate-200 p-4 mb-6 bg-slate-50 text-sm">
                    <p class="text-slate-800 font-medium">{{ statement.owner.name }} ({{ statement.owner.uf }})</p>
                    <p class="text-slate-600">{{ statement.owner.email || 'Sin email cargado' }}</p>
                    <p class="text-slate-600 mt-1">Filtro aplicado: {{ statement.filter_label }}</p>
                </div>

                <div class="overflow-x-auto border border-slate-200 rounded-lg mb-6">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-left">Periodo</th>
                                <th class="px-4 py-3 text-right">Mensual</th>
                                <th class="px-4 py-3 text-right">Extra.</th>
                                <th class="px-4 py-3 text-right">Multas</th>
                                <th class="px-4 py-3 text-right">Cargado</th>
                                <th class="px-4 py-3 text-right">Pagado (filtro)</th>
                                <th class="px-4 py-3 text-right">Pagado total</th>
                                <th class="px-4 py-3 text-right">Pendiente</th>
                                <th class="px-4 py-3 text-left">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-for="row in statement.charges" :key="row.period">
                                <td class="px-4 py-3">{{ row.period }}</td>
                                <td class="px-4 py-3 text-right">{{ formatCurrency(row.monthly) }}</td>
                                <td class="px-4 py-3 text-right">{{ formatCurrency(row.extraordinary) }}</td>
                                <td class="px-4 py-3 text-right">{{ formatCurrency(row.fines) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ formatCurrency(row.charged) }}</td>
                                <td class="px-4 py-3 text-right">{{ formatCurrency(row.paid_in_filter) }}</td>
                                <td class="px-4 py-3 text-right">{{ formatCurrency(row.paid_total) }}</td>
                                <td class="px-4 py-3 text-right font-semibold"
                                    :class="row.outstanding > 0 ? 'text-red-600' : 'text-emerald-600'">
                                    {{ formatCurrency(row.outstanding) }}
                                </td>
                                <td class="px-4 py-3">{{ row.status }}</td>
                            </tr>
                            <tr v-if="!statement.charges.length">
                                <td colspan="9" class="px-4 py-6 text-center text-slate-500">
                                    No hay cargos para el filtro aplicado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="overflow-x-auto border border-slate-200 rounded-lg">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-left">Fecha</th>
                                <th class="px-4 py-3 text-left">Periodo imputado</th>
                                <th class="px-4 py-3 text-left">Metodo</th>
                                <th class="px-4 py-3 text-left">Referencia</th>
                                <th class="px-4 py-3 text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-for="(payment, index) in statement.payments" :key="`${payment.date}-${index}`">
                                <td class="px-4 py-3">{{ payment.date }}</td>
                                <td class="px-4 py-3">{{ payment.period || '-' }}</td>
                                <td class="px-4 py-3">{{ payment.method }}</td>
                                <td class="px-4 py-3">{{ payment.reference || '-' }}</td>
                                <td class="px-4 py-3 text-right font-medium">{{ formatCurrency(payment.amount) }}</td>
                            </tr>
                            <tr v-if="!statement.payments.length">
                                <td colspan="5" class="px-4 py-6 text-center text-slate-500">
                                    No hay pagos en el filtro aplicado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-else class="py-10 text-center text-slate-500">
                No hay propietarios cargados para el barrio seleccionado.
            </div>
        </Card>
    </DashboardLayout>
</template>
