<script setup>
import {creneauAbsence, justificationAbsence} from '@/utils/scolarite.js';

defineProps({
  absences: {type: Array, required: true},
});

// Les tons de la page Notes et absences : le rouge pour ce qui demande une action.
const TONS = {aucune: 'action', refusee: 'action', en_attente: 'attente', validee: 'neutre'};
</script>

<template>
  <div class="absences">
    <p v-if="!absences.length" class="m-0!">Aucune absence dans cette matière.</p>
    <ul v-else class="liste">
      <li v-for="absence in absences" :key="absence.debut" :class="['absence', `absence--${TONS[absence.justification] ?? 'action'}`]">
        <span class="absence__repere" aria-hidden="true"><i class="pi pi-calendar-times"></i></span>
        <span class="absence__texte">
          <span class="absence__creneau">{{ creneauAbsence(absence) }}</span>
          <span class="absence__etat">{{ justificationAbsence(absence.justification).texte }}</span>
          <span v-if="absence.motifRefus" class="absence__refus">Motif du refus : {{ absence.motifRefus }}</span>
        </span>
      </li>
    </ul>
    <!-- Le dépôt d'un justificatif vit sur la page Notes et absences : la fiche y renvoie plutôt que
         d'en dupliquer le panneau. -->
    <router-link
        v-if="absences.some(absence => TONS[absence.justification] === 'action')"
        :to="{path: '/intranet/scolarite', query: {onglet: 'absences'}}"
        class="absences__lien"
    >
      Justifier une absence
      <i class="pi pi-arrow-right" aria-hidden="true"></i>
    </router-link>
  </div>
</template>

<style scoped>
.absences {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
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

.absence__creneau {
  font-weight: 600;
}

.absence__etat {
  font-size: 1rem;
  color: var(--p-text-muted-color);
}

.absence--action .absence__etat,
.absence__refus {
  color: var(--p-seance-evaluation-color);
}

.absence__refus {
  margin-top: 0.25rem;
  font-size: 1rem;
}

.absences__lien {
  display: inline-flex;
  align-items: center;
  align-self: flex-start;
  gap: 0.5rem;
  min-height: 44px;
  font-weight: 600;
  color: var(--p-primary-color);
}

.absences__lien:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
  border-radius: 6px;
}
</style>
