<template>
  <HeaderComponent
      icon="pi pi-folder"
      titre="Documents"
      description="Les documents de vos matières, de vos SAÉ et du département"
  />
  <div class="min-h-screen lg:flex">
    <Toast />
    <ConfirmDialog />

    <!-- Sidebar -->
    <Sidebar
        v-bind="panneau"
        @search="handleSearch"
        @openUploadModal="showUploadModal = true"
        class="max-lg:hidden! me-3"
    />

    <!-- Sur un écran étroit, le panneau prendrait la place de la liste : il s'ouvre à la demande. -->
    <Drawer v-model:visible="filtresOuverts" header="Filtres" class="w-full! sm:w-80!">
      <Sidebar
          v-bind="panneau"
          @search="handleSearch"
          @openUploadModal="showUploadModal = true"
          class="w-full! border-r-0!"
      />
    </Drawer>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Loading State with Shared Skeletons -->
      <div v-if="loading" class="flex-1 overflow-y-auto p-4 space-y-4">
        <div class="flex justify-between items-center mb-6">
          <div class="h-8 w-48 bg-gray-200 rounded animate-pulse"></div>
          <div class="h-8 w-32 bg-gray-200 rounded animate-pulse"></div>
        </div>
        <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <CardSkeleton v-for="i in 8" :key="i" />
        </div>
        <div v-else class="space-y-3">
          <ListSkeleton v-for="i in 6" :key="i" />
        </div>
      </div>

      <!-- Content -->
      <div v-else class="flex-1 overflow-y-auto lg:p-4">
        <p role="status" class="sr-only">{{ annonceResultats }}</p>
        <Button
            class="lg:hidden! mb-3"
            icon="pi pi-filter"
            label="Filtres"
            outlined
            aria-haspopup="dialog"
            :badge="nombreFiltresPanneau > 0 ? String(nombreFiltresPanneau) : undefined"
            :aria-label="nombreFiltresPanneau > 0 ? `Filtres, ${nombreFiltresPanneau} actif${nombreFiltresPanneau > 1 ? 's' : ''}` : 'Filtres'"
            @click="filtresOuverts = true"
        />
        <FiltresActifs :filtres="filtresActifs" :tout-effacer="lienFiltres(SANS_FILTRE)" />
        <DocumentGrid
            v-if="viewMode === 'grid'"
            :documents="filteredDocuments"
            :title="getTitle()"
            :sort-field="sortField"
            :sort-order="sortOrder"
            :pagination-info="paginationInfo"
            :view-mode="viewMode"
            :empty-message="getEmptyMessage()"
            @selectDocument="openDetailDrawer"
            @downloadDocument="handleDownloadDocument"
            @deleteDocument="confirmDeleteDocument"
            @toggleFavorite="toggleFavorite"
            @sort="handleSort"
            @pageChange="handlePageChange"
            @changeView="handleViewModeChange"
        />

        <DocumentList
            v-else
            :documents="filteredDocuments"
            :title="getTitle()"
            :sort-field="sortField"
            :sort-order="sortOrder"
            :pagination-info="paginationInfo"
            :view-mode="viewMode"
            :empty-message="getEmptyMessage()"
            @selectDocument="openDetailDrawer"
            @downloadDocument="handleDownloadDocument"
            @deleteDocument="confirmDeleteDocument"
            @toggleFavorite="toggleFavorite"
            @sort="handleSort"
            @pageChange="handlePageChange"
            @changeView="handleViewModeChange"
        />
      </div>
    </div>

    <!-- Detail Drawer -->
    <DocumentDetailDrawer
      v-model:visible="showDetailDrawer"
      :document="selectedDocument"
      :category-name="selectedDocumentCategoryName"
      @download="handleDownloadDocument"
      @delete="confirmDeleteDocument"
      @toggleFavorite="toggleFavorite"
    />

    <!-- Upload Modal -->
    <DocumentUploadModal
      v-model:visible="showUploadModal"
      :categories="categories"
      @submit="handleCreateDocument"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick, type Ref } from 'vue';
