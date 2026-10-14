<script setup>
import { ProfilPersonnel, ProfilEtudiant, TopbarComponent, HeaderComponent } from '@components';
import {useUsersStore} from "@stores";
import {computed} from "vue";

const props = defineProps({
  logoUrl: {
    type: String,
    default: '/assets/logo.png',
  },
  appName: {
    type: String,
    default: 'App',
  },
  user: {
    type: Object,
    default: () => ({}),
  }
});

const store = useUsersStore();
const isPersonnel = computed(() => store.userType === 'personnels');
const isEtudiant = computed(() => store.userType === 'etudiants');
</script>

<template>
  <TopbarComponent :app-name :logo-url/>
  <div class="layout-main-container mt-16">
    <main id="contenu-principal" class="layout-main">
      <ProfilPersonnel v-if="isPersonnel" />
      <template v-if="isEtudiant">
        <HeaderComponent
            icon="pi pi-id-card"
            titre="Profil"
            description="Consultez votre profil et les informations associées"
        />
        <ProfilEtudiant />
      </template>
    </main>
  </div>
</template>

<style>
</style>
