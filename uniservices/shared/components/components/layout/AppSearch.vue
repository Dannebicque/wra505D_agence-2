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

// Mêmes règles que le portail (applications de l'utilisateur) et que le menu (permissions).
const pagesAccessibles = () => bundles
    .filter(bundle => bundle.menu && usersStore.applications.includes(bundle.name))
    .flatMap(bundle => (Array.isArray(bundle.menu) ? bundle.menu : [bundle.menu]))
    .flatMap(section => (section.items || []).map(item => ({ ...item, section: section.label })))
    .filter(item => item.to && (!item.permission || hasPermission(item.permission)))
    .map(item => ({ cle: `page-${item.to}`, type: 'page', libelle: item.label, detail: item.section, to: item.to }));

const rechercher = async ({ query }) => {
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
      placeholder="Recherche"
      class="w-full"
      input-class="w-full"
      @complete="rechercher"
      @option-select="ouvrir"
  >
    <template #optiongroup="{ option }">
      <span class="font-bold">{{ option.libelle }}</span>
    </template>
    <template #option="{ option }">
      <div class="flex flex-col">
        <span>{{ option.libelle }}</span>
        <small v-if="option.detail" class="text-muted-color">{{ option.detail }}</small>
      </div>
    </template>
  </AutoComplete>
</template>
