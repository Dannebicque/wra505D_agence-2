<script setup>
import { onMounted, ref, computed } from 'vue';
import Logo from '@components/components/Logo.vue';
import LogoIut from '@images/logo/logo_iut.png';
import axios from 'axios';
import { tools } from '@config/uniServices.js';
import {ValidatedInput, validationRules} from "@components";
import { useEtablissementStore } from '@stores';
import {resolveLogoEtablissementUrl} from "@helpers";

const etablissementStore = useEtablissementStore();
const username = ref('');
const password = ref('');
const checked = ref(false);
const errorMessage = ref('');
const isLoading = ref(false);
const formErrors = ref({});
const formValid = ref(true);
const logoUrl = ref(LogoIut);

// Pagination variables
const currentPage = ref(0);
const itemsPerPage = 4;

// Compute paginated tools
const paginatedTools = computed(() => {
  const start = currentPage.value * itemsPerPage;
  const end = start + itemsPerPage;
  return tools.slice(start, end);
});

// Compute total pages
const totalPages = computed(() => Math.ceil(tools.length / itemsPerPage));

// Navigation methods
const nextPage = () => {
  if (currentPage.value < totalPages.value - 1) {
    currentPage.value++;
  }
};

const prevPage = () => {
  if (currentPage.value > 0) {
    currentPage.value--;
  }
};

const handleSubmit = async () => {
  if (!username.value || !password.value) {
    errorMessage.value = 'Veuillez remplir tous les champs';
    return;
  }

  isLoading.value = true;
  errorMessage.value = '';
  try {
    // Les cookies HTTP-only sont définis automatiquement par le serveur
    // Utilise le proxy Vite configuré pour /api
    await axios.post('/api/login', {
      username: username.value,
      password: password.value
    }, {
      withCredentials: true // Important: permet la réception des cookies
    });

    // Plus besoin de stocker le token dans localStorage
    // Le cookie HTTP-only est géré automatiquement par le navigateur
    location.href = '/app/auth/portail';
  } catch (error) {
    errorMessage.value = error.response && error.response.status === 401
        ? 'Login ou mot de passe incorrect'
        : 'Une erreur est survenue, veuillez contacter l\'administrateur du site';
  } finally {
    isLoading.value = false;
  }
};

// Détection de la touche Entrée : lance la connexion si les deux champs sont remplis
const onEnter = () => {
  if (username.value && password.value) {
    handleSubmit();
  }
};

const handleValidation = (field, result) => {
  formErrors.value = {
    ...formErrors.value,
    [field]: result.isValid ? null : result.errorMessage
  };
  formValid.value = Object.values(formErrors.value).every(error => error === null);
};

onMounted(async () => {
  try {
    const etablissement = await etablissementStore.etablissement;
    logoUrl.value = resolveLogoEtablissementUrl(etablissement?.logo_name);
  } catch (e) {
    logoUrl.value = LogoIut;
  }
});
</script>

