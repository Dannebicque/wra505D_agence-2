<script setup lang="ts">
import type { GroupesMap } from '@/types/trombinoscope';

interface Props {
  groupes?: GroupesMap;
  maxBadgeWidth?: string;
}

withDefaults(defineProps<Props>(), {
  maxBadgeWidth: 'max-w-[130px]',
});

const groupTypes = [
  { key: 'CM', label: 'CM', seance: 'cm' },
  { key: 'TD', label: 'TD', seance: 'td' },
  { key: 'TP', label: 'TP', seance: 'tp' },
] as const;
</script>

<template>
  <div class="flex flex-wrap items-center justify-center gap-1.5">
    <template v-for="t in groupTypes" :key="t.key">
      <span
        v-for="grp in (groupes?.[t.key] || [])"
        :key="grp"
        :class="[
          'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-xs font-semibold cursor-default truncate',
          maxBadgeWidth
        ]"
        :style="{ background: `var(--p-seance-${t.seance}-background)`, color: `var(--p-seance-${t.seance}-color)` }"
        v-tooltip.top="`Groupe ${t.label} : ${grp}`"
      >
        <span class="text-[10px] font-black uppercase flex-shrink-0">
          {{ t.label }}
        </span>
        <span class="truncate">{{ grp }}</span>
      </span>
    </template>

    <span
      v-if="!groupes?.CM?.length && !groupes?.TD?.length && !groupes?.TP?.length"
      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-emphasis text-muted-color border border-surface"
    >
      <i class="pi pi-info-circle text-[11px] text-muted-color" />
      <span>Aucun groupe</span>
    </span>
  </div>
</template>

