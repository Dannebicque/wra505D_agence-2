<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import Message from 'primevue/message';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import type { Document } from '@types';
import { documentService } from '@/service/documentService';
import {
  COLONNES_MAX,
  LIGNES_MAX,
  lettreColonne,
  lireClasseur,
  typeApercu,
  type FeuilleApercu,
} from '@/service/utils/apercuUtils';

const props = defineProps<{
  visible: boolean;
  document: Document | null;
}>();

defineEmits<{
  'update:visible': [value: boolean];
}>();

const etat = ref<'chargement' | 'pret' | 'erreur'>('chargement');
const urlFichier = ref<string | null>(null);
const feuilles = ref<FeuilleApercu[]>([]);
const type = computed(() => (props.document ? typeApercu(props.document.type) : null));

// Chaque ouverture incrémente ce compteur : une réponse arrivée après la fermeture, ou après le
// choix d'un autre document, est ignorée au lieu d'écraser l'aperçu affiché.
let chargementCourant = 0;

const liberer = () => {
  if (urlFichier.value) URL.revokeObjectURL(urlFichier.value);
  urlFichier.value = null;
  feuilles.value = [];
};

const charger = async () => {
  const numero = ++chargementCourant;
  liberer();
  const doc = props.document;
  const genre = type.value;
  if (!props.visible || !doc || !genre) return;

  etat.value = 'chargement';
  try {
    const fichier = await documentService.fetchFichier(doc.id);
    if (genre === 'tableur') {
      const lues = await lireClasseur(fichier);
      if (numero !== chargementCourant) return;
      feuilles.value = lues;
    } else {
      if (numero !== chargementCourant) return;
      // Type imposé : un fichier HTML déclaré PDF ne s'exécute pas dans l'iframe, qui partage
      // l'origine de l'intranet. Une image dans <img> n'exécute jamais de script.
      const contenu = genre === 'pdf' ? new Blob([fichier], { type: 'application/pdf' }) : fichier;
      urlFichier.value = URL.createObjectURL(contenu);
    }
    etat.value = 'pret';
  } catch {
    if (numero === chargementCourant) etat.value = 'erreur';
  }
};

watch(() => [props.visible, props.document?.id], charger, { immediate: true });
onBeforeUnmount(() => {
  chargementCourant++;
  liberer();
});

const lignesTableau = (feuille: FeuilleApercu) =>
  feuille.lignes.map((ligne, index) => ({
    numero: index + 1,
    ...Object.fromEntries(ligne.map((valeur, colonne) => [lettreColonne(colonne), valeur])),
  }));

const colonnes = (feuille: FeuilleApercu) =>
  Array.from({ length: feuille.lignes[0]?.length ?? 0 }, (_, index) => lettreColonne(index));
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    maximizable
    dismissable-mask
    :header="document ? `Aperçu : ${document.title}` : 'Aperçu'"
    :style="{ width: 'min(1100px, 95vw)' }"
    :content-style="{ minHeight: '50vh' }"
    @update:visible="$emit('update:visible', $event)"
  >
    <p v-if="etat === 'chargement'" role="status" class="flex items-center gap-2 text-muted-color py-8 justify-center">
      <i class="pi pi-spin pi-spinner" aria-hidden="true"></i>
      Chargement de l'aperçu…
    </p>

    <Message v-else-if="etat === 'erreur'" severity="warn" :closable="false">
      L'aperçu n'a pas pu être chargé : le fichier de ce document est peut-être indisponible.
    </Message>

    <template v-else-if="document">
      <iframe
        v-if="type === 'pdf' && urlFichier"
        :src="urlFichier"
        :title="`Contenu du document ${document.title}`"
        class="w-full h-[75vh] border-0 rounded-md"
      ></iframe>

      <img
        v-else-if="type === 'image' && urlFichier"
        :src="urlFichier"
        :alt="`Contenu du document ${document.title}`"
        class="block max-w-full max-h-[75vh] mx-auto object-contain"
      />

      <template v-else-if="type === 'tableur'">
        <p v-if="feuilles.length === 0" class="text-muted-color py-8 text-center">Ce classeur ne contient aucune feuille.</p>
        <Tabs v-else :value="0">
          <TabList v-if="feuilles.length > 1">
            <Tab v-for="(feuille, index) in feuilles" :key="feuille.nom" :value="index">{{ feuille.nom }}</Tab>
          </TabList>
          <TabPanels class="px-0!">
            <TabPanel v-for="(feuille, index) in feuilles" :key="feuille.nom" :value="index">
              <p v-if="feuille.tronquee" class="text-sm text-muted-color mb-2">
                Aperçu limité aux {{ LIGNES_MAX }} premières lignes et aux {{ COLONNES_MAX }} premières colonnes.
              </p>
              <DataTable
                :value="lignesTableau(feuille)"
                data-key="numero"
                size="small"
                show-gridlines
                scrollable
                scroll-height="65vh"
                :table-props="{ 'aria-label': `Feuille ${feuille.nom}` }"
              >
                <Column field="numero" header="Ligne" frozen class="text-muted-color tabular-nums" />
                <Column v-for="colonne in colonnes(feuille)" :key="colonne" :field="colonne" :header="colonne" />
              </DataTable>
            </TabPanel>
          </TabPanels>
        </Tabs>
      </template>
    </template>
  </Dialog>
</template>