<template>
  <div class="bg w-full h-screen fixed top-0 left-0 -z-10">
  </div>
  <div class="w-full min-h-screen flex justify-center items-center md:py-16 p-4">
    <!-- Le formulaire vient d'abord dans le code, pour être atteint en premier au clavier ; la
         présentation reste à sa gauche, ou au-dessus sur un téléphone. -->
    <div class="md:w-3/4 w-full h-full flex md:flex-row-reverse flex-col-reverse shadow-xl">
      <main id="contenu-principal" class="bg-white p-12 md:rounded-tr-xl md:rounded-br-xl md:rounded-bl-none rounded-br-xl rounded-bl-xl w-full flex flex-col gap-4">
        <div class="text-center mb-8">
          <h1 class="text-surface-900! dark:text-surface-0! text-3xl! font-medium! uppercase m-0!">Connexion</h1>
          <span class="text-muted-color font-medium">Etudiants, personnels de l'Université et vacataires, connectez-vous avec l'authentification de l'Université.</span>
        </div>
        <Button label="Connexion URCA" class="w-full" as="router-link" to="/"></Button>

        <Divider></Divider>

        <p class="text-center">Compte invité</p>
        <form @submit.prevent="handleSubmit" @keydown.enter.prevent="onEnter" class="flex flex-col">
          <ValidatedInput
              v-model="username"
              name="username"
              label="Login"
              type="text"
              validate-on-input
              :rules="validationRules.required"
              @validation="result => handleValidation('username', result)"
          />
          <ValidatedInput
              class="pwd"
              v-model="password"
              name="password"
              label="Mot de passe"
              type="password"
              validate-on-input
              :feedback="false"
              toggleMask
              :rules="validationRules.required"
              @validation="result => handleValidation('password', result)"
          />
          <div class="flex flex-col justify-between gap-4">
            <div class="flex items-center">
              <Checkbox v-model="checked" input-id="rememberme1" binary class="mr-2"></Checkbox>
              <label for="rememberme1">Se souvenir de moi</label>
            </div>
            <div class="flex justify-end items-center">
              <router-link to="/reset-password" class="font-medium underline ml-2 text-right cursor-pointer text-primary">Mot de passe oublié ?</router-link>
            </div>
            <div class="w-full flex flex-col gap-2">
              <Message v-if="errorMessage" severity="error">
                {{ errorMessage }}
              </Message>
              <Button :label="isLoading ? 'Connexion...' : 'Connexion invité'" class="w-full" type="submit"
                      severity="secondary" :disabled="isLoading || !formValid"></Button>
            </div>
          </div>
        </form>
        <small class="text-muted-color">En cas de problème de connexion, contactez le support à cette adresse :
          <a href="mailto:intranet.iut-troyes@univ-reims.fr" class="underline">intranet.iut-troyes@univ-reims.fr</a></small>
      </main>
      <div class="bg-black bg-opacity-60 text-white backdrop-blur-sm p-12 md:rounded-tl-xl md:rounded-bl-xl rounded-tl-xl rounded-tr-xl w-full flex flex-col gap-4">
        <div class="flex items-center w-full gap-4">
          <Logo :logo-url="logoUrl" alt="IUT de Troyes" class="w-1/4 rounded-md" @error="logoUrl = LogoIut"/>
          <div>
            <div class="text-2xl font-bold">Bienvenue sur UniServices</div>
            <div>Plateforme de gestion centralisée des services universitaires</div>
          </div>
        </div>
        <div class="hidden md:block md:h-full">
          <Divider></Divider>
          <div class="flex flex-col justify-between h-full">
            <ul class="h-full flex flex-col justify-start gap-6 py-4">
              <li v-for="tool in paginatedTools" :key="tool.name" class="w-full p-0 flex items-center gap-4">
                <Logo :logo-url="tool.logo" alt="" class="w-16 bg-white rounded-xl"/>
                <div>
              <span class="font-bold text-xl">
                {{ tool.name }}
              </span>
                  <p class="text-lg">{{ tool.description }}</p>
                </div>
              </li>
            </ul>
            <div v-if="totalPages > 1" class="flex justify-center items-center gap-2 mt-4">
              <button
                  type="button"
                  @click="prevPage"
                  :disabled="currentPage === 0"
                  aria-label="Applications précédentes"
                  class="pagination-btn"
                  :class="{ 'disabled': currentPage === 0 }"
              >
                <i class="pi pi-chevron-left" aria-hidden="true"></i>
              </button>
              <span class="text-white">{{ currentPage + 1 }} / {{ totalPages }}</span>
              <button
                  type="button"
                  @click="nextPage"
                  :disabled="currentPage === totalPages - 1"
                  aria-label="Applications suivantes"
                  class="pagination-btn"
                  :class="{ 'disabled': currentPage === totalPages - 1 }"
              >
                <i class="pi pi-chevron-right" aria-hidden="true"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
.bg {
  background-image: url("../assets/iut.jpg");
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
}

.pagination-btn {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background-color: rgba(255, 255, 255, 0.2);
  color: white;
  display: flex;
  justify-content: center;
  align-items: center;
  cursor: pointer;
  transition: background-color 0.3s;
}

.pagination-btn:focus-visible {
  outline: 2px solid white;
  outline-offset: 2px;
}

.pagination-btn:hover:not(.disabled) {
  background-color: rgba(255, 255, 255, 0.4);
}

.pagination-btn.disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
