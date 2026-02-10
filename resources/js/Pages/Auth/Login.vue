<script setup>
import { useForm } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import Button from '@/Components/Button.vue'
import { Building2 } from 'lucide-vue-next'

const form = useForm({
    email: '',
    password: '',
    remember: false
})

defineProps({
    errors: {
        type: Object,
        default: () => ({})
    }
})

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password')
    })
}
</script>

<template>
    <AuthLayout>
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-emerald-100 rounded-full mb-4">
                <Building2 class="w-6 h-6 text-emerald-600" />
            </div>
            <h1 class="text-2xl font-bold text-slate-900">NeighborAdmin</h1>
            <p class="mt-2 text-sm text-slate-500">Inicia sesión para administrar tu vecindario</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <FormInput v-model="form.email" label="Correo" type="email" placeholder="you@example.com"
                :error="form.errors.email" required />

            <FormInput v-model="form.password" label="Contraseña" type="password" placeholder="Enter your password"
                :error="form.errors.password" required />

            <div class="flex items-center justify-between">
                <label class="flex items-center">
                    <input v-model="form.remember" type="checkbox"
                        class="w-4 h-4 border-slate-300 rounded text-emerald-600 focus:ring-emerald-500" />
                    <span class="ml-2 text-sm text-slate-600">Recordar</span>
                </label>
            </div>

            <Button type="submit" :loading="form.processing" class="w-full">
                Iniciar sesión
            </Button>
        </form>
    </AuthLayout>
</template>
