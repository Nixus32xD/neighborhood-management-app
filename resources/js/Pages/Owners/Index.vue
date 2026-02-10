<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import Card from '@/Components/Card.vue'
import DataTable from '@/Components/DataTable.vue'
import Button from '@/Components/Button.vue'
import Modal from '@/Components/Modal.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import FormInput from '@/Components/FormInput.vue'
import FormTextarea from '@/Components/FormTextarea.vue'
import { Plus, Pencil, Trash2, Eye, Users } from 'lucide-vue-next'

const props = defineProps({
    owners: {
        type: Array,
    },
    residentRelations: Array
})

const columns = [
    { key: 'uf_number', label: 'UF Number' },
    { key: 'name', label: 'Owner Name' },
    { key: 'email', label: 'Email' },
    { key: 'residents', label: 'Residents' }
]

// Modal states
const showCreateModal = ref(false)
const showEditModal = ref(false)
const showViewModal = ref(false)
const showDeleteDialog = ref(false)
const selectedOwner = ref(null)

// Form for create/edit
const form = useForm({
    uf_number: '',
    name: '',
    email: '',
    residents: [
        { name: '', relation: 'owner' }
    ]
})

const openCreateModal = () => {
    form.reset()
    form.clearErrors()
    showCreateModal.value = true
}

const openEditModal = (owner) => {
    selectedOwner.value = owner
    form.uf_number = owner.uf_number
    form.name = owner.name
    form.email = owner.email
    form.residents = owner.residents.map(r => ({
        name: r.name,
        relation: r.relation
    }))
    form.clearErrors()
    showEditModal.value = true
}

const openViewModal = (owner) => {
    selectedOwner.value = owner
    showViewModal.value = true
}

const openDeleteDialog = (owner) => {
    selectedOwner.value = owner
    showDeleteDialog.value = true
}

const submitCreate = () => {
    form.post('/owners', {
        onSuccess: () => {
            showCreateModal.value = false
            form.reset()
        }
    })
}

const submitEdit = () => {
    form.put(`/owners/${selectedOwner.value.id}`, {
        onSuccess: () => {
            showEditModal.value = false
            form.reset()
        }
    })
}

const confirmDelete = () => {
    router.delete(`/owners/${selectedOwner.value.id}`, {
        onSuccess: () => {
            showDeleteDialog.value = false
            selectedOwner.value = null
        }
    })
}

const addResident = () => {
    form.residents.push({ name: '', relation: 'other' })
}

const removeResident = (index) => {
    form.residents.splice(index, 1)
}
</script>

