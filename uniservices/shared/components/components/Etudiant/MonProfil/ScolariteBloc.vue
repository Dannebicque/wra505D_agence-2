<script setup>
import {computed, ref, watch} from 'vue';
import {decisionJury, moyenneSur20} from '@helpers/profil.js';
import {BlocAccueil, LigneAccueil} from '@components';

// Les inscriptions chargées par la page, de la plus récente à la plus ancienne, chacune avec ses
// semestres, leurs groupes et les groupes disponibles.
const props = defineProps({
  scolarites: {type: Array, default: null},
  erreur: {type: Boolean, default: false},
});

const emit = defineEmits(['changer-groupe']);

const ongletActif = ref(null);
watch(() => props.scolarites, (scolarites) => {
  if (scolarites?.length && ongletActif.value === null) {
    ongletActif.value = (scolarites.find((sco) => sco.actif) ?? scolarites[0]).anneeUniversitaire.libelle;
  }
}, {immediate: true});

const plusieursAnnees = computed(() => (props.scolarites?.length ?? 0) > 1);

// Les types de groupe reprennent les couleurs des séances de l'emploi du temps.
const teinte = (type) => ({CM: 'cm', TD: 'td', TP: 'tp'})[type] ?? 'autre';

const groupeChoisi = (scoSemestre, type) => scoSemestre.groupes?.find((groupe) => groupe.type === type)?.id ?? null;
const groupesDuType = (scoSemestre, type) => (scoSemestre.disponibles ?? []).filter((groupe) => groupe.type === type);

const aUnBilan = (scoSemestre) => scoSemestre.moyenne != null || scoSemestre.decision != null;
</script>

<template>
  <BlocAccueil titre="Scolarité" icone="pi pi-graduation-cap" lien="/intranet/scolarite" texte-lien="Notes et absences">
    <p v-if="erreur" class="scolarite__vide">La scolarité est indisponible pour le moment.</p>
    <Skeleton v-else-if="scolarites === null" height="8rem" border-radius="14px" aria-hidden="true"/>
    <p v-else-if="scolarites.length === 0" class="scolarite__vide">Aucune inscription pour le moment.</p>

    <Tabs v-else v-model:value="ongletActif" class="scolarite__onglets" :class="{ 'scolarite__onglets--seul': !plusieursAnnees }">
      <TabList v-if="plusieursAnnees">
        <Tab v-for="scolarite in scolarites" :key="scolarite.id" :value="scolarite.anneeUniversitaire.libelle">
          {{ scolarite.anneeUniversitaire.libelle }}
        </Tab>
      </TabList>
      <TabPanels>
        <TabPanel v-for="scolarite in scolarites" :key="scolarite.id" :value="scolarite.anneeUniversitaire.libelle">
          <p v-if="!scolarite.semestres.length" class="scolarite__vide">Aucun semestre pour cette année.</p>
          <article v-for="scoSemestre in scolarite.semestres" :key="scoSemestre.id" class="scolarite__semestre">
            <h3 class="scolarite__semestre-titre">
              {{ scoSemestre.semestre.annee?.libelle }}
              <span class="scolarite__semestre-libelle">{{ scoSemestre.semestre.libelle }}</span>
            </h3>

            <div v-if="scoSemestre.semestre.typesGroupe?.length" class="scolarite__groupes">
              <div v-for="type in scoSemestre.semestre.typesGroupe" :key="type" class="scolarite__groupe">
                <label :for="`groupe-${scoSemestre.id}-${type}`" class="scolarite__groupe-label">
                  <span
                      class="scolarite__pastille"
                      aria-hidden="true"
                      :style="{
                        background: `var(--p-seance-${teinte(type)}-background)`,
                        color: `var(--p-seance-${teinte(type)}-color)`,
                      }"
                  >{{ type }}</span>
                  <span class="sr-only">Groupe {{ type }}</span>
                </label>
                <Select
                    :input-id="`groupe-${scoSemestre.id}-${type}`"
                    :model-value="groupeChoisi(scoSemestre, type)"
                    :options="groupesDuType(scoSemestre, type)"
                    option-label="libelle"
                    option-value="id"
                    :placeholder="`Choisir un groupe ${type}`"
                    class="scolarite__select"
                    @update:model-value="groupeId => emit('changer-groupe', scoSemestre, groupeId, type)"
                />
              </div>
            </div>

            <template v-if="aUnBilan(scoSemestre)">
              <h4 class="scolarite__sous-titre">Bilan du semestre</h4>
              <ul class="scolarite__bilan">
                <LigneAccueil icone="pi pi-chart-bar">
                  <template #titre>Moyenne</template>
                  <template v-if="scoSemestre.rang" #detail>{{ scoSemestre.rang }}<sup>{{ scoSemestre.rang === 1 ? 're' : 'e' }}</sup> de la promotion</template>
                  <template #valeur>
                    <span :class="{ 'scolarite__decision--attente': scoSemestre.moyenne == null }">{{ moyenneSur20(scoSemestre.moyenne) ?? 'Non calculée' }}</span>
                  </template>
                </LigneAccueil>
                <LigneAccueil icone="pi pi-check-circle">
                  <template #titre>Décision du jury</template>
                  <template v-if="scoSemestre.proposition" #detail>Poursuite en {{ scoSemestre.proposition.libelle }}</template>
                  <template #valeur>
                    <span :class="['scolarite__decision', `scolarite__decision--${decisionJury(scoSemestre.decision).ton}`]">
                      {{ decisionJury(scoSemestre.decision).texte }}
                    </span>
                  </template>
                </LigneAccueil>
              </ul>
            </template>
            <p v-else class="scolarite__vide">Pas encore de bilan pour ce semestre.</p>
          </article>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </BlocAccueil>