import { useRoute, useRouter, type RouteLocationRaw } from 'vue-router';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import Drawer from 'primevue/drawer';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';

import Sidebar from '@/components/Documents/Sidebar.vue';
import DocumentGrid from '@/components/Documents/DocumentGrid.vue';
import DocumentList from '@/components/Documents/DocumentList.vue';
import DocumentUploadModal from '@/components/Documents/DocumentUploadModal.vue';
import DocumentDetailDrawer from '@/components/Documents/DocumentDetailDrawer.vue';
import FiltresActifs, { type FiltreActif } from '@/components/Documents/FiltresActifs.vue';
import { documentService } from '@/service/documentService';
import { classerParEnseignement, libelleEnseignement } from '@/service/utils/enseignementUtils';
import {
  aDesFiltres,
  ecrireFiltres,
  filtrerDocuments,
  lireFiltres,
  texteDuDocument,
  trierDocuments,
  type FiltresDocuments,
} from '@/service/utils/filtresDocuments';
import { correspond } from '@helpers/recherche';
import { CardSkeleton, HeaderComponent, ListSkeleton } from '@components';
import { useSecurity } from '@stores';
import type { Category, Document, SortField, SortOrder, PaginationInfo, ViewMode } from '@types';

const toast = useToast();
const confirm = useConfirm();
const route = useRoute();
const router = useRouter();

// State
const loading = ref(true);
const showUploadModal = ref(false);
const showDetailDrawer = ref(false);
const selectedDocument = ref<Document | null>(null);

const categories = ref<Category[]>([]);
const documentsList = ref<Document[]>([]);
const filtres = computed(() => lireFiltres(route.query));
const selectedCategory = computed(() => filtres.value.categorie);
const selectedEnseignement = computed(() => filtres.value.enseignement);
const showFavorites = computed(() => filtres.value.favoris);
const searchQuery = computed(() => filtres.value.recherche);
const sortField = computed(() => filtres.value.tri.field);
const sortOrder = computed(() => filtres.value.tri.order);
const currentPage = ref(1);
const filtresOuverts = ref(false);
const itemsPerPage = ref(20);
const viewMode = ref<ViewMode>('grid');

// Computed
const totalDocuments = computed(() => documentsList.value.length);
const favoriteCount = computed(() => documentsList.value.filter(d => d.isFavorite).length);
const classementEnseignements = computed(() => classerParEnseignement(documentsList.value));
const nombreFiltresPanneau = computed(() => [selectedCategory.value, selectedEnseignement.value, showFavorites.value].filter(Boolean).length);

const selectedDocumentCategoryName = computed(() => {
  if (!selectedDocument.value?.categoryId) return undefined;
  const findCat = (cats: Category[]): Category | undefined => {
    for (const c of cats) {
      if (c.id === selectedDocument.value?.categoryId) return c;
      if (c.children) {
        const res = findCat(c.children);
        if (res) return res;
      }
    }
    return undefined;
  };
  return findCat(categories.value)?.name;
});

const filteredDocuments = computed(() => {
  const retenus = filtrerDocuments(documentsList.value, categories.value, filtres.value);
  const recherche = filtres.value.recherche;
  const trouves = recherche.trim() === ''
    ? retenus
    : retenus.filter(document => correspond(recherche, texteDuDocument(document)));

  return trierDocuments(trouves, filtres.value.tri);
});

const paginationInfo = computed((): PaginationInfo => {
  const totalItems = filteredDocuments.value.length;
  const totalPages = Math.ceil(totalItems / itemsPerPage.value);

  return {
    currentPage: currentPage.value,
    totalPages,
    totalItems,
    itemsPerPage: itemsPerPage.value
  };
});

