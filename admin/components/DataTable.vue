<script setup lang="ts">
const props = defineProps<{
  columns: { key: string; label: string }[]
  rows: Record<string, unknown>[]
}>()
</script>

<template>
  <table class="w-full text-left text-sm">
    <thead>
      <tr class="border-b">
        <th v-for="col in props.columns" :key="col.key" class="px-3 py-2 font-medium">{{ col.label }}</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="(row, i) in props.rows" :key="i" class="border-b">
        <td v-for="col in props.columns" :key="col.key" class="px-3 py-2">
          <slot :name="col.key" :row="row">{{ row[col.key] }}</slot>
        </td>
      </tr>
    </tbody>
  </table>
</template>
