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
        <span v-if="semestreChoisi">{{ semestreChoisi.libelle }}, {{ semestreChoisi.anneeUniversitaire.replace('/', '-') }}</span>
      </template>
      <template #option="{option}">
        <span>{{ option.libelle }}<span v-if="option.actif" class="selecteur__en-cours"> (année en cours)</span></span>
      </template>
    </Select>
  </div>
</template>

<style scoped>
.selecteur__liste {
  min-width: 14rem;
  min-height: 44px;
  align-items: center;
}

.selecteur__en-cours {
  color: var(--p-text-muted-color);
  font-size: 0.8125rem;
}
</style>
