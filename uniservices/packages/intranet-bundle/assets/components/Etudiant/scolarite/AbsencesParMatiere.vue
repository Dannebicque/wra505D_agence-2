<script setup>
import {computed, useId} from 'vue';
import {absencesParMatiere} from '@/utils/scolarite.js';

// Une ou deux absences par matière ne demandent pas de graphique : chaque matière sur sa ligne, et
// ses absences comptées en pastilles qui disent leur état en toutes lettres.
const props = defineProps({
  absences: {type: Array, required: true},
});

const ETATS = [
  {cle: 'aJustifier', texte: () => 'à justifier', icone: 'pi pi-exclamation-circle', ton: 'action'},
  {cle: 'enAttente', texte: () => 'en attente', icone: 'pi pi-clock', ton: 'attente'},
  {cle: 'justifiees', texte: (nombre) => (nombre > 1 ? 'justifiées' : 'justifiée'), icone: 'pi pi-check-circle', ton: 'ok'},
];

const matieres = computed(() => absencesParMatiere(props.absences));
const id = useId();
</script>

<template>
  <section v-if="matieres.length" class="repartition apparition" style="--rang: 2" :aria-labelledby="id">
    <h3 :id="id" class="repartition__titre">
      <span class="repartition__icone" aria-hidden="true"><i class="pi pi-calendar-times"></i></span>
      Absences par matière
    </h3>
    <ul class="repartition__liste">
      <li v-for="matiere in matieres" :key="matiere.matiere" class="ligne">
        <span class="ligne__matiere">{{ matiere.matiere }}</span>
        <span class="ligne__etats">
          <template v-for="etat in ETATS" :key="etat.cle">
            <span v-if="matiere[etat.cle]" :class="['etat', `etat--${etat.ton}`]">
              <i :class="etat.icone" aria-hidden="true"></i>
              {{ matiere[etat.cle] }} {{ etat.texte(matiere[etat.cle]) }}
            </span>
          </template>
        </span>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.repartition {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.repartition__titre {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  margin: 0 !important;
  font-size: 1.0625rem !important;
  font-weight: 600;
}

.repartition__icone {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: 999px;
  font-size: 1rem;
  background: var(--p-seance-evaluation-background);
  color: var(--p-seance-evaluation-color);
}

.repartition__liste {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  overflow: hidden;
  border-radius: 14px;
  background: var(--p-content-hover-background);
  list-style: none;
}

.ligne {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem 1rem;
  padding: 0.75rem 1rem;
}

.ligne + .ligne {
  border-top: 1px solid var(--p-content-border-color);
}

.ligne__matiere {
  font-weight: 500;
}

.ligne__etats {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
}

/* Texte foncé sur fond pâle du même type : 5,65:1 au moins, comme les pastilles de l'accueil. */
.etat {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.25rem 0.75rem;
  border-radius: 999px;
  font-size: 1rem;
  font-weight: 600;
  white-space: nowrap;
}

.etat--action {
  background: var(--p-seance-evaluation-background);
  color: var(--p-seance-evaluation-color);
}

.etat--attente {
  background: var(--p-seance-td-background);
  color: var(--p-seance-td-color);
}

.etat--ok {
  background: var(--p-seance-tp-background);
  color: var(--p-seance-tp-color);
}

/* Sur téléphone, les pastilles passent toujours sous la matière : selon la longueur du nom, elles
   tombaient tantôt à côté, tantôt dessous. */
@media (max-width: 639px) {
  .ligne {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
