<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import Button from '@/Components/Button.vue'
import { Building2, ChevronRight } from 'lucide-vue-next'
import { computed, ref } from 'vue'

const { neighborhoods } = defineProps({
    neighborhoods: Array,
})


const page = usePage()
const user = computed(() => page.props.auth?.user)

const selected = ref(null)
const form = useForm({
    neighborhood: ''
})

const selectNeighborhood = (neighborhood) => {
    selected.value = neighborhood.id
    form.neighborhood = neighborhood.id
}

const submit = () => {
    if (!form.neighborhood) return
    form.post('/select-neighborhood')
}
</script>

<template>
    <AuthLayout>
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-emerald-100 rounded-full mb-4">
                <Building2 class="w-6 h-6 text-emerald-600" />
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Seleccionar Barrio</h1>
            <p class="mt-2 text-sm text-slate-500">
                Bienvenido de nuevo, {{ user?.name || 'User' }}! Elige qué barrio quieres gestionar.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="space-y-3">
                <button v-for="neighborhood in neighborhoods" :key="neighborhood.id" type="button" :class="[
                    'w-full flex items-center justify-between p-4 border rounded-lg transition-all text-left',
                    selected === neighborhood.id
                        ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-200'
                        : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'
                ]" @click="selectNeighborhood(neighborhood)">
                    <div class="flex items-center gap-3">
                        <div :class="[
                            'w-10 h-10 rounded-lg flex items-center justify-center',
                            selected === neighborhood.id ? 'bg-emerald-600' : 'bg-slate-200'
                        ]">
                            <Building2 :class="[
                                'w-5 h-5',
                                selected === neighborhood.id ? 'text-white' : 'text-slate-500'
                            ]" />
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900">{{ neighborhood.name }}</p>
                            <p class="text-sm text-slate-500">{{ neighborhood.description }}</p>
                        </div>
                    </div>
                    <ChevronRight :class="[
                        'w-5 h-5',
                        selected === neighborhood.id ? 'text-emerald-600' : 'text-slate-400'
                    ]" />
                </button>
            </div>

            <Button type="submit" :loading="form.processing" :disabled="!form.neighborhood" class="w-full">
                Continuar al panel
            </Button>
        </form>
    </AuthLayout>
</template>
