<script setup>
import { ProfilPersonnel, MonProfil, HeaderComponent } from '@components';
import {useUsersStore} from "@stores";
import {computed, onMounted} from "vue";

const store = useUsersStore();
const isPersonnel = computed(() => store.userType === 'personnels');
const isEtudiant = computed(() => store.userType === 'etudiants');

onMounted(async () => {
  // Assurons-nous que les données utilisateur sont chargées
  if (!store.isLoaded) {
    await store.getUser();
  }
})
</script>

<template>
  <!-- La carte de l'étudiant ouvre la page, comme celle de l'accueil : le titre reste pour les
       lecteurs d'écran. -->
  <template v-if="isEtudiant">
    <h1 class="sr-only">Profil</h1>
    <MonProfil />
  </template>
  <template v-else>
    <HeaderComponent
        icon="pi pi-id-card"
        titre="Profil"
        description="Consultez votre profil et les informations associées"
    />
    <div class="card">
      <ProfilPersonnel v-if="isPersonnel" />
    </div>
  </template>
</template>

<style>
</style>
