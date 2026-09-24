<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { getRechercheService } from '@requests';
import { useUsersStore } from '@stores';
import { hasPermission } from '@utils/permissions';
import { destinationResultat, filtrerPages, grouperResultats } from '@helpers/recherche.js';
import { bundles } from '../../../../packages/shell/assets/bundles-registry';

const props = defineProps({
  inputId: {
    type: String,
    required: true
  },
});

const router = useRouter();
const usersStore = useUsersStore();
const selection = ref(null);
const suggestions = ref([]);
const requete = ref('');

// Le groupe affiché au-dessus ne suffit pas à un lecteur d'écran, qui lit l'option seule.
const roles = { etudiant: 'Étudiant', personnel: 'Personnel' };

// Mêmes règles que le portail (applications de l'utilisateur) et que le menu (permissions).
const pagesAccessibles = () => bundles
    .filter(bundle => bundle.menu && usersStore.applications.includes(bundle.name))
    .flatMap(bundle => (Array.isArray(bundle.menu) ? bundle.menu : [bundle.menu]))
    .flatMap(section => (section.items || []).map(item => ({ ...item, section: section.label })))
    .filter(item => item.to && (!item.permission || hasPermission(item.permission)))
    .map(item => ({ cle: `page-${item.to}`, type: 'page', libelle: item.label, detail: item.section, to: item.to }));

const rechercher = async ({ query }) => {
  requete.value = query;
  const pages = filtrerPages(pagesAccessibles(), query);
  let resultats = [];
  try {
    resultats = await getRechercheService(query);
  } catch {
    // Les pages restent proposées si l'API ne répond pas.
  }
  suggestions.value = grouperResultats([...pages, ...resultats]);
};

const ouvrir = ({ value }) => {
  const destination = destinationResultat(value);
  selection.value = null;
  if (destination?.href) {
    window.location.href = destination.href;
  } else if (destination?.to) {
    router.push(destination.to);
  }
};
</script>

<template>
  <label :for="props.inputId" class="sr-only">Rechercher une page, une personne, une matière ou un document</label>
  <AutoComplete
      v-model="selection"
      :input-id="props.inputId"
      :suggestions="suggestions"
      option-label="libelle"
      option-group-label="libelle"
      option-group-children="items"
      :min-length="2"
      :delay="250"
      placeholder="Rechercher une page, une personne, une matière…"
      class="w-full"
      input-class="w-full"
      overlay-class="app-search-overlay"
      @complete="rechercher"
      @option-select="ouvrir"
  >
    <template #optiongroup="{ option }">
      <span class="font-bold">{{ option.libelle }}</span>
    </template>
    <template #option="{ option }">
      <div class="flex w-full items-center gap-3 whitespace-normal">
        <div class="flex min-w-0 flex-col">
          <span><span v-if="roles[option.type]" class="sr-only">{{ roles[option.type] }} : </span>{{ option.libelle }}</span>
          <!-- Texte atténué de la DA : celui du thème tombe à 4,34:1 sur l'option survolée. -->
          <small v-if="option.detail || option.mail" class="break-all text-[#676D75] dark:text-[#A7ACB4]">
            {{ [option.detail, option.mail].filter(Boolean).join(' · ') }}
          </small>
        </div>
        <span v-if="option.mail" class="ml-auto flex shrink-0 items-center gap-1 text-sm">
          <i class="pi pi-envelope" aria-hidden="true"></i>Écrire
        </span>
      </div>
    </template>
    <template #empty>
      <span>Aucun résultat pour « {{ requete }} »</span>
    </template>
  </AutoComplete>
</template>

<style>
/*
 * Le fond de l'option active ne se distingue du blanc qu'à 1,1:1 : on marque son bord gauche.
 * Bordure de la DA en clair (3,44:1) ; en sombre, #666B73 tombe à 2,78:1 sur ce fond, d'où #8A9099.
 * La liste est rendue hors du composant, le style ne peut donc pas être scoped.
 */
.app-search-overlay .p-autocomplete-option.p-focus {
  box-shadow: inset 4px 0 0 #7E848D;
}

.app-dark .app-search-overlay .p-autocomplete-option.p-focus {
  box-shadow: inset 4px 0 0 #8A9099;
}
</style>
