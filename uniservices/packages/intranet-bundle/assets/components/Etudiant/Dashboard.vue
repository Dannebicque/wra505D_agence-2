<script setup>
import {onMounted, ref} from 'vue';
import {getMaScolariteService, getMonCahierDeTexteService, getWidgetDataByCodeService} from '@requests';
import BlocAccueil from '@/components/Etudiant/dashboard/BlocAccueil.vue';
import CarteJournee from '@/components/Etudiant/dashboard/CarteJournee.vue';
import AFaire from '@/components/Etudiant/dashboard/AFaire.vue';
import NotesWidget from '@/components/Etudiant/dashboard/NotesWidget.vue';
import NouveauxDocuments from '@/components/Etudiant/dashboard/NouveauxDocuments.vue';
import Actualites from '@/components/Etudiant/dashboard/Actualites.vue';
import ContactsWidget from '@/components/Etudiant/dashboard/ContactsWidget.vue';

// Le relevé sert à « À faire » et aux notes : une seule lecture pour les deux.
const releve = ref(null);
const erreurReleve = ref(false);
const echeances = ref(null);
const erreurEcheances = ref(false);
const contacts = ref(null);

onMounted(() => {
  getMaScolariteService()
      .then(reponse => {
        releve.value = reponse;
      })
      .catch(() => {
        erreurReleve.value = true;
      });
  getMonCahierDeTexteService()
      .then(reponse => {
        echeances.value = reponse.echeances ?? [];
      })
      .catch(() => {
        erreurEcheances.value = true;
      });
  getWidgetDataByCodeService('intranet.contacts')
      .then(reponse => {
        contacts.value = reponse;
      })
      .catch(() => {
        contacts.value = {items: []};
      });
});
</script>

<template>
  <!-- La page ouvre sur la journée : son titre reste pour les lecteurs d'écran, comme l'agenda.
       Sans lui, rien ne sépare plus la journée de la barre du haut, qui la recouvre en mobile. -->
  <section class="col-span-12 lg:col-span-10 mt-10 md:mt-0">
    <h1 class="sr-only">Accueil</h1>

    <!-- Des rangées par ordre d'importance, lues de haut en bas comme sur un téléphone : la
         journée, puis ce qui demande une action et les notes, puis ce qu'on consulte. -->
    <div class="accueil">
      <CarteJournee class="accueil__journee"/>

      <BlocAccueil titre="À faire" icone="pi pi-flag" teinte="evaluation" class="accueil__a-faire">
        <AFaire :releve="releve" :echeances="echeances" :erreur="erreurReleve || erreurEcheances"/>
      </BlocAccueil>

      <BlocAccueil class="accueil__notes" titre="Dernières notes" icone="pi pi-chart-bar" teinte="tp" lien="/intranet/scolarite" texte-lien="Toutes mes notes">
        <NotesWidget :releve="releve" :erreur="erreurReleve"/>
      </BlocAccueil>

      <BlocAccueil class="accueil__documents" titre="Nouveaux documents" icone="pi pi-folder-open" teinte="cm" lien="/documents" texte-lien="Tous les documents">
        <NouveauxDocuments/>
      </BlocAccueil>

      <BlocAccueil class="accueil__actualites" titre="Actualités" icone="pi pi-megaphone" teinte="td">
        <Actualites/>
      </BlocAccueil>

      <BlocAccueil class="accueil__contacts" titre="Contacts" icone="pi pi-phone" teinte="sae">
        <ContactsWidget v-if="contacts" :data="contacts"/>
        <Skeleton v-else height="5rem" border-radius="14px" aria-hidden="true"/>
      </BlocAccueil>
    </div>
  </section>
</template>

<style scoped>
.accueil {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 1rem;
}

/* Trois rangées, chacune de blocs de même poids : la journée seule, puis deux blocs, puis trois. */
@media (min-width: 1024px) {
  .accueil {
    grid-template-columns: repeat(6, minmax(0, 1fr));
  }

  .accueil__journee {
    grid-column: span 6;
  }

  .accueil__a-faire,
  .accueil__notes {
    grid-column: span 3;
  }

  .accueil__documents,
  .accueil__actualites,
  .accueil__contacts {
    grid-column: span 2;
  }
}
</style>
