<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import Card from '@/Components/Card.vue'
import Button from '@/Components/Button.vue'
import Modal from '@/Components/Modal.vue'
import FormInput from '@/Components/FormInput.vue'
import FormSelect from '@/Components/FormSelect.vue'
import FormTextarea from '@/Components/FormTextarea.vue'

const props = defineProps({ plans: Array, filters: Object, eligibleUnits: Array })
const showCreate = ref(false)
const selectedUnitId = ref('')
const selectedAmounts = ref({})
const form = useForm({ unit_id: '', owner_id: '', items: [], total_amount: '', installments_count: 1, start_date: new Date().toISOString().slice(0, 10), notes: '' })
const unitOptions = computed(() => props.eligibleUnits.map(unit => ({ value: String(unit.unit_id), label: `${unit.uf_number} - ${unit.owners.map(owner => owner.full_name).join(', ')}` })))
const selectedUnit = computed(() => props.eligibleUnits.find(unit => String(unit.unit_id) === String(selectedUnitId.value)))
const ownerOptions = computed(() => selectedUnit.value?.owners.map(owner => ({ value: String(owner.id), label: owner.full_name })) || [])
const total = computed(() => Object.values(selectedAmounts.value).reduce((sum, amount) => sum + Number(amount || 0), 0))
const agreementTotal = computed(() => Number(form.total_amount || 0))
const financingCharge = computed(() => Math.max(0, agreementTotal.value - total.value))
const money = amount => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(amount || 0)
const statusLabel = status => ({ active: 'Activo', completed: 'Completado', cancelled: 'Cancelado' }[status] || status)

watch(selectedUnit, unit => {
  form.owner_id = unit?.owners?.[0]?.id ? String(unit.owners[0].id) : ''
  selectedAmounts.value = Object.fromEntries((unit?.items || []).map(item => [item.unit_expense_id, item.amount]))
  form.total_amount = total.value ? total.value.toFixed(2) : ''
})
watch(total, value => {
  if (!form.total_amount || Number(form.total_amount) < value) form.total_amount = value.toFixed(2)
})
const openCreate = () => { form.reset(); selectedUnitId.value = ''; selectedAmounts.value = {}; form.start_date = new Date().toISOString().slice(0, 10); form.installments_count = 1; showCreate.value = true }
const submit = () => {
  if (!selectedUnit.value) return
  form.unit_id = selectedUnit.value.unit_id
  form.items = selectedUnit.value.items.map(item => ({ unit_expense_id: item.unit_expense_id, amount: Number(selectedAmounts.value[item.unit_expense_id] || 0) })).filter(item => item.amount > 0)
  form.post(route('payment-plans.store'), { onSuccess: () => { showCreate.value = false } })
}
const filter = status => router.get(route('payment-plans.index'), { status, search: props.filters?.search || '' }, { preserveState: true })
</script>

