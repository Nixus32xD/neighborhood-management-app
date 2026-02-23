<script setup>
import { ref, computed } from 'vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import StatCard from '@/Components/StatCard.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Modal from '@/Components/Modal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import {
    Building2,
    Ruler,
    BarChart3,
    Percent,
    ChevronUp,
    ChevronDown,
    Search,
    X,
    User,
    Users,
    FileText,
    MapPin
} from 'lucide-vue-next'

// Props from backend (already filtered by neighborhood)
const props = defineProps({
    lots: {
        type: Array,
        default: () => []
    },
    stats: {
        type: Object,
        default: () => ({
            totalLots: 0,
            totalSurface: 0,
            averageSurface: 0,
            totalCoefficient: 100
        })
    }
})

// Filters
const searchQuery = ref('')
const statusFilter = ref('all')
const surfaceMin = ref('')
const surfaceMax = ref('')

// Sorting
const sortColumn = ref('uf_number')
const sortDirection = ref('asc')

// Modal
const showDetailModal = ref(false)
const selectedLot = ref(null)

// Filter options
const statusOptions = [
    { value: 'all', label: 'All Status' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' }
]

// Computed: filtered and sorted lots
const filteredLots = computed(() => {
    let result = [...props.lots]

    // Search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase()
        result = result.filter(lot =>
            lot.uf_number?.toString().toLowerCase().includes(query) ||
            lot.owner_name?.toLowerCase().includes(query)
        )
    }

    // Status filter
    if (statusFilter.value !== 'all') {
        result = result.filter(lot => lot.status === statusFilter.value)
    }

    // Surface range filter
    if (surfaceMin.value !== '') {
        result = result.filter(lot => lot.surface_area >= Number(surfaceMin.value))
    }
    if (surfaceMax.value !== '') {
        result = result.filter(lot => lot.surface_area <= Number(surfaceMax.value))
    }

    // Sorting
    result.sort((a, b) => {
        let aVal = a[sortColumn.value]
        let bVal = b[sortColumn.value]

        // Si ordenamos por UF, usamos orden natural para que "2" vaya antes que "10"
        if (sortColumn.value === 'uf_number') {
            return sortDirection.value === 'asc'
                ? String(aVal).localeCompare(String(bVal), undefined, { numeric: true, sensitivity: 'base' })
                : String(bVal).localeCompare(String(aVal), undefined, { numeric: true, sensitivity: 'base' });
        }

        // Para el resto (superficie, porcentaje, etc)
        if (typeof aVal === 'number' && typeof bVal === 'number') {
            return sortDirection.value === 'asc' ? aVal - bVal : bVal - aVal
        }

        // Strings genéricos (nombres)
        aVal = String(aVal || '').toLowerCase()
        bVal = String(bVal || '').toLowerCase()

        return sortDirection.value === 'asc'
            ? aVal.localeCompare(bVal)
            : bVal.localeCompare(aVal)
    })

    return result
})

// Highlight thresholds (lots with unusually high values)
const surfaceThreshold = computed(() => {
    if (props.lots.length === 0) return 0
    const surfaces = props.lots.map(l => l.surface_area)
    const avg = surfaces.reduce((a, b) => a + b, 0) / surfaces.length
    return avg * 1.5 // 50% above average
})

const percentageThreshold = computed(() => {
    if (props.lots.length === 0) return 0
    const percentages = props.lots.map(l => l.expense_percentage)
    const avg = percentages.reduce((a, b) => a + b, 0) / percentages.length
    return avg * 1.5 // 50% above average
})

// Methods
const toggleSort = (column) => {
    if (sortColumn.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortColumn.value = column
        sortDirection.value = 'asc'
    }
}

const getSortIcon = (column) => {
    if (sortColumn.value !== column) return null
    return sortDirection.value === 'asc' ? ChevronUp : ChevronDown
}

const openLotDetail = (lot) => {
    selectedLot.value = lot
    showDetailModal.value = true
}

const closeDetailModal = () => {
    showDetailModal.value = false
    selectedLot.value = null
}

const clearFilters = () => {
    searchQuery.value = ''
    statusFilter.value = 'all'
    surfaceMin.value = ''
    surfaceMax.value = ''
}

const hasActiveFilters = computed(() => {
    return searchQuery.value !== '' ||
        statusFilter.value !== 'all' ||
        surfaceMin.value !== '' ||
        surfaceMax.value !== ''
})

const isHighSurface = (surface) => surface > surfaceThreshold.value
const isHighPercentage = (percentage) => percentage > percentageThreshold.value

const formatNumber = (num, decimals = 2) => {
    return Number(num).toLocaleString('es-AR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    })
}

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS'
    }).format(amount)
}
</script>