</template>

<style scoped>
.scolarite__vide {
  margin: 0;
  color: var(--p-text-muted-color);
}

.scolarite__onglets :deep(.p-tablist-tab-list),
.scolarite__onglets :deep(.p-tabpanels) {
  background: transparent;
}

.scolarite__onglets :deep(.p-tabpanels) {
  padding: 1rem 0 0;
}

.scolarite__onglets--seul :deep(.p-tabpanels) {
  padding-top: 0;
}

.scolarite__onglets :deep(.p-tab) {
  min-height: 44px;
}

.scolarite__onglets :deep(.p-tabpanel) {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

/* Un semestre se pose sur le fond de la page, comme les cours sur la grille de l'agenda. */
.scolarite__semestre {
  display: flex;
  flex-direction: column;
  gap: 0.875rem;
  padding: 0.875rem 1rem 1rem;
  border-radius: 14px;
  background: var(--p-content-hover-background);
}

.scolarite__semestre-titre {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  margin: 0 !important;
  font-size: 1rem !important;
  font-weight: 600;
}

.scolarite__semestre-libelle {
  font-weight: 400;
  color: var(--p-text-muted-color);
}

.scolarite__groupes {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
  gap: 0.75rem;
}

.scolarite__groupe {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.scolarite__groupe-label {
  display: inline-flex;
}

.scolarite__pastille {
  padding: 0.125rem 0.625rem;
  border-radius: 999px;
  font-size: 0.875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
}

.scolarite__select {
  width: 100%;
  min-height: 44px;
  align-items: center;
}

.scolarite__sous-titre {
  margin: 0 !important;
  font-size: 0.9375rem !important;
  font-weight: 600;
  color: var(--p-text-muted-color);
}

.scolarite__bilan {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
}

.scolarite__decision--valide {
  color: var(--p-seance-tp-color);
}

.scolarite__decision--non-valide {
  color: var(--p-seance-evaluation-color);
}

.scolarite__decision--attente {
  font-weight: 400;
  color: var(--p-text-muted-color);
}
</style>
