<script setup>
import {computed, onMounted, ref} from 'vue';
import {
  getEtudiantScolaritesService,
  getEtudiantScolariteSemestresService,
  getEtudiantService,
  getGroupesService,
  updateEtudiantScolariteSemestreService,
} from '@requests';
import {useUsersStore} from '@stores';
import {formatNaissance} from '@helpers/date.js';
import {groupesApresChoix, iriGroupe, scolariteEnCours} from '@helpers/profil.js';
import {BlocAccueil, ErrorView, LigneAccueil} from '@components';
import CarteEtudiant from './CarteEtudiant.vue';
import CoordonneesEtudiant from './CoordonneesEtudiant.vue';
import ScolariteBloc from './ScolariteBloc.vue';

const userStore = useUsersStore();

const etudiant = ref(null);
const erreurEtudiant = ref(false);
const scolarites = ref(null);
const erreurScolarites = ref(false);
const editionCoordonnees = ref(false);

// Une inscription avec ses semestres, et pour chacun les groupes disponibles : un semestre dont la
// lecture échoue reste affiché, sans groupes, plutôt que de faire tomber toute la scolarité.
const chargerScolarite = async (scolarite) => {
  const semestres = await getEtudiantScolariteSemestresService({scolarite: scolarite.id}).catch(() => []);
  return {
    ...scolarite,
    semestres: await Promise.all(semestres.map(async (scoSemestre) => ({
      ...scoSemestre,
      groupes: scoSemestre.groupes ?? [],
      disponibles: await getGroupesService({semestre: scoSemestre.semestre.id}, '/mini').catch(() => []),
    }))),
  };
};

const chargerScolarites = async (id) => {
  try {
    const liste = await getEtudiantScolaritesService({etudiant: id});
    liste.sort((a, b) => b.anneeUniversitaire.annee - a.anneeUniversitaire.annee);
    scolarites.value = await Promise.all(liste.map(chargerScolarite));
  } catch {
    erreurScolarites.value = true;
  }
};

onMounted(async () => {
  if (!userStore.isLoaded) {
    await userStore.getUser();
  }
  const id = userStore.user?.id;
  chargerScolarites(id);
  try {
    etudiant.value = await getEtudiantService(id);
  } catch {
    erreurEtudiant.value = true;
  }
});

const enCours = computed(() => scolariteEnCours(scolarites.value));
const naissance = computed(() => formatNaissance(etudiant.value?.date_naissance));

const changerGroupe = async (scoSemestre, groupeId, type) => {
  const avant = scoSemestre.groupes;
  scoSemestre.groupes = groupesApresChoix(avant, scoSemestre.disponibles, groupeId, type);
  try {
    await updateEtudiantScolariteSemestreService(scoSemestre.id, {groupes: scoSemestre.groupes.map(iriGroupe)}, true);
  } catch {
    scoSemestre.groupes = avant;
  }
};
</script>

