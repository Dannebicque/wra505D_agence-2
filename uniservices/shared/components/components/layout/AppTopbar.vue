<script setup>
import { useLayout } from './composables/layout.js';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { AVAILABLE_ROLES } from "@utils/permissions";
import Logo from '@components/components/Logo.vue';
import AppSearch from './AppSearch.vue';
import NotificationsCloche from './NotificationsCloche.vue';
import { useAnneeUnivStore, useUsersStore } from "@stores";
import { useRoute, useRouter } from 'vue-router';
import { tools } from '@config/uniServices.js';
import noImage from "@images/photos_etudiants/noimage.png";
import { PermissionGuard } from "@components";

const anneeUnivStore = useAnneeUnivStore();
const userStore = useUsersStore();
const route = useRoute();
const router = useRouter();

const hasError = ref(false);

const deptItems = ref([]);
const departementLabel = ref('');
const anneesUniv = ref([]);
const anneeItems = ref([
  {
    label: 'Années universitaires',
    items: []
  }
]);
const rolesItems = ref([]);

const selectedAnneeUniversitaire = ref(null);

onMounted(async () => {
  selectedAnneeUniversitaire.value = localStorage.getItem('selectedAnneeUniv');
  await fetchData();
});

watch(() => userStore.user, async () => {
  await fetchData();
});

// Surveiller les changements du rôle temporaire pour mettre à jour l'interface utilisateur
watch(() => userStore.temporaryRole, () => {
  const hasTemporaryRole = typeof userStore.temporaryRole === 'string' && userStore.temporaryRole.length > 0;
  // Mettre à jour l'état actif des éléments de rôle lorsque le rôle temporaire change
  if (rolesItems.value.length > 0) {
    AVAILABLE_ROLES.forEach((role, index) => {
      if (rolesItems.value[index]) {
        rolesItems.value[index].active = hasTemporaryRole ?
          (userStore.temporaryRole === role.role) :
          userStore[role.property];
      }
    });
  }

  // afficher le role impersonnalisé dans la console pour le développement
  console.log('Rôle temporaire actuel:', userStore.temporaryRole);
});

const fetchData = async () => {
  try {
    // Les données sont déjà récupérées par initializeAppData, donc on les utilise simplement
    // Si anneesUniv est vide, on le récupère (solution de repli)
    if (anneeUnivStore.anneesUniv.length === 0) {
      await anneeUnivStore.getAllAnneesUniv();
    }

    // Préparer les années universitaires triées pour le menu déroulant
    const sortedAnnees = anneeUnivStore.anneesUniv.map(annee => ({
      id: annee.id,
      label: annee.libelle,
      isActif: annee.actif,
      command: () => selectAnneeUniversitaire(annee),
    })).sort((a, b) => b.label.localeCompare(a.label));
    anneesUniv.value = sortedAnnees;
    anneeItems.value[0].items = sortedAnnees;

    // Gérer l'année universitaire sélectionnée
    if (!selectedAnneeUniversitaire.value) {
      // Si aucune année n'est sélectionnée dans l'état local, utiliser celle du store ou définir la première
      if (anneeUnivStore.selectedAnneeUniv) {
        selectedAnneeUniversitaire.value = anneeUnivStore.selectedAnneeUniv;
      } else if (sortedAnnees.length > 0) {
        await anneeUnivStore.setSelectedAnneeUniv(sortedAnnees[0]);
        selectedAnneeUniversitaire.value = sortedAnnees[0];
      }
    } else {
      // Analyser l'année sélectionnée depuis localStorage
      selectedAnneeUniversitaire.value = JSON.parse(selectedAnneeUniversitaire.value);
      // S'assurer qu'elle a une propriété label
      if (selectedAnneeUniversitaire.value && selectedAnneeUniversitaire.value.libelle) {
        selectedAnneeUniversitaire.value.label = selectedAnneeUniversitaire.value.libelle;
      }
    }

    if (userStore.user) {
      // Mapper les rôles aux éléments du menu avec l'état actif et la fonction de commande
      rolesItems.value = AVAILABLE_ROLES.map(role => ({
        label: role.label,
        command: () => {
          if (userStore.temporaryRole === role.role) {
            userStore.clearTemporaryRole();
          } else {
            userStore.setTemporaryRole(role.role);
          }
        },
        active: (typeof userStore.temporaryRole === 'string' && userStore.temporaryRole.length > 0)
          ? (userStore.temporaryRole === role.role)
          : userStore[role.property]
      }));

      // Ajouter une option "Réinitialiser le rôle" à la fin
      rolesItems.value.push({
        label: 'Réinitialiser le rôle',
        command: () => userStore.clearTemporaryRole(),
        icon: 'pi pi-refresh'
      });
    }

    // Gérer les données des départements pour l'interface utilisateur
    if (userStore.user) {
      if (userStore.userType === 'personnels') {
        // Mapper les départements pour le menu déroulant
        deptItems.value = Array.isArray(userStore.departementsNotDefaut)
          ? userStore.departementsNotDefaut.map(departement => ({
            label: departement.libelle,
            id: departement.id,
            command: () => changeDepartement(departement.id)
          }))
          : [];

        // Définir le libellé du département par défaut
        departementLabel.value = userStore.departementDefaut?.libelle || '';
      } else {
        // Pour les utilisateurs non-personnel
        deptItems.value = [];
        departementLabel.value = userStore.departementDefaut?.libelle || '';
      }
    }
  } catch (error) {
    hasError.value = true;
    console.error('Error fetching data:', error);
  }
};