<template>
  <DashboardLayout title="Planes de Pago">
    <Card title="Planes de pago" subtitle="Financiación de deuda existente sin alterar las expensas originales">
      <template #header-actions><Button @click="openCreate">Nuevo Plan de Pago</Button></template>
      <div class="mb-4 flex flex-wrap gap-2">
        <Button v-for="item in [['active','Activos'],['completed','Completados'],['cancelled','Cancelados'],['all','Todos']]" :key="item[0]" size="sm" :variant="filters?.status === item[0] ? 'primary' : 'secondary'" @click="filter(item[0])">{{ item[1] }}</Button>
      </div>
      <div class="overflow-x-auto rounded-lg border border-slate-200">
        <table class="w-full text-sm"><thead class="bg-slate-50"><tr><th class="p-3 text-left">UF</th><th class="p-3 text-left">Propietario</th><th class="p-3 text-right">Deuda incluida</th><th class="p-3 text-right">Recargo</th><th class="p-3 text-right">Total acuerdo</th><th class="p-3 text-right">Abonado</th><th class="p-3 text-right">Saldo</th><th class="p-3 text-center">Cuotas</th><th class="p-3 text-left">Próximo vencimiento</th><th class="p-3 text-left">Estado</th><th class="p-3"></th></tr></thead>
          <tbody class="divide-y"><tr v-for="plan in plans" :key="plan.id"><td class="p-3 font-medium">{{ plan.uf_number }}</td><td class="p-3">{{ plan.owner }}</td><td class="p-3 text-right">{{ money(plan.financed_debt_amount) }}</td><td class="p-3 text-right">{{ money(plan.financing_charge_amount) }}</td><td class="p-3 text-right">{{ money(plan.original_amount) }}</td><td class="p-3 text-right">{{ money(plan.paid_amount) }}</td><td class="p-3 text-right font-semibold">{{ money(plan.outstanding_amount) }}</td><td class="p-3 text-center">{{ plan.installments_paid }}/{{ plan.installments_count }}</td><td class="p-3">{{ plan.next_installment?.due_date || '-' }}</td><td class="p-3">{{ statusLabel(plan.status) }}</td><td class="p-3"><Link :href="route('payment-plans.show', plan.id)" class="text-emerald-700 hover:underline">Ver detalle</Link></td></tr><tr v-if="!plans?.length"><td colspan="11" class="p-8 text-center text-slate-500">No hay planes para el filtro seleccionado.</td></tr></tbody>
        </table>
      </div>
    </Card>
    <Modal :show="showCreate" title="Nuevo Plan de Pago" max-width="2xl" @close="showCreate = false">
      <form class="space-y-4" @submit.prevent="submit">
        <FormSelect v-model="selectedUnitId" label="Propietario / UF" :options="unitOptions" placeholder="Seleccione una unidad con deuda corriente" required />
        <FormSelect v-if="selectedUnit" v-model="form.owner_id" label="Propietario que celebra el acuerdo" :options="ownerOptions" />
        <div v-if="selectedUnit" class="overflow-hidden rounded-lg border border-slate-200"><table class="w-full text-sm"><thead class="bg-slate-50"><tr><th class="p-3 text-left">Período</th><th class="p-3 text-right">Deuda elegible</th><th class="p-3 text-right">Importe a financiar</th></tr></thead><tbody><tr v-for="item in selectedUnit.items" :key="item.unit_expense_id" class="border-t"><td class="p-3">{{ item.period }}</td><td class="p-3 text-right">{{ money(item.amount) }}</td><td class="p-3"><input v-model="selectedAmounts[item.unit_expense_id]" type="number" min="0" :max="item.amount" step="0.01" class="w-full rounded border-slate-300 text-right" /></td></tr></tbody></table></div>
        <p v-if="selectedUnit" class="text-right font-semibold">Deuda incluida en el plan: {{ money(total) }}</p>
        <FormInput v-model="form.total_amount" type="number" min="0.01" step="0.01" label="Total acordado a financiar" :error="form.errors.total_amount" required />
        <p v-if="selectedUnit" class="text-sm" :class="agreementTotal >= total ? 'text-slate-600' : 'text-red-600'">
          Recargo financiero: {{ money(financingCharge) }}. No se aplica a futuras expensas ni a deudas no seleccionadas.
        </p>
        <div class="grid gap-4 sm:grid-cols-2"><FormInput v-model="form.installments_count" type="number" min="1" max="120" label="Cantidad de cuotas" required /><FormInput v-model="form.start_date" type="date" label="Primer vencimiento" required /></div>
        <p v-if="agreementTotal && form.installments_count" class="text-sm text-slate-600">Cuota estimada: {{ money(agreementTotal / Number(form.installments_count)) }}; el ajuste de centavos se aplica en la última cuota.</p>
        <FormTextarea v-model="form.notes" label="Observaciones" :rows="2" />
        <p v-if="form.errors.items" class="text-sm text-red-600">{{ form.errors.items }}</p>
      </form>
      <template #footer><div class="flex justify-end gap-2"><Button variant="secondary" @click="showCreate = false">Cancelar</Button><Button :disabled="!selectedUnit || total <= 0 || agreementTotal < total" :loading="form.processing" @click="submit">Crear plan</Button></div></template>
    </Modal>
  </DashboardLayout>
</template>
