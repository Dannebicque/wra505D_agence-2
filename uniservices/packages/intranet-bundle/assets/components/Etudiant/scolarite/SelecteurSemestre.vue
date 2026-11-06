<script setup>
import {computed} from 'vue';
import {semestresParAnnee} from '@/utils/scolarite.js';

// Les semestres dans une liste déroulante, groupés par année : « S1 » seul serait ambigu d'une année à l'autre.
const props = defineProps({
  semestres: {type: Array, required: true},
});
const choisi = defineModel({type: Number, default: null});

const annees = computed(() => semestresParAnnee(props.semestres));
const semestreChoisi = computed(() => props.semestres.find(semestre => semestre.id === choisi.value) ?? null);
</script>

<template>
  <div class="selecteur">
    <label id="selecteur-semestre-libelle" class="sr-only">Semestre</label>
    <Select
        v-model="choisi"
        :options="annees"
        option-group-label="annee"
        option-group-children="semestres"
        option-label="libelle"
        option-value="id"
        aria-labelledby="selecteur-semestre-libelle"
        class="selecteur__liste"
    >
      <template #value>
        <span v-if="semestreChoisi" class="selecteur__valeur">
          <span class="selecteur__icone" aria-hidden="true"><i class="pi pi-calendar"></i></span>
          <span class="selecteur__semestre">{{ semestreChoisi.libelle }}</span>
          <span class="selecteur__annee">{{ semestreChoisi.anneeUniversitaire.replace('/', '-') }}</span>
        </span>
      </template>
      <template #option="{option}">
        <span>{{ option.libelle }}<span v-if="option.actif" class="selecteur__en-cours"> (année en cours)</span></span>
      </template>
    </Select>
  </div>
</template>

<style scoped>
/* La même pilule que les onglets voisins : fond doux sans bordure, icône dans une pastille blanche
   comme l'onglet ouvert. Le semestre en gras, l'année atténuée à côté. */
.selecteur__liste {
  min-width: 12.5rem;
  min-height: 52px;
  align-items: center;
  padding: 0.25rem;
  border: none;
  border-radius: 999px;
  background: var(--p-content-hover-background);
  box-shadow: none;
  transition: filter 150ms ease;
}

.selecteur__liste:hover {
  filter: brightness(0.97);
}

.selecteur__liste:focus-within {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}

.selecteur__liste :deep(.p-select-label) {
  display: flex;
  align-items: center;
  padding: 0 0.25rem 0 0;
}

.selecteur__liste :deep(.p-select-dropdown) {
  width: 2.5rem;
  color: var(--p-text-muted-color);
}

.selecteur__valeur {
  display: inline-flex;
  align-items: center;
  gap: 0.625rem;
}

.selecteur__icone {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 999px;
  font-size: 1rem;
  background: var(--p-pastille-active-background);
  color: var(--p-pastille-active-color);
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.12);
}

.selecteur__semestre {
  font-weight: 700;
  color: var(--p-text-color);
}

.selecteur__annee {
  font-weight: 500;
  color: var(--p-text-muted-color);
}

.selecteur__en-cours {
  color: var(--p-text-muted-color);
  font-size: 0.8125rem;
}

@media (prefers-reduced-motion: reduce) {
  .selecteur__liste {
    transition: none;
  }
}
</style>
