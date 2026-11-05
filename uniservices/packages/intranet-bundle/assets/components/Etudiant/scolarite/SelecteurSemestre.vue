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
          <i class="pi pi-calendar" aria-hidden="true"></i>
          {{ semestreChoisi.libelle }}, {{ semestreChoisi.anneeUniversitaire.replace('/', '-') }}
        </span>
      </template>
      <template #option="{option}">
        <span>{{ option.libelle }}<span v-if="option.actif" class="selecteur__en-cours"> (année en cours)</span></span>
      </template>
    </Select>
  </div>
</template>

<style scoped>
/* Une pilule, comme les commandes de l'agenda ; la bordure garde 3:1 sur la carte. */
.selecteur__liste {
  min-width: 13rem;
  min-height: 44px;
  align-items: center;
  border-color: var(--p-ui-border-color);
  border-radius: 999px;
  padding-left: 0.375rem;
}

.selecteur__valeur {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 500;
}

.selecteur__valeur .pi {
  color: var(--p-primary-color);
}

.selecteur__en-cours {
  color: var(--p-text-muted-color);
  font-size: 0.8125rem;
}
</style>
