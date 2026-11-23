<script setup>
import CriteresEvaluationPeriode from '@/components/CriteresEvaluationPeriode.vue';
import { Card } from '@components';
import {
  CalendarIcon,
  UsersIcon,
  UserIcon,
  UserPlusIcon,
  ClockIcon,
  DocumentTextIcon,
  Cog6ToothIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
  period: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['edit']);
</script>

<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center pb-2 border-b border-surface ">
      <div>
        <h2 class="text-sm font-bold text-muted-color uppercase tracking-wider">
          Configuration & Paramètres de la période
        </h2>
        <p class="text-xs text-muted-color mt-0.5">Détails administratifs, calendrier et modalités pédagogiques.</p>
      </div>
      <button
        @click="emit('edit', period)"
        class="py-2 px-4 bg-primary hover:bg-primary text-primary-contrast font-bold rounded-xl text-xs transition-all flex items-center justify-center gap-2 shadow-sm cursor-pointer border-0"
      >
        <Cog6ToothIcon class="w-3.5 h-3.5" />
        <span>Modifier la période</span>
      </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Section 1: Informations Générales & Calendrier -->
      <Card
        title="Informations & Calendrier"
        subtitle="Dates clés et durée minimale du stage."
        :icon="CalendarIcon"
        iconClass="text-primary "
        iconBgClass="bg-emphasis  border-primary "
        bodyClass="space-y-4"
      >
        <div class="grid grid-cols-2 gap-4 text-xs">
          <div class="space-y-1">
            <span class="text-muted-color block">Type de période</span>
            <span class="font-bold text-color  text-sm flex items-center gap-1.5">
              <span class="bg-emphasis  text-primary  px-2 py-0.5 rounded text-[9px] uppercase tracking-wider font-extrabold">{{ period.type }}</span>
            </span>
          </div>
          <div class="space-y-1">
            <span class="text-muted-color block">Année Universitaire</span>
            <span class="font-bold text-color  text-sm">{{ period.anneeUniv }}</span>
          </div>
          <div class="space-y-1">
            <span class="text-muted-color block">Dates de stage</span>
            <span class="font-bold text-color  text-sm">{{ period.dates }}</span>
          </div>
          <div class="space-y-1">
            <span class="text-muted-color block">Durée minimale</span>
            <span class="font-bold text-color  text-sm">{{ period.minWeeks }} semaines</span>
          </div>
          <div class="space-y-1 col-span-2">
            <span class="text-muted-color block">Souplesse des dates</span>
            <span :class="['px-2 py-0.5 text-[9px] rounded font-bold uppercase inline-block', period.datesFlexibles ? 'bg-emphasis text-primary  ' : 'bg-emphasis text-muted-color  ']">
              {{ period.datesFlexibles ? 'Dates de début et de fin flexibles' : 'Dates strictes imposées' }}
            </span>
          </div>
        </div>
      </Card>

      <!-- Section 2: Responsables & Équipe -->
      <Card
        title="Responsables & Accompagnement"
        subtitle="Membres de l'équipe pédagogique référents."
        :icon="UsersIcon"
        iconClass="text-primary "
        iconBgClass="bg-emphasis  border-primary "
        bodyClass="space-y-4"
      >
        <div class="space-y-3 text-xs">
          <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-full bg-emphasis  flex items-center justify-center shrink-0">
              <UserIcon class="w-4 h-4 text-muted-color" />
            </div>
            <div>
              <span class="text-muted-color block text-[10px] uppercase font-bold tracking-wider">Responsable Principal</span>
              <span class="font-bold text-color  text-sm">{{ period.responsablePrincipal }}</span>
            </div>
          </div>
          <div class="flex items-start gap-3 border-t border-surface  pt-3">
            <div class="w-8 h-8 rounded-full bg-emphasis  flex items-center justify-center shrink-0">
              <UserPlusIcon class="w-4 h-4 text-muted-color" />
            </div>
            <div>
              <span class="text-muted-color block text-[10px] uppercase font-bold tracking-wider">Co-responsables</span>
              <span class="font-bold text-color  text-sm" v-if="period.coResponsables && period.coResponsables.length > 0">
                {{ period.coResponsables.join(', ') }}
              </span>
              <span class="text-muted-color italic text-sm" v-else>Aucun co-responsable défini</span>
            </div>
          </div>
        </div>
      </Card>

      <!-- Section 3: Interruptions & Soutenances -->
      <Card
        title="Interruptions & Soutenances"
        subtitle="Dates des interruptions et sessions de soutenance."
        :icon="ClockIcon"
        iconClass="text-muted-color "
        iconBgClass="bg-emphasis  border-surface "
        bodyClass="space-y-4"
      >
        <div class="space-y-4 text-xs">
          <div>
            <span class="font-bold text-color  block mb-2">Périodes d'interruption :</span>
            <div v-if="period.interruptions && period.interruptions.length > 0" class="space-y-1.5">
              <div v-for="i in period.interruptions" :key="i.dateDebut" class="bg-emphasis  p-2.5 rounded-xl border border-surface  flex justify-between items-center">
                <div>
                  <span class="font-bold text-color ">Du {{ new Date(i.dateDebut).toLocaleDateString('fr-FR') }} au {{ new Date(i.dateFin).toLocaleDateString('fr-FR') }}</span>
                  <span class="text-[10px] text-muted-color block mt-0.5">{{ i.motif || 'Aucun motif renseigné' }}</span>
                </div>
                <span class="bg-emphasis  text-color  px-2 py-0.5 rounded font-bold text-[9px] uppercase tracking-wider">Interruption</span>
              </div>
            </div>
            <div v-else class="text-muted-color italic text-xs py-2 bg-emphasis  rounded-xl text-center border border-dashed border-surface ">
              Aucune période d'interruption déclarée.
            </div>
          </div>

          <div class="border-t border-surface  pt-3">
            <span class="font-bold text-color  block mb-2">Sessions de soutenance :</span>
            <div v-if="period.soutenances && period.soutenances.length > 0" class="space-y-2">
              <div v-for="s in period.soutenances" :key="s.dateDebut" class="bg-emphasis  p-2.5 rounded-xl border border-surface  space-y-1.5">
                <div class="flex justify-between items-center">
                  <span class="font-bold text-color ">Soutenances du {{ new Date(s.dateDebut).toLocaleDateString('fr') }} au {{ new Date(s.dateFin).toLocaleDateString('fr') }}</span>
                  <span class="bg-emphasis  text-color  px-2 py-0.5 rounded font-bold text-[9px] uppercase tracking-wider">Soutenances</span>
                </div>
                <div class="text-[10px] text-muted-color space-y-0.5">
                  <p>Date limite rendu rapport : <strong class="text-muted-color ">{{ new Date(s.dateRenduRapport).toLocaleDateString('fr') }}</strong></p>
                  <p v-if="s.modalites">Modalités : {{ s.modalites }}</p>
                </div>
              </div>
            </div>
            <div v-else class="text-muted-color italic text-xs py-2 bg-emphasis  rounded-xl text-center border border-dashed border-surface ">
              Aucune période de soutenance programmée.
            </div>
          </div>
        </div>
      </Card>

      <!-- Section 4: Modalités d'Évaluation & Pédagogie -->
      <Card
        title="Modalités Pédagogiques & Rendu"
        subtitle="Consignes de validation et compétences attendues."
        :icon="DocumentTextIcon"
        iconClass="text-muted-color "
        iconBgClass="bg-emphasis  border-surface "
        bodyClass="space-y-4"
      >
        <div class="space-y-3 text-xs">
          <div class="bg-emphasis  rounded-xl p-3 border border-surface ">
            <span class="font-bold text-color  block mb-1">Compétences visées :</span>
            <p class="text-muted-color  leading-relaxed font-medium">
              {{ period.competencesVisees || 'Non définies.' }}
            </p>
          </div>
          <div class="bg-emphasis  rounded-xl p-3 border border-surface ">
            <span class="font-bold text-color  block mb-1">Documents à rendre :</span>
            <p class="text-muted-color  leading-relaxed font-medium">
              {{ period.documentsRendre || 'Non définis.' }}
            </p>
          </div>
          <div v-if="period.commentaireLibre" class="bg-emphasis  rounded-xl p-3 border border-surface ">
            <span class="font-bold text-color  block mb-1">Consignes complémentaires :</span>
            <p class="text-muted-color  leading-relaxed font-medium">
              {{ period.commentaireLibre }}
            </p>
          </div>
        </div>
      </Card>

      <Card
        title="Évaluation par le tuteur en entreprise"
        subtitle="Grille envoyée par e-mail à l'organisme d'accueil."
        :icon="DocumentTextIcon"
        iconClass="text-primary "
        iconBgClass="bg-emphasis  border-primary "
      >
        <CriteresEvaluationPeriode :periode-id="period.id" />
      </Card>
    </div>
  </div>
</template>