<template>
  <ErrorView v-if="erreurEtudiant" message="Une erreur est survenue lors du chargement de votre profil."/>
  <div v-else-if="!etudiant" class="mon-profil" aria-busy="true">
    <Skeleton class="mon-profil__carte" height="20rem" border-radius="18px"/>
    <Skeleton class="mon-profil__informations" height="20rem" border-radius="18px"/>
  </div>
  <div v-else class="mon-profil" :class="{ 'mon-profil--edition': editionCoordonnees }">
    <CarteEtudiant
        class="mon-profil__carte"
        :etudiant="etudiant"
        :en-cours="enCours"
        :chargement-scolarite="scolarites === null && !erreurScolarites"
    />

    <BlocAccueil class="mon-profil__informations" titre="Informations générales" icone="pi pi-id-card">
      <ul class="mon-profil__liste">
        <LigneAccueil icone="pi pi-calendar">
          <template #titre>Date de naissance</template>
          <template #valeur><span :class="{ 'mon-profil__vide': !naissance }">{{ naissance || 'Non renseignée' }}</span></template>
        </LigneAccueil>
        <LigneAccueil icone="pi pi-book">
          <template #titre>Bac</template>
          <template #valeur><span :class="{ 'mon-profil__vide': !etudiant.bac?.libelle }">{{ etudiant.bac?.libelle || 'Non renseigné' }}</span></template>
        </LigneAccueil>
        <LigneAccueil icone="pi pi-users">
          <template #titre>Promotion</template>
          <template #valeur><span :class="{ 'mon-profil__vide': !etudiant.promotion }">{{ etudiant.promotion || 'Non renseignée' }}</span></template>
        </LigneAccueil>
        <LigneAccueil icone="pi pi-hashtag">
          <template #titre>Numéro étudiant</template>
          <template #valeur><span :class="{ 'mon-profil__vide': !etudiant.num_etudiant }">{{ etudiant.num_etudiant || 'Non renseigné' }}</span></template>
        </LigneAccueil>
        <LigneAccueil icone="pi pi-id-card">
          <template #titre>Numéro INE</template>
          <template #valeur><span :class="{ 'mon-profil__vide': !etudiant.num_ine }">{{ etudiant.num_ine || 'Non renseigné' }}</span></template>
        </LigneAccueil>
        <LigneAccueil v-if="etudiant.site_univ" icone="pi pi-briefcase" :href="etudiant.site_univ">
          <template #titre>Portfolio univ</template>
          <template #valeur><i class="pi pi-external-link" aria-hidden="true"></i></template>
        </LigneAccueil>
        <LigneAccueil v-else icone="pi pi-briefcase">
          <template #titre>Portfolio univ</template>
          <template #valeur><span class="mon-profil__vide">Non renseigné</span></template>
        </LigneAccueil>
      </ul>
      <p class="mon-profil__note">
        <i class="pi pi-info-circle" aria-hidden="true"></i>
        Une erreur dans ces données ? Contactez le responsable de votre formation.
      </p>
    </BlocAccueil>

    <CoordonneesEtudiant
        v-model:edition="editionCoordonnees"
        class="mon-profil__coordonnees"
        :etudiant="etudiant"
        @mis-a-jour="fiche => etudiant = fiche"
    />

    <ScolariteBloc
        class="mon-profil__scolarite"
        :scolarites="scolarites"
        :erreur="erreurScolarites"
        @changer-groupe="changerGroupe"
    />
  </div>
</template>

<style scoped>
/* La trame de l'accueil : six colonnes en bureau, une seule sur téléphone, dans l'ordre de lecture.
   La carte et l'identité d'abord, puis ce que l'étudiant tient à jour, puis sa scolarité. */
.mon-profil {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 1rem;
}

@media (min-width: 1024px) {
  .mon-profil {
    grid-template-columns: repeat(6, minmax(0, 1fr));
  }

  .mon-profil__carte {
    grid-column: span 2;
  }

  .mon-profil__informations {
    grid-column: span 4;
  }

  .mon-profil__coordonnees,
  .mon-profil__scolarite {
    grid-column: span 3;
  }

  /* Le formulaire a besoin de la largeur : la scolarité passe dessous le temps de la saisie. */
  .mon-profil--edition .mon-profil__coordonnees,
  .mon-profil--edition .mon-profil__scolarite {
    grid-column: span 6;
  }
}

/* Deux colonnes seulement quand chaque ligne tient sur la sienne, menu latéral ouvert compris. */
@media (min-width: 1536px) {
  .mon-profil__liste {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 2rem;
  }
}

.mon-profil__liste {
  margin: 0;
  padding: 0;
}

.mon-profil__vide {
  font-weight: 400;
  color: var(--p-text-muted-color);
}

.mon-profil__note {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  margin: auto 0 0;
  font-size: 0.9375rem;
  color: var(--p-text-muted-color);
}
</style>