const props = defineProps({
  appName: {
    type: String,
    required: true
  },
  logoUrl: {
    type: String,
    required: false
  },
});

const { onMenuToggle, toggleDarkMode, isDarkTheme, layoutState } = useLayout();

// Ce bouton n'apparaît qu'en mobile : sur ordinateur, le menu a son propre bouton de repli.
const menuOuvert = computed(() => layoutState.staticMenuMobileActive);

// Menu « ⋮ » des écrans étroits : fermé au chargement, il se referme au clic ailleurs, à
// Échap et au changement de page. En bureau, ses actions restent toujours affichées.
const actionsOuvertes = ref(false);
const boutonActions = ref(null);
const menuActions = ref(null);

const fermerActions = (event) => {
  if (!actionsOuvertes.value) {
    return;
  }
  if (event.type === 'keydown') {
    if (event.key !== 'Escape') {
      return;
    }
    actionsOuvertes.value = false;
    boutonActions.value?.focus();
    return;
  }
  if (!menuActions.value?.contains(event.target) && !boutonActions.value?.contains(event.target)) {
    actionsOuvertes.value = false;
  }
};

watch(() => route.fullPath, () => {
  actionsOuvertes.value = false;
});

onMounted(() => {
  document.addEventListener('click', fermerActions);
  document.addEventListener('keydown', fermerActions);
});

onUnmounted(() => {
  document.removeEventListener('click', fermerActions);
  document.removeEventListener('keydown', fermerActions);
});

const anneeMenu = ref();
const toolsMenu = ref();
const profileMenu = ref();
const deptMenu = ref();
const rolesMenu = ref();

const estEtudiant = computed(() => userStore.userType === 'etudiants');

const optionsProfil = [
  {
    label: 'Options',
    items: [
      {
        label: 'Profil',
        icon: 'pi pi-user',
        command: () => {
          router.push('/profil');
        }
      },
      {
        label: 'Paramètres',
        icon: 'pi pi-cog',
        mortPourEtudiant: true
      },
      {
        label: 'Déconnexion',
        icon: 'pi pi-sign-out',
        command: () => {
          localStorage.removeItem('token');
          localStorage.removeItem('selectedAnneeUniv');
          document.cookie = 'token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
          window.location.replace(`${window.location.origin}/?logout=true`);
        }
      }
    ]
  }
];