// Methods
const loadData = async () => {
  loading.value = true;
  try {
    const security = useSecurity();
    const activePackages = security.activePackages || [];
    const currentDepartmentId = security.currentDepartment?.id ? String(security.currentDepartment.id) : undefined;

    const [fetchedCategories, fetchedDocs, favoris] = await Promise.all([
      documentService.fetchCategories({ activePackages, currentDepartmentId }),
      documentService.fetchDocuments(),
      documentService.fetchFavoris()
    ]);
    categories.value = fetchedCategories;
    documentsList.value = fetchedDocs.map(doc => ({ ...doc, isFavorite: favoris.has(doc.id) }));
    documentService.updateCategoryCounts(categories.value, documentsList.value);
  } catch (e) {
    console.error('Error loading documents:', e);
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les documents depuis l\'API', life: 4000 });
  } finally {
    loading.value = false;
  }
};

const openDetailDrawer = (doc: Document) => {
  selectedDocument.value = doc;
  showDetailDrawer.value = true;
};

const SANS_FILTRE: Partial<FiltresDocuments> = { categorie: null, enseignement: null, favoris: false, recherche: '' };

// L'adresse porte les filtres : chaque lien la modifie, et la liste la suit.
const lienFiltres = (modifications: Partial<FiltresDocuments>): RouteLocationRaw => ({
  query: ecrireFiltres({ ...filtres.value, ...modifications }),
});

watch(() => route.query, () => {
  currentPage.value = 1;
});

// Choisir un filtre referme le panneau mobile sur la liste. Taper une recherche ne le ferme pas.
watch([selectedCategory, selectedEnseignement, showFavorites], () => {
  filtresOuverts.value = false;
});

// Le Drawer de PrimeVue, contrairement au Dialog, ne rend pas le focus en se fermant : il
// retomberait en haut de la page, et l'utilisateur au clavier perdrait sa place.
const rendreLeFocusALaFermeture = (visible: Ref<boolean>) => {
  let declencheur: HTMLElement | null = null;
  watch(visible, (ouvert) => {
    if (ouvert) {
      declencheur = document.activeElement as HTMLElement | null;
      return;
    }
    nextTick(() => declencheur?.focus());
  });
};
rendreLeFocusALaFermeture(showDetailDrawer);
rendreLeFocusALaFermeture(filtresOuverts);

const panneau = computed(() => ({
  categories: categories.value,
  enseignements: classementEnseignements.value,
  selectedCategory: selectedCategory.value,
  selectedEnseignement: selectedEnseignement.value,
  showFavorites: showFavorites.value,
  recherche: searchQuery.value,
  lien: lienFiltres,
  totalDocuments: totalDocuments.value,
  favoriteCount: favoriteCount.value,
}));

// Remplacer plutôt qu'empiler : une entrée d'historique par lettre tapée rendrait « Précédent »
// inutilisable.
const handleSearch = (query: string) => {
  router.replace(lienFiltres({ recherche: query }));
};

const handleSort = ({ field, order }: { field: SortField; order: SortOrder }) => {
  router.push(lienFiltres({ tri: { field, order } }));
};

const handlePageChange = (page: number) => {
  currentPage.value = page;
};

const handleViewModeChange = (mode: ViewMode) => {
  viewMode.value = mode;
};

const toggleFavorite = async (documentId: string) => {
  const doc = documentsList.value.find(d => d.id === documentId);
  if (doc) {
    try {
      const newStatus = await documentService.toggleFavorite(documentId, doc.isFavorite);
      doc.isFavorite = newStatus;
      toast.add({
        severity: 'info',
        summary: 'Favoris',
        detail: newStatus ? `"${doc.title}" ajouté aux favoris` : `"${doc.title}" retiré des favoris`,
        life: 2500
      });
    } catch (e) {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Échec de la mise à jour des favoris', life: 3000 });
    }
  }
};

const handleDownloadDocument = (doc: Document) => {
  toast.add({
    severity: 'success',
    summary: 'Téléchargement',
    detail: `Préparation du téléchargement de "${doc.title}"`,
    life: 3000
  });
};