<template>
    <DashboardLayout title="Dueños / Propietarios">
        <Card title="Unidades funcionales (UF)" subtitle="Administrar propietarios y sus propiedades">
            <template #header-actions>
                <Button @click="openCreateModal">
                    <Plus class="w-4 h-4" />
                    Agregar propietario
                </Button>
            </template>

            <template #default>
                <DataTable :columns="columns" :data="owners" empty-title="No se encontraron propietarios"
                    empty-description="Añade tu primer propietario para comenzar.">
                    <template #cell-residents="{ row }">
                        <div class="flex items-center gap-1">
                            <Users class="w-4 h-4 text-slate-400" />
                            <span>{{ row.residents.length }}</span>
                        </div>
                    </template>

                    <template #actions="{ row }">
                        <div class="flex items-center justify-end gap-2">
                            <button
                                class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded transition-colors"
                                title="View details" @click="openViewModal(row)">
                                <Eye class="w-4 h-4" />
                            </button>
                            <button
                                class="p-1.5 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors"
                                title="Edit" @click="openEditModal(row)">
                                <Pencil class="w-4 h-4" />
                            </button>
                            <button
                                class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
                                title="Delete" @click="openDeleteDialog(row)">
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>

        <!-- Create Modal -->
        <Modal :show="showCreateModal" title="Añadir Nuevo Propietario" max-width="lg" @close="showCreateModal = false">
            <form @submit.prevent="submitCreate" class="space-y-4">
                <FormInput v-model="form.uf_number" label="Numero del UF" placeholder="e.g., UF-101"
                    :error="form.errors.uf_number" required />
                <FormInput v-model="form.name" label="Nombre Completo del Propietario"
                    placeholder="Ingrese el nombre completo" :error="form.errors.name" required />
                <FormInput v-model="form.email" label="Correo (Gmail)" type="email" placeholder="example@gmail.com"
                    :error="form.errors.email" required />
                <div class="space-y-3">
                    <div v-for="(resident, index) in form.residents" :key="index" class="flex gap-2 items-end">
                        <FormInput v-model="resident.name" label="Nombres del Residente" placeholder="Nombre Completo"
                            class="flex-1" />

                        <select v-model="resident.relation" class="border rounded px-2 py-2 text-sm">
                            <option v-for="rel in $page.props.residentRelations" :key="rel.value" :value="rel.value">
                                {{ rel.label }}
                            </option>
                        </select>

                        <button v-if="form.residents.length > 1" type="button" class="text-red-500 text-sm px-2"
                            @click="removeResident(index)">
                            ✕
                        </button>
                    </div>

                    <Button type="button" variant="secondary" @click="addResident">
                        + Añadir residente
                    </Button>
                </div>

            </form>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button variant="secondary" @click="showCreateModal = false">
                        Cancelar
                    </Button>
                    <Button :loading="form.processing" @click="submitCreate">
                        Cargar Propietario
                    </Button>
                </div>
            </template>
        </Modal>

        <!-- Edit Modal -->
        <Modal :show="showEditModal" title="Editar Propietario" max-width="lg" @close="showEditModal = false">
            <form @submit.prevent="submitEdit" class="space-y-4">
                <FormInput v-model="form.uf_number" label="Numero del UF" placeholder="e.g., UF-101"
                    :error="form.errors.uf_number" required />
                <FormInput v-model="form.name" label="Nombre Completo del Propietario"
                    placeholder="Ingrese el nombre completo" :error="form.errors.name" required />
                <FormInput v-model="form.email" label="Correo (Gmail)" type="email" placeholder="example@gmail.com"
                    :error="form.errors.email" required />
                <div class="space-y-3">
                    <div v-for="(resident, index) in form.residents" :key="index" class="flex gap-2 items-end">
                        <FormInput v-model="resident.name" label="Nombre del Residente" placeholder="Full name"
                            class="flex-1" />

                        <select v-model="resident.relation" class="border rounded px-2 py-2 text-sm">
                            <option v-for="rel in $page.props.residentRelations" :key="rel.value" :value="rel.value">
                                {{ rel.label }}
                            </option>
                        </select>



                        <button v-if="form.residents.length > 1" type="button" class="text-red-500 text-sm px-2"
                            @click="removeResident(index)">
                            ✕
                        </button>
                    </div>

                    <Button type="button" variant="secondary" @click="addResident">
                        + Añadir residente
                    </Button>
                </div>

            </form>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button variant="secondary" @click="showEditModal = false">
                        Cancelar
                    </Button>
                    <Button :loading="form.processing" @click="submitEdit">
                        Guardar Cambios
                    </Button>
                </div>
            </template>
        </Modal>

        <!-- View Modal -->
        <Modal :show="showViewModal" :title="`Detalles del Propietario - ${selectedOwner?.uf_number}`" max-width="lg"
            @close="showViewModal = false">
            <div v-if="selectedOwner" class="space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Numero de la Unidad</p>
                        <p class="mt-1 text-slate-900">{{ selectedOwner.uf_number }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500">Nombre del Propietario/a</p>
                        <p class="mt-1 text-slate-900">{{ selectedOwner.name }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-sm font-medium text-slate-500">Correo</p>
                        <p class="mt-1 text-slate-900">{{ selectedOwner.email }}</p>
                    </div>
                </div>

                <div>
                    <p class="text-sm font-medium text-slate-500 mb-2">Residentes</p>
                    <div class="border border-slate-200 rounded-lg divide-y divide-slate-200">
                        <div v-for="(resident, index) in selectedOwner.residents" :key="index"
                            class="flex items-center justify-between px-4 py-3">
                            <span class="text-slate-900">{{ resident.name }}</span>
                            <span class="text-sm text-slate-500">{{ resident.relation }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end">
                    <Button variant="secondary" @click="showViewModal = false">
                        Cerrar
                    </Button>
                </div>
            </template>
        </Modal>

        <!-- Delete Confirmation -->
        <ConfirmDialog :show="showDeleteDialog" title="Eliminar Propietario"
            :message="`¿Estás seguro de que quieres eliminar a ${selectedOwner?.name}? Esta acción no se puede deshacer.`"
            confirm-text="Eliminar" @confirm="confirmDelete" @cancel="showDeleteDialog = false"
            @close="showDeleteDialog = false" />
    </DashboardLayout>
</template>