<template>
    <DashboardLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Lotes / Unidad Funcionales</h1>
                <p class="mt-1 text-sm text-slate-500">Descripción general de la propiedad y coeficientes de gastos</p>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard title="Lotes Totales" :value="stats.totalLots" :icon="Building2" />
                <StatCard title="Área de superficie total" :value="`${formatNumber(stats.totalSurface, 2)} m²`"
                    :icon="Ruler" />
                <StatCard title="Tamaño promedio del lote" :value="`${formatNumber(stats.averageSurface, 2)} m²`"
                    :icon="BarChart3" />
                <StatCard title="Coeficiente total" :value="`${formatNumber(stats.totalCoefficient, 2)}%`"
                    :icon="Percent" :subtitle="stats.totalCoefficient === 100 ? 'Equilibrado' : 'Revisión necesaria'" />
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg border border-slate-200 p-4">
                <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                    <!-- Search -->
                    <div class="relative flex-1 max-w-sm">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                        <input v-model="searchQuery" type="text" placeholder="Buscar por UF o propietario..."
                            class="w-full pl-9 pr-4 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-colors" />
                    </div>

                    <!-- Status Filter -->
                    <select v-model="statusFilter"
                        class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                        <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>

                    <!-- Surface Range -->
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-slate-500 whitespace-nowrap">Superficie:</span>
                        <input v-model="surfaceMin" type="number" placeholder="Min"
                            class="w-20 px-2 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                        <span class="text-slate-400">-</span>
                        <input v-model="surfaceMax" type="number" placeholder="Max"
                            class="w-20 px-2 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
                        <span class="text-sm text-slate-500">m²</span>
                    </div>

                    <!-- Clear Filters -->
                    <button v-if="hasActiveFilters" @click="clearFilters"
                        class="flex items-center gap-1 px-3 py-2 text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-colors">
                        <X class="w-4 h-4" />
                        Limpiar
                    </button>
                </div>

                <!-- Results count -->
                <div class="mt-3 text-sm text-slate-500">
                    Mostrando {{ filteredLots.length }} de {{ lots.length }} lotes
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th @click="toggleSort('uf_number')"
                                    class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider cursor-pointer hover:bg-slate-100 transition-colors">
                                    <div class="flex items-center gap-1">
                                        Lote / UF
                                        <component :is="getSortIcon('uf_number')" v-if="getSortIcon('uf_number')"
                                            class="w-4 h-4" />
                                    </div>
                                </th>
                                <th @click="toggleSort('owner_name')"
                                    class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider cursor-pointer hover:bg-slate-100 transition-colors">
                                    <div class="flex items-center gap-1">
                                        Propietario
                                        <component :is="getSortIcon('owner_name')" v-if="getSortIcon('owner_name')"
                                            class="w-4 h-4" />
                                    </div>
                                </th>
                                <th @click="toggleSort('surface_area')"
                                    class="px-4 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider cursor-pointer hover:bg-slate-100 transition-colors">
                                    <div class="flex items-center justify-end gap-1">
                                        Superficie
                                        <component :is="getSortIcon('surface_area')" v-if="getSortIcon('surface_area')"
                                            class="w-4 h-4" />
                                    </div>
                                </th>
                                <th @click="toggleSort('expense_percentage')"
                                    class="px-4 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider cursor-pointer hover:bg-slate-100 transition-colors">
                                    <div class="flex items-center justify-end gap-1">
                                        Expensa %
                                        <component :is="getSortIcon('expense_percentage')"
                                            v-if="getSortIcon('expense_percentage')" class="w-4 h-4" />
                                    </div>
                                </th>
                                <th @click="toggleSort('base_expense')"
                                    class="px-4 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider cursor-pointer hover:bg-slate-100 transition-colors">
                                    <div class="flex items-center justify-end gap-1">
                                        Gasto Base Estimado
                                        <component :is="getSortIcon('base_expense')" v-if="getSortIcon('base_expense')"
                                            class="w-4 h-4" />
                                    </div>
                                </th>
                                <th @click="toggleSort('status')"
                                    class="px-4 py-3 text-center text-xs font-semibold text-slate-600 uppercase tracking-wider cursor-pointer hover:bg-slate-100 transition-colors">
                                    <div class="flex items-center justify-center gap-1">
                                        Estado
                                        <component :is="getSortIcon('status')" v-if="getSortIcon('status')"
                                            class="w-4 h-4" />
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <template v-if="filteredLots.length > 0">
                                <tr v-for="lot in filteredLots" :key="lot.id" @click="openLotDetail(lot)"
                                    class="hover:bg-slate-50 transition-colors cursor-pointer">
                                    <td class="px-4 py-3">
                                        <span class="text-sm font-medium text-slate-900">{{ lot.uf_number }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-sm text-slate-700">{{ lot.owner_name }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span :class="[
                                            'text-sm font-medium',
                                            isHighSurface(lot.surface_area) ? 'text-amber-600 bg-amber-50 px-2 py-0.5 rounded' : 'text-slate-700'
                                        ]">
                                            {{ formatNumber(lot.surface_area, 2) }} m²
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span :class="[
                                            'text-sm font-semibold',
                                            isHighPercentage(lot.expense_percentage) ? 'text-blue-600 bg-blue-50 px-2 py-0.5 rounded' : 'text-slate-900'
                                        ]">
                                            {{ formatNumber(lot.expense_percentage, 4) }}%
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="text-sm text-slate-500">{{ formatCurrency(lot.base_expense)
                                        }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">

                                        <StatusBadge :status="lot.status === 'active' ? 'Active' : 'Inactive'"
                                            :variant="lot.status === 'active' ? 'success' : 'default'" />
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <EmptyState v-if="filteredLots.length === 0" title="No se encontraron lotes"
                    :description="hasActiveFilters ? 'Intente ajustar sus filtros.' : 'No hay lotes para mostrar.'"
                    class="py-12" />
            </div>
        </div>

        <!-- Lot Detail Modal -->
        <Modal :show="showDetailModal" :title="`Detalles del ${selectedLot?.uf_number}`" max-width="lg"
            @close="closeDetailModal">
            <div v-if="selectedLot" class="space-y-6">
                <!-- Owner Information -->
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <User class="w-4 h-4 text-slate-500" />
                        <h4 class="text-sm font-semibold text-slate-900 uppercase tracking-wide">Información del
                            propietario
                        </h4>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-4 space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-500">Nombre</span>
                            <span class="text-sm font-medium text-slate-900">{{ selectedLot.owner_name }}</span>
                        </div>
                        <div v-if="selectedLot.owner_email" class="flex justify-between">
                            <span class="text-sm text-slate-500">Correo</span>
                            <span class="text-sm text-slate-700">{{ selectedLot.owner_email }}</span>
                        </div>
                        <div v-if="selectedLot.owner_phone" class="flex justify-between">
                            <span class="text-sm text-slate-500">Telefono</span>
                            <span class="text-sm text-slate-700">{{ selectedLot.owner_phone }}</span>
                        </div>
                        <div v-if="selectedLot.owner_dni" class="flex justify-between">
                            <span class="text-sm text-slate-500">DNI/CUIT</span>
                            <span class="text-sm text-slate-700">{{ selectedLot.owner_dni }}</span>
                        </div>
                    </div>
                </div>

                <!-- Residents -->
                <div v-if="selectedLot.residents && selectedLot.residents.length > 0">
                    <div class="flex items-center gap-2 mb-3">
                        <Users class="w-4 h-4 text-slate-500" />
                        <h4 class="text-sm font-semibold text-slate-900 uppercase tracking-wide">Residentes</h4>
                    </div>
                    <div class="bg-slate-50 rounded-lg divide-y divide-slate-200">
                        <div v-for="resident in selectedLot.residents" :key="resident.id"
                            class="p-3 flex justify-between items-center">
                            <span class="text-sm text-slate-700">{{ resident.name }}</span>
                            <span class="text-xs text-slate-500 bg-slate-200 px-2 py-0.5 rounded">{{ resident.relation
                            }}</span>
                        </div>
                    </div>
                </div>

                <!-- Lot Dimensions -->
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <MapPin class="w-4 h-4 text-slate-500" />
                        <h4 class="text-sm font-semibold text-slate-900 uppercase tracking-wide">Detalles de la
                            propiedad</h4>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-4 grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-xs text-slate-500 block">Área de Superficie</span>
                            <span class="text-sm font-medium text-slate-900">{{ formatNumber(selectedLot.surface_area,
                                2) }}
                                m²</span>
                        </div>
                        <div>
                            <span class="text-xs text-slate-500 block">Coeficiente de Expensas</span>
                            <span class="text-sm font-medium text-slate-900">{{
                                formatNumber(selectedLot.expense_percentage, 4)
                            }}%</span>
                        </div>
                        <div v-if="selectedLot.dimensions">
                            <span class="text-xs text-slate-500 block">Dimensiones</span>
                            <span class="text-sm font-medium text-slate-900">{{ selectedLot.dimensions }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-slate-500 block">Gasto Base Estimado</span>
                            <span class="text-sm font-medium text-slate-900">{{ formatCurrency(selectedLot.base_expense)
                            }}</span>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div v-if="selectedLot.notes">
                    <div class="flex items-center gap-2 mb-3">
                        <FileText class="w-4 h-4 text-slate-500" />
                        <h4 class="text-sm font-semibold text-slate-900 uppercase tracking-wide">Notas</h4>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-4">
                        <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ selectedLot.notes }}</p>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end">
                    <button @click="closeDetailModal"
                        class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                        Cerrar
                    </button>
                </div>
            </template>
        </Modal>
    </DashboardLayout>
</template>