const confirmDeleteDocument = (doc: Document) => {
  confirm.require({
    message: `Voulez-vous vraiment supprimer définitivement "${doc.title}" ?`,
    header: 'Confirmation de suppression',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Oui, supprimer',
    rejectLabel: 'Annuler',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await documentService.deleteDocument(doc.id);
        documentsList.value = documentsList.value.filter(d => d.id !== doc.id);
        documentService.updateCategoryCounts(categories.value, documentsList.value);
        if (selectedDocument.value?.id === doc.id) {
          showDetailDrawer.value = false;
          selectedDocument.value = null;
        }
        toast.add({ severity: 'success', summary: 'Supprimé', detail: 'Document supprimé avec succès', life: 3000 });
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de supprimer le document', life: 3000 });
      }
    }
  });
};

const handleCreateDocument = async (docData: { titre: string; description?: string; type: string; categoryId?: string; tags?: string[] }) => {
  try {
    const security = useSecurity();
    const currentDepartmentId = security.currentDepartment?.id ? String(security.currentDepartment.id) : undefined;
    const created = await documentService.createDocument({
      ...docData,
      departementId: currentDepartmentId
    });
    documentsList.value.unshift(created);
    documentService.updateCategoryCounts(categories.value, documentsList.value);
    toast.add({ severity: 'success', summary: 'Document créé', detail: `"${created.title}" a été ajouté`, life: 3000 });
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de créer le document sur l\'API', life: 3000 });
  }
};

const trouverCategorie = (liste: Category[], id: string): Category | undefined => {
  for (const categorie of liste) {
    const trouvee = categorie.id === id ? categorie : trouverCategorie(categorie.children ?? [], id);
    if (trouvee) {
      return trouvee;
    }
  }
  return undefined;
};

const libelleEnseignementChoisi = computed(() => {
  if (!selectedEnseignement.value) {
    return null;
  }
  const enseignement = documentsList.value.find(d => d.enseignement?.id === selectedEnseignement.value)?.enseignement;
  return enseignement ? libelleEnseignement(enseignement) : 'Enseignement inconnu';
});

const nomCategorieChoisie = computed(() => {
  if (!selectedCategory.value) {
    return null;
  }
  return trouverCategorie(categories.value, selectedCategory.value)?.name ?? 'Catégorie inconnue';
});

const filtresActifs = computed((): FiltreActif[] => [
  showFavorites.value ? { cle: 'favoris', libelle: 'Favoris', retrait: lienFiltres({ favoris: false }) } : null,
  libelleEnseignementChoisi.value ? { cle: 'enseignement', libelle: libelleEnseignementChoisi.value, retrait: lienFiltres({ enseignement: null }) } : null,
  nomCategorieChoisie.value ? { cle: 'categorie', libelle: nomCategorieChoisie.value, retrait: lienFiltres({ categorie: null }) } : null,
  searchQuery.value.trim() ? { cle: 'recherche', libelle: `« ${searchQuery.value.trim()} »`, retrait: lienFiltres({ recherche: '' }) } : null,
].filter((filtre): filtre is FiltreActif => filtre !== null));

const annonceResultats = computed(() => {
  const nombre = filteredDocuments.value.length;
  const documents = nombre > 1 ? `${nombre} documents` : `${nombre} document`;
  if (!aDesFiltres(filtres.value)) {
    return documents;
  }
  return nombre > 1 ? `${documents} correspondent aux filtres` : `${documents} correspond aux filtres`;
});

const getTitle = () => [showFavorites.value ? 'Favoris' : null, libelleEnseignementChoisi.value, nomCategorieChoisie.value]
  .filter(Boolean)
  .join(' · ') || 'Tous les documents';

const getEmptyMessage = () => (aDesFiltres(filtres.value)
  ? 'Aucun document ne correspond à ces filtres. Retirez-en un pour élargir la liste.'
  : 'Aucun document pour le moment.');

// Lifecycle
onMounted(() => {
  loadData();
});
</script>
