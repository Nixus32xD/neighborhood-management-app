<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
    Home,
    Users,
    DollarSign,
    CreditCard,
    Wallet,
    LogOut,
    Building2,
    Menu,
    X,
    LandPlot,
    FileSpreadsheet,
    HandCoins
} from 'lucide-vue-next'
import { ref } from 'vue'

const sidebarOpen = ref(false)

const props = defineProps({
    title: {
        type: String,
        default: 'Dashboard'
    }
})

const page = usePage()
const user = computed(() => page.props.auth?.user)
const neighborhood = computed(() => page.props.neighborhood)

const selectedNeighborhood = computed(() =>
    neighborhood.value?.name ?? '—'
)



const navigation = [
    { name: 'Panel', href: '/dashboard', icon: Home },
    { name: 'Expensas y Honorarios', href: '/expenses', icon: DollarSign },
    { name: 'Pagos y Movimientos', href: '/payments', icon: CreditCard },
    { name: 'Planes de Pago', href: '/payment-plans', icon: HandCoins },
    { name: 'Estado de Cuenta', href: '/owner-statements', icon: FileSpreadsheet },
    { name: 'Propietarios', href: '/owners', icon: Users },
    { name: 'Lotes', href: '/lots', icon: LandPlot },
    { name: 'Metodos de Cobro', href: '/payment-methods', icon: Wallet },
]
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <!-- Mobile sidebar backdrop -->
        <div v-if="sidebarOpen" class="fixed inset-0 bg-slate-900/50 z-40 lg:hidden" @click="sidebarOpen = false" />

        <!-- Sidebar -->
        <aside :class="[
            'fixed top-0 left-0 z-50 h-full w-64 bg-slate-900 text-white transform transition-transform duration-200 lg:translate-x-0',
            sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        ]">
            <div class="flex items-center justify-between h-16 px-4 border-b border-slate-700">
                <div class="flex items-center gap-2">
                    <Building2 class="w-6 h-6 text-emerald-400" />
                    <span class="font-semibold text-lg">NeighborAdmin</span>
                </div>
                <button class="lg:hidden p-1 hover:bg-slate-800 rounded" @click="sidebarOpen = false">
                    <X class="w-5 h-5" />
                </button>
            </div>

            <nav class="mt-6 px-3">
                <ul class="space-y-1">
                    <li v-for="item in navigation" :key="item.name">
                        <Link :href="item.href"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition-colors">
                            <component :is="item.icon" class="w-5 h-5" />
                            <span>{{ item.name }}</span>
                        </Link>
                    </li>
                </ul>
            </nav>

            <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-slate-700">
                <Link href="/logout" method="post" as="button"
                    class="flex items-center gap-3 w-full px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition-colors">
                    <LogOut class="w-5 h-5" />
                    <span>Desconectarse</span>
                </Link>
            </div>
        </aside>

        <!-- Main content -->
        <div class="lg:pl-64">
            <!-- Top bar -->
            <header class="sticky top-0 z-30 bg-white border-b border-slate-200">
                <div class="flex items-center justify-between h-16 px-4 lg:px-6">
                    <div class="flex items-center gap-4">
                        <button class="lg:hidden p-2 hover:bg-slate-100 rounded-lg" @click="sidebarOpen = true">
                            <Menu class="w-5 h-5 text-slate-600" />
                        </button>
                        <h1 class="text-lg font-semibold text-slate-900">{{ title }}</h1>
                    </div>

                    <div class="flex items-center gap-4">
                        <!-- Neighborhood indicator -->
                        <div
                            class="flex items-center gap-2 px-3 py-1.5 bg-emerald-50 text-emerald-700 rounded-full text-sm font-medium">
                            <Building2 class="w-4 h-4" />
                            <span>{{ selectedNeighborhood }}</span>
                        </div>

                        <!-- User info -->
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-slate-200 rounded-full flex items-center justify-center">
                                <span class="text-sm font-medium text-slate-600">
                                    {{ user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                                </span>
                            </div>
                            <span class="hidden sm:block text-sm font-medium text-slate-700">
                                {{ user?.name || 'User' }}
                            </span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page content -->
            <main class="p-4 lg:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>


