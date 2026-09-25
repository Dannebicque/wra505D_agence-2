<script setup lang="ts">
import { computed } from 'vue';
import type { SortField, SortOrder } from '@types';

interface Props {
  sortField: SortField;
  sortOrder: SortOrder;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  sort: [{ field: SortField; order: SortOrder }];
}>();

const sortOptions = [
  { field: 'title' as SortField, order: 'asc' as SortOrder, label: 'Titre, de A à Z' },
  { field: 'title' as SortField, order: 'desc' as SortOrder, label: 'Titre, de Z à A' },
  { field: 'lastModified' as SortField, order: 'desc' as SortOrder, label: 'Plus récent' },
  { field: 'lastModified' as SortField, order: 'asc' as SortOrder, label: 'Plus ancien' },
  { field: 'size' as SortField, order: 'desc' as SortOrder, label: 'Plus lourd' },
  { field: 'size' as SortField, order: 'asc' as SortOrder, label: 'Plus léger' },
].map(option => ({ ...option, cle: `${option.field}-${option.order}` }));

const triChoisi = computed({
  get: () => `${props.sortField}-${props.sortOrder}`,
  set: (cle: string) => {
    const option = sortOptions.find(o => o.cle === cle);
    if (option) {
      emit('sort', { field: option.field, order: option.order });
    }
  },
});
</script>

<template>
  <div class="flex items-center gap-2">
    <span id="tri-documents" class="text-sm text-gray-700">Trier par</span>
    <Select
        v-model="triChoisi"
        :options="sortOptions"
        option-label="label"
        option-value="cle"
        aria-labelledby="tri-documents"
        class="min-h-[44px]"
    />
  </div>
</template>
