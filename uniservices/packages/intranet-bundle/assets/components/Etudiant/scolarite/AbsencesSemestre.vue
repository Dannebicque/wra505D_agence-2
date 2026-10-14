<script setup>
import {computed} from 'vue';
import {absencesParEtat, creneauAbsence, formatNote} from '@/utils/scolarite.js';

const props = defineProps({
  semestre: {type: Object, required: true},
});
const emit = defineEmits(['justifier']);

const groupes = computed(() => absencesParEtat(props.semestre.absences.liste));

// Ce qui demande une action d'abord : le rouge leur est réservé.
const SECTIONS = [
  {cle: 'aJustifier', titre: 'À justifier', action: 'Justifier', ton: 'action'},
  {cle: 'refusees', titre: 'Justificatif refusé', action: 'Justifier de nouveau', ton: 'action'},
  {cle: 'enAttente', titre: 'En attente de décision', action: null, ton: 'attente'},
  {cle: 'justifiees', titre: 'Justifiées', action: null, ton: 'neutre'},
];
</script>

<template>
  <div class="absences">
    <p class="regle">
      <template v-if="semestre.penalite.active">
        Une absence non justifiée retire {{ formatNote(semestre.penalite.points) }} point à la moyenne de sa matière.
        Un justificatif accepté lève la pénalité.
      </template>
      <template v-else>Les absences ne pénalisent pas les moyennes de ce semestre.</template>
    </p>

    <ul class="compteurs">
      <li :class="['compteur', { 'compteur--action': groupes.aJustifier.length + groupes.refusees.length }]">
        <span class="compteur__valeur">{{ groupes.aJustifier.length + groupes.refusees.length }}</span> à justifier
      </li>
      <li class="compteur compteur--attente">
        <span class="compteur__valeur">{{ groupes.enAttente.length }}</span> en attente
      </li>
      <li class="compteur">
        <span class="compteur__valeur">{{ groupes.justifiees.length }}</span> justifiée{{ groupes.justifiees.length > 1 ? 's' : '' }}
      </li>
    </ul>

    <p v-if="!semestre.absences.total" class="m-0!">Aucune absence ce semestre.</p>

    <template v-for="section in SECTIONS" :key="section.cle">
      <section v-if="groupes[section.cle].length" class="section" :aria-labelledby="`absences-${section.cle}`">
        <h3 :id="`absences-${section.cle}`" class="section__titre" tabindex="-1">{{ section.titre }} <span class="section__nombre">{{ groupes[section.cle].length }}</span></h3>
        <ul class="liste">
          <li v-for="absence in groupes[section.cle]" :key="absence.debut" :class="['absence', `absence--${section.ton}`]">
            <span class="absence__repere" aria-hidden="true"><i class="pi pi-calendar-times"></i></span>
            <span class="absence__texte">
              <span class="absence__matiere">{{ absence.matiere || 'Cours' }}</span>
              <span class="absence__creneau">{{ creneauAbsence(absence) }}</span>
              <span v-if="absence.motifRefus" class="absence__refus">Motif du refus : {{ absence.motifRefus }}</span>
            </span>
            <Button
                v-if="section.action"
                :label="section.action"
                icon="pi pi-upload"
                size="small"
                class="min-h-[44px]! shrink-0"
                :aria-label="`${section.action} l'absence en ${absence.matiere || 'cours'}, ${creneauAbsence(absence)}`"
                @click="emit('justifier', absence)"
            />
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>

<style scoped>
.absences {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.regle {
  margin: 0;
  color: var(--p-text-muted-color);
}

.compteurs {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.75rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.compteur {
  display: flex;
  flex-direction: column;
  padding: 0.75rem 1rem;
  border-radius: 14px;
  background: var(--p-seance-autre-background);
  color: var(--p-text-color);
}

.compteur--action {
  background: var(--p-seance-evaluation-background);
  color: var(--p-seance-evaluation-color);
}

.compteur--attente {
  background: var(--p-seance-td-background);
  color: var(--p-seance-td-color);
}

.compteur__valeur {
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1.1;
}

.section {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.section__titre {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0 !important;
  font-size: 1rem !important;
  font-weight: 600;
}

.section__nombre {
  padding: 0 0.5rem;
  border-radius: 999px;
  font-size: 0.8125rem;
  background: var(--p-content-hover-background);
}

.liste {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.absence {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  padding: 0.75rem 1rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 14px;
}

.absence__repere {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  flex-shrink: 0;
  border-radius: 999px;
  background: var(--p-seance-autre-background);
  color: var(--p-text-muted-color);
}

.absence--action .absence__repere {
  background: var(--p-seance-evaluation-background);
  color: var(--p-seance-evaluation-color);
}

.absence--attente .absence__repere {
  background: var(--p-seance-td-background);
  color: var(--p-seance-td-color);
}

.absence__texte {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.absence__matiere {
  font-weight: 600;
}

.absence__creneau {
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
}

.absence__refus {
  margin-top: 0.25rem;
  font-size: 0.875rem;
  color: var(--p-seance-evaluation-color);
}

@media (max-width: 639px) {
  .absence {
    flex-wrap: wrap;
  }
}
</style>
