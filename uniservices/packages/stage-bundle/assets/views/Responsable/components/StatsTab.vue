<script setup>
import { Card } from '@components';
import { ChartPieIcon, CurrencyEuroIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  kpis: {
    type: Object,
    required: true
  },
  activePeriodName: {
    type: String,
    required: true
  }
});
</script>

<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Placement rates Card -->
      <Card
        title="Taux d'insertion des étudiants"
        :subtitle="'Proportion d\'étudiants ayant validé leur convention à ce jour. Période : ' + activePeriodName"
        :icon="ChartPieIcon"
        iconClass="text-primary "
        iconBgClass="bg-emphasis  border-primary "
      >
        <div class="flex flex-col sm:flex-row items-center justify-around gap-6 py-2">
          <!-- SVG Donut Chart dynamically calculated -->
          <div class="relative w-44 h-44 shrink-0">
            <svg viewBox="0 0 36 36" class="w-full h-full transform -rotate-90">
              <path class="text-color " stroke-width="3" stroke="currentColor" fill="none"
                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
              <path class="text-primary" stroke-width="3" :stroke-dasharray="kpis.rate + ', 100'"
                stroke-linecap="round" stroke="currentColor" fill="none"
                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
              <span class="text-3xl font-black text-color -contrast leading-none">{{ kpis.rate }} %</span>
              <span class="text-[9px] text-muted-color font-bold uppercase tracking-wider mt-1">Placés</span>
            </div>
          </div>

          <!-- Legend -->
          <div class="space-y-3 text-xs w-full">
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded bg-primary"></span>
                <span class="text-muted-color">Conventions signées / Placées</span>
              </span>
              <span class="font-bold text-color ">{{ kpis.placed }} étudiants</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded bg-emphasis "></span>
                <span class="text-muted-color">Recherche active</span>
              </span>
              <span class="font-bold text-color ">{{ kpis.total - kpis.placed }} étudiants</span>
            </div>
          </div>
        </div>
      </Card>

      <!-- Average compensation salary stats -->
      <Card
        title="Statistiques financières de la période"
        subtitle="Moyennes de gratification horaire négociées par les étudiants."
        :icon="CurrencyEuroIcon"
        iconClass="text-muted-color "
        iconBgClass="bg-emphasis  border-surface "
        bodyClass="flex flex-col justify-between h-full"
      >
        <div class="space-y-4 py-2">
          <div>
            <div class="flex justify-between text-xs mb-1 font-bold">
              <span class="text-muted-color ">Gratification maximale enregistrée</span>
              <span class="text-primary">6.80 €/h</span>
            </div>
            <div class="w-full bg-emphasis  h-2 rounded-full overflow-hidden">
              <div class="bg-primary h-full rounded-full" style="width: 100%"></div>
            </div>
          </div>
          <div>
            <div class="flex justify-between text-xs mb-1 font-bold">
              <span class="text-muted-color ">Gratification moyenne</span>
              <span class="text-primary">4.95 €/h</span>
            </div>
            <div class="w-full bg-emphasis  h-2 rounded-full overflow-hidden">
              <div class="bg-primary h-full rounded-full" style="width: 72.8%"></div>
            </div>
          </div>
          <div>
            <div class="flex justify-between text-xs mb-1 font-bold">
              <span class="text-muted-color ">Minimum légal obligatoire</span>
              <span class="text-muted-color">4.35 €/h</span>
            </div>
            <div class="w-full bg-emphasis  h-2 rounded-full overflow-hidden">
              <div class="bg-emphasis h-full rounded-full" style="width: 63.9%"></div>
            </div>
          </div>
        </div>

        <div class="text-[10px] text-muted-color  pt-4 border-t border-surface  mt-6">
          * Indicateurs de gratification calculés sur la base des contrats saisis.
        </div>
      </Card>

    </div>
  </div>
</template>