// « Paramètres » ne mène nulle part : on ne le montre pas à l'étudiant, dont le département,
// retiré de la barre, s'affiche ici.
const profileItems = computed(() => optionsProfil.map(section => (estEtudiant.value
  ? {
      ...section,
      label: departementLabel.value || section.label,
      items: section.items.filter(item => !item.mortPourEtudiant)
    }
  : section)));

const toggleProfileMenu = (event) => {
  profileMenu.value.toggle(event);
};

const toggleAnneeMenu = (event) => {
  anneeMenu.value.toggle(event);
};

const toggleToolsMenu = (event) => {
  toolsMenu.value.toggle(event);
};

const toggleDeptMenu = (event) => {
  deptMenu.value.toggle(event);
};

const toggleRolesMenu = (event) => {
  rolesMenu.value.toggle(event);
};

const changeDepartement = async (departementId) => {
  try {
    await userStore.changeDepartement(departementId);
    deptItems.value = userStore.departementsNotDefaut.map(departementPersonnel => ({
      label: departementPersonnel.libelle,
      id: departementPersonnel.id,
      command: () => changeDepartement(departementPersonnel.id)
    }));
    departementLabel.value = userStore.departementDefaut.libelle;
  } catch (error) {
    hasError.value = true;
    console.error('Error changing department:', error);
  }
};

const initiales = computed(() => {
  if (userStore.user && userStore.user.name) {
    return userStore.user.name.split(' ').map(n => n[0]).join('');
  }
  return '';
});

const isEnabled = (item) => {
  return item.urlSlug === 'intranet' || userStore.applications.includes(item.urlSlug);
};

// Propriété calculée pour déterminer si le menu des rôles doit être affiché
const showRolesMenu = computed(() => {
  // Afficher le menu si l'utilisateur est un superAdmin ou a un rôle temporaire défini
  const hasTemporaryRole = typeof userStore.temporaryRole === 'string' && userStore.temporaryRole.length > 0;
  return userStore.isSuperAdmin || hasTemporaryRole;
});

const selectAnneeUniversitaire = (annee) => {
  // Passer l'objet annee original au store
  // Le store gérera la définition correcte de la propriété isActif
  anneeUnivStore.setSelectedAnneeUniv(annee);

  // Mettre à jour la valeur locale selectedAnneeUniversitaire avec la valeur du store
  selectedAnneeUniversitaire.value = anneeUnivStore.selectedAnneeUniv;

  // recharger la page
  window.location.reload();
};
</script>

