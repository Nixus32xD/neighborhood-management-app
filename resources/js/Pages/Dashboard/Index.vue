<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import StatCard from '@/Components/StatCard.vue'
import Card from '@/Components/Card.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Users, Home, AlertTriangle, TrendingUp, TrendingDown } from 'lucide-vue-next'

defineProps({
    stats: {
        type: Object,
        default: () => ({
            totalOwners: 0,
            totalUnits: 0,
            totalCollected: 0,
            totalOutstanding: 0,
            monthlyBalance: 0
        })
    },
    recentPayments: {
        type: Array,
        default: () => []
    },
    overdueUnits: {
        type: Array,
        default: () => []
    }
})

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        minimumFractionDigits: 0
    }).format(amount)
}

const getStatusVariant = (status) => {
    const variants = {
        paid: 'success',
        pending: 'warning',
        overdue: 'danger'
    }
    return variants[status] || 'default'
}
</script>

<template>
    <DashboardLayout title="Panel">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <StatCard title="Propietarios totales" :value="stats.totalOwners" :icon="Users" />
            <StatCard title="Unidades funcionales" :value="stats.totalUnits" :icon="Home" />
            <StatCard title="Total recaudado" :value="formatCurrency(stats.totalCollected)" :icon="TrendingUp" />
            <StatCard title="Deuda pendiente" :value="formatCurrency(stats.totalOutstanding)" :icon="TrendingDown" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <Card title="Pagos recientes" subtitle="Ultima actividad de pago">
                <div v-if="recentPayments.length" class="divide-y divide-slate-200 -mx-6 -my-4">
                    <div v-for="payment in recentPayments" :key="payment.id"
                        class="flex items-center justify-between px-6 py-3">
                        <div>
                            <p class="font-medium text-slate-900">{{ payment.uf }}</p>
                            <p class="text-sm text-slate-500">{{ payment.owner }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-medium text-slate-900">{{ formatCurrency(payment.amount) }}</p>
                            <StatusBadge :status="payment.status" :variant="getStatusVariant(payment.status)" />
                        </div>
                    </div>
                </div>
                <div v-else class="text-sm text-slate-500">
                    No hay pagos recientes para mostrar.
                </div>
            </Card>

            <Card title="Unidades vencidas" subtitle="Unidades con pagos pendientes">
                <template #header-actions>
                    <span class="inline-flex items-center gap-1 text-sm text-red-600">
                        <AlertTriangle class="w-4 h-4" />
                        {{ overdueUnits.length }} unidades
                    </span>
                </template>

                <div v-if="overdueUnits.length" class="divide-y divide-slate-200 -mx-6 -my-4">
                    <div v-for="unit in overdueUnits" :key="unit.id"
                        class="flex items-center justify-between px-6 py-3">
                        <div>
                            <p class="font-medium text-slate-900">{{ unit.uf }}</p>
                            <p class="text-sm text-slate-500">{{ unit.owner }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-medium text-red-600">{{ formatCurrency(unit.amount) }}</p>
                            <p v-if="unit.months === 1" class="text-sm text-slate-500">{{ unit.months }} mes de retraso</p>
                            <p v-else class="text-sm text-slate-500">{{ unit.months }} meses de retraso</p>
                        </div>
                    </div>
                </div>
                <div v-else class="text-sm text-slate-500">
                    No hay unidades vencidas para mostrar.
                </div>
            </Card>
        </div>

        <Card title="Saldo mensual" subtitle="Resumen financiero del mes actual" class="mt-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center p-4 bg-emerald-50 rounded-lg">
                    <p class="text-sm font-medium text-emerald-600">Total recaudado</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ formatCurrency(stats.totalCollected) }}</p>
                </div>
                <div class="text-center p-4 bg-red-50 rounded-lg">
                    <p class="text-sm font-medium text-red-600">Pendiente</p>
                    <p class="mt-1 text-2xl font-bold text-red-700">{{ formatCurrency(stats.totalOutstanding) }}</p>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <p class="text-sm font-medium text-blue-600">Saldo neto</p>
                    <p class="mt-1 text-2xl font-bold text-blue-700">{{ formatCurrency(stats.monthlyBalance) }}</p>
                </div>
            </div>
        </Card>
    </DashboardLayout>
</template>

