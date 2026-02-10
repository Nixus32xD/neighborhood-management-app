<script setup>
import { computed } from 'vue'
import EmptyState from './EmptyState.vue'

const props = defineProps({
  columns: {
    type: Array,
    required: true
    // { key: string, label: string, class?: string }
  },
  data: {
    type: Array,
    default: () => []
  },
  emptyTitle: {
    type: String,
    default: 'No data found'
  },
  emptyDescription: {
    type: String,
    default: 'There are no records to display.'
  }
})

const isEmpty = computed(() => props.data.length === 0)
</script>

<template>
  <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th
              v-for="column in columns"
              :key="column.key"
              :class="[
                'px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider',
                column.class
              ]"
            >
              {{ column.label }}
            </th>
            <th v-if="$slots.actions" class="px-4 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <template v-if="!isEmpty">
            <tr
              v-for="(row, index) in data"
              :key="row.id || index"
              class="hover:bg-slate-50 transition-colors"
            >
              <td
                v-for="column in columns"
                :key="column.key"
                :class="['px-4 py-3 text-sm text-slate-700', column.class]"
              >
                <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
                  {{ row[column.key] }}
                </slot>
              </td>
              <td v-if="$slots.actions" class="px-4 py-3 text-right">
                <slot name="actions" :row="row" />
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <EmptyState
      v-if="isEmpty"
      :title="emptyTitle"
      :description="emptyDescription"
      class="py-12"
    />
  </div>
</template>