<template>
  <header class="layout-topbar">
    <div class="layout-topbar-logo-container">
      <button
          v-if="route.name !== 'portail'"
          class="layout-menu-button layout-topbar-action"
          :aria-label="menuOuvert ? 'Fermer le menu' : 'Ouvrir le menu'"
          :aria-expanded="menuOuvert ? 'true' : 'false'"
          aria-controls="menu-principal"
          @click="onMenuToggle"
      >
        <i class="pi pi-bars" aria-hidden="true"></i>
      </button>

      <!-- L'étudiant a un seul menu : le nom du module ne lui apprend rien, le logo mène à l'accueil. -->
      <router-link to="/" class="layout-topbar-logo" :aria-label="estEtudiant ? 'Accueil' : undefined">
        <Logo :logo-url="logoUrl" alt="logo" class="rounded-xl p-2" /> <span v-if="!estEtudiant" class="text-lg">{{ appName }}</span>
      </router-link>
    </div>

    <div v-if="route.name !== 'portail'" class="layout-topbar-search hidden w-full max-w-md lg:block">
      <AppSearch input-id="recherche-globale" />
    </div>

    <div class="layout-topbar-actions">
      <div v-if="route.name !== 'portail'" class="layout-topbar-search lg:hidden">
        <AppSearch input-id="recherche-globale-mobile" />
      </div>

      <button
          ref="boutonActions"
          type="button"
          class="layout-topbar-menu-button layout-topbar-action"
          aria-label="Plus d'actions"
          :aria-expanded="actionsOuvertes ? 'true' : 'false'"
          aria-controls="actions-barre-haute"
          @click="actionsOuvertes = !actionsOuvertes"
      >
        <i class="pi pi-ellipsis-v" aria-hidden="true"></i>
      </button>

      <div
          id="actions-barre-haute"
          ref="menuActions"
          :class="['layout-topbar-menu lg:block', actionsOuvertes ? 'animate-scalein' : 'hidden']"
      >
        <div class="layout-topbar-menu-content">
          <router-link :to="{ name: 'portail' }" v-if="route.name !== 'portail' && !estEtudiant"
            class="layout-topbar-action layout-topbar-action-text">
            <i class="pi pi-arrow-left text-primary"></i>
            <span>Portail</span>
          </router-link>

          <button v-if="route.name !== 'portail' && !estEtudiant" type="button" class="layout-topbar-action layout-topbar-action-text"
            @click="toggleToolsMenu" aria-haspopup="true" aria-controls="tools_menu">
            <i class="pi pi-box text-primary"></i>
            <span>Applications</span>
          </button>
          <Menu ref="toolsMenu" id="tools_menu" :model="tools" :popup="true">
            <template #item="{ item, props }">
              <a v-if="item.url && isEnabled(item)" :href="item.url" v-ripple v-bind="props.action">
                <Logo :logo-url="item.logo" class="logo_menu" />
                <span class="ml-2">{{ item.name }}</span>
              </a>
            </template>
          </Menu>

          <button v-if="userStore.userType === 'personnels'" type="button"
            class="layout-topbar-action layout-topbar-action-text" @click="toggleDeptMenu" aria-haspopup="true"
            aria-controls="dept_menu">
            <i class="pi pi-arrow-right-arrow-left text-primary"></i>
            <span>{{ departementLabel }}</span>
          </button>
          <Menu ref="deptMenu" id="dept_menu" :model="deptItems" :popup="true" />

          <button v-if="showRolesMenu" type="button" class="layout-topbar-action layout-topbar-action-text"
            @click="toggleRolesMenu" aria-haspopup="true" aria-controls="roles_menu">
            <i class="pi pi-shield text-primary"></i>
            <span>Rôles</span>
          </button>
          <Menu ref="rolesMenu" id="roles_menu" :model="rolesItems" :popup="true" />

          <PermissionGuard permission="isPersonnel">
            <button type="button" class="layout-topbar-action layout-topbar-action-text" @click="toggleAnneeMenu"
              aria-haspopup="true" aria-controls="annee_menu">
              <i class="pi pi-calendar text-primary"></i>
              <span>{{ selectedAnneeUniversitaire?.label }}</span>
            </button>
            <Menu ref="anneeMenu" id="annee_menu" :model="anneeItems" :popup="true" />
          </PermissionGuard>

          <button v-if="!estEtudiant" type="button" class="layout-topbar-action">
            <i class="pi pi-inbox"></i>
            <span>Messages</span>
          </button>
          <NotificationsCloche v-if="estEtudiant" />
          <button v-else type="button" class="layout-topbar-action">
            <i class="pi pi-bell"></i>
            <span>Notifications</span>
          </button>
          <div class="layout-config-menu">
            <button type="button" class="layout-topbar-action" aria-label="Mode sombre" :aria-pressed="isDarkTheme" @click="toggleDarkMode">
              <i :class="['pi', { 'pi-moon': isDarkTheme, 'pi-sun': !isDarkTheme }]" aria-hidden="true"></i>
            </button>
          </div>
          <Button severity="secondary" rounded @click="toggleProfileMenu" aria-haspopup="true"
            aria-controls="profile_menu" aria-label="Mon profil" class="layout-topbar-action p-0!">
            <template v-if="userStore.userPhoto">
              <img :src="userStore.userPhoto" alt="photo de profil" class="rounded-full max-w-12 mx-auto">
            </template>
            <template v-else>
              <span class="text-gray-700 text-xl">{{ initiales }}</span>
            </template>
          </Button>
          <Menu ref="profileMenu" id="profile_menu" :model="profileItems" :popup="true" />
        </div>
      </div>

    </div>
  </header>
</template>
