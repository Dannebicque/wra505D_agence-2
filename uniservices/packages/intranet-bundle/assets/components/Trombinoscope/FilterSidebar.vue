<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import type { FilterState } from '@types';
import { getDepartementSemestresService, getGroupesSemestreService } from '@requests';
import { useUsersStore } from '@stores';

const usersStore = useUsersStore();
const departementId = computed(() => usersStore.departementDefaut?.id);

interface Props {
  filters: FilterState;
  students: any[];
  staff: any[];
  anneeUnivLabel?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  updateFilters: [filters: Partial<FilterState>];
  resetFilters: [];
  exportData: [];
  exportTrombiPdf: [];
  exportListingExcel: [];
  exportListingPdf: [];
}>();

const loaded = ref(false);
const semesters = ref<Array<{ id: number; libelle: string; actif?: boolean }>>([]);
const availableGroups = ref<Array<{ id: number; libelle: string; type: string }>>([]);
const loadingGroups = ref(false);

const loadSemesters = async () => {
  if (!departementId.value) return;
  try {
    const res = await getDepartementSemestresService(departementId.value, true);
    const list = Array.isArray(res) ? res : (res?.member || []);
    // Strictly filter active semesters
    semesters.value = list.filter((sem: any) => sem.actif === true);
  } catch (err) {
    console.error('Erreur chargement semestres:', err);
    semesters.value = [];
  } finally {
    loaded.value = true;
  }
};

onMounted(async () => {
  await loadSemesters();
});

watch(departementId, async (newVal, oldVal) => {
  if (newVal && newVal !== oldVal) {
    await loadSemesters();
  }
});

const updateAvailableGroups = async () => {
  const currentSem = props.filters.studentFilters.semester;
  const currentType = props.filters.studentFilters.groupType;

  if (!currentSem || !currentType) {
    availableGroups.value = [];
    return;
  }

  loadingGroups.value = true;
  try {
    const res = await getGroupesSemestreService(currentSem);
    const member: any[] = res?.member || res?.data?.member || (Array.isArray(res) ? res : []);
    const filtered = member.filter((g: any) => g.type === currentType);
    filtered.sort((a: any, b: any) => (a.libelle || '').localeCompare(b.libelle || ''));
    availableGroups.value = filtered.map((g: any) => ({
      id: g.id,
      libelle: g.libelle,
      type: g.type,
    }));
  } catch (err) {
    console.error('Erreur chargement groupes:', err);
    availableGroups.value = [];
  } finally {
    loadingGroups.value = false;
  }
};

const availableStaffStatuts = computed(() => {
  const statuts = props.staff.map((s: any) => s.statut).filter(Boolean);
  return [...new Set(statuts)].sort();
});

const hasActiveFilters = computed(() => {
  if (props.filters.searchTerm) return true;
  if (props.filters.mode === 'students') {
    return Boolean(
        props.filters.studentFilters.semester ||
        props.filters.studentFilters.groupType ||
        props.filters.studentFilters.group
    );
  }
  return Boolean(props.filters.staffFilters.statut || props.filters.staffFilters.department);
});

const searchInputRef = ref<HTMLInputElement | null>(null);

const focusSearch = () => {
  searchInputRef.value?.focus();
};

defineExpose({
  focusSearch,
});

const updateMode = (mode: 'students' | 'staff') => {
  emit('updateFilters', {
    mode,
    searchTerm: '',
    studentFilters: {
      semester: null,
      groupType: null,
      group: null,
    },
    staffFilters: {
      statut: null,
      department: null,
    },
  });
  availableGroups.value = [];
};

const updateStudentSemester = (semester: number | null) => {
  emit('updateFilters', {
    studentFilters: {
      ...props.filters.studentFilters,
      semester,
      groupType: null,
      group: null,
    },
  });
  availableGroups.value = [];
};

const updateStudentGroupType = (groupType: 'CM' | 'TD' | 'TP' | null) => {
  emit('updateFilters', {
    studentFilters: {
      ...props.filters.studentFilters,
      groupType,
      group: null,
    },
  });
  if (groupType) {
    updateAvailableGroups();
  } else {
    availableGroups.value = [];
  }
};

const updateStudentGroup = (group: number | string | null) => {
  emit('updateFilters', {
    studentFilters: {
      ...props.filters.studentFilters,
      group,
    },
  });
};

const updateStaffStatut = (statut: string | null) => {
  emit('updateFilters', {
    staffFilters: {
      ...props.filters.staffFilters,
      statut,
    },
  });
};

const updateSearch = (searchTerm: string) => {
  emit('updateFilters', { searchTerm });
};

const updateSortBy = (sortBy: any) => {
  emit('updateFilters', { sortBy });
};

const toggleSortOrder = () => {
  const nextOrder = props.filters.sortOrder === 'asc' ? 'desc' : 'asc';
  emit('updateFilters', { sortOrder: nextOrder });
};

const updateViewMode = (viewMode: 'grid' | 'list') => {
  emit('updateFilters', { viewMode });
};
</script>

<template>
  <aside class="w-80 bg-surface-0 dark:bg-surface-900 border-r border-surface flex flex-col h-full shadow-sm flex-shrink-0">
    <!-- Header -->
    <div class="p-5 border-b border-surface flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center border border-primary-200/60 shadow-2xs">
          <i class="pi pi-filter text-sm" aria-hidden="true"></i>
        </div>
        <div>
          <h2 class="text-base font-bold text-color leading-tight">Filtres</h2>
          <span v-if="anneeUnivLabel" class="text-xs text-muted-color font-semibold">
            {{ anneeUnivLabel }}
          </span>
        </div>
      </div>
      <button
          v-if="hasActiveFilters"
          @click="emit('resetFilters')"
          class="text-xs text-primary-700 hover:text-primary-900 bg-primary-50 hover:bg-primary-100 px-2.5 py-1 rounded-lg border border-primary-200 font-semibold inline-flex items-center gap-1.5 transition-colors cursor-pointer"
      >
        <i class="pi pi-refresh text-[10px]" aria-hidden="true"></i>
        <span>Réinitialiser</span>
      </button>
    </div>

    <!-- Scrollable content -->
    <div class="flex-1 overflow-y-auto p-5 space-y-6">
      <!-- Mode Toggle (Segmented control) -->
      <div>
        <p id="trombi-affichage" class="block text-xs font-bold text-color uppercase tracking-wider mb-2">
          Affichage
        </p>
        <div role="group" aria-labelledby="trombi-affichage" class="grid grid-cols-2 p-1 bg-emphasis rounded-xl border border-surface">
          <button
              @click="updateMode('students')"
              type="button"
              :aria-pressed="filters.mode === 'students'"
              :class="[
              'flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs transition-all duration-200 cursor-pointer',
              filters.mode === 'students'
                ? 'bg-surface-0 dark:bg-surface-900 text-primary-700 shadow-xs font-bold border border-surface'
                : 'text-muted-color hover:text-color font-semibold'
            ]"
          >
            <i class="pi pi-users text-xs" aria-hidden="true"></i>
            <span>Étudiants</span>
          </button>
          <button
              @click="updateMode('staff')"
              type="button"
              :aria-pressed="filters.mode === 'staff'"
              :class="[
              'flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs transition-all duration-200 cursor-pointer',
              filters.mode === 'staff'
                ? 'bg-surface-0 dark:bg-surface-900 text-primary-700 shadow-xs font-bold border border-surface'
                : 'text-muted-color hover:text-color font-semibold'
            ]"
          >
            <i class="pi pi-id-card text-xs" aria-hidden="true"></i>
            <span>Personnel</span>
          </button>
        </div>
      </div>

      <!-- Search Input -->
      <div>
        <label for="trombi-recherche" class="block text-xs font-bold text-color uppercase tracking-wider mb-2">
          Recherche
        </label>
        <div class="relative">
          <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-muted-color text-xs" aria-hidden="true"></i>
          <input
              id="trombi-recherche"
              ref="searchInputRef"
              type="text"
              :value="filters.searchTerm"
              @input="updateSearch(($event.target as HTMLInputElement).value)"
              :placeholder="filters.mode === 'students' ? 'Nom, prénom, email, N°...' : 'Nom, prénom, bureau, matière...'"
              class="w-full pl-8 pr-8 py-2 text-xs bg-surface-0 dark:bg-surface-900 border border-[var(--ui-border-color)] rounded-lg text-color font-medium placeholder:text-muted-color focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 shadow-2xs transition-all"
          />
          <button
              v-if="filters.searchTerm"
              @click="updateSearch('')"
              type="button"
              aria-label="Effacer la recherche"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-color hover:text-color cursor-pointer"
          >
            <i class="pi pi-times-circle text-xs" aria-hidden="true"></i>
          </button>
        </div>
      </div>

      <!-- Student Specific Filters -->
      <div v-if="filters.mode === 'students'" class="space-y-4 pt-2 border-t border-surface">
        <!-- Semester Selection -->
        <div>
          <label for="trombi-semestre" class="block text-xs font-bold text-color mb-1.5">
            Semestre
          </label>
          <select
              id="trombi-semestre"
              :value="filters.studentFilters.semester || ''"
              @change="updateStudentSemester($event.target.value ? Number($event.target.value) : null)"
              class="w-full text-xs py-2 px-3 bg-surface-0 dark:bg-surface-900 border border-[var(--ui-border-color)] rounded-lg text-color font-medium focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 shadow-2xs transition-all cursor-pointer"
          >
            <option value="">Tous les semestres</option>
            <option v-for="sem in semesters" :key="sem.id" :value="sem.id">
              {{ sem.libelle }}
            </option>
          </select>
        </div>

        <!-- Group Type Selection -->
        <div v-if="filters.studentFilters.semester" class="space-y-2">
          <p id="trombi-type-groupe" class="block text-xs font-bold text-color mb-1">
            Type de groupe
          </p>
          <div role="group" aria-labelledby="trombi-type-groupe" class="grid grid-cols-4 gap-1 p-1 bg-emphasis rounded-lg border border-surface">
            <button
                type="button"
                @click="updateStudentGroupType(null)"
                :aria-pressed="!filters.studentFilters.groupType"
                :class="[
                'py-1.5 text-xs font-semibold rounded-md text-center transition-all cursor-pointer',
                !filters.studentFilters.groupType
                  ? 'bg-surface-0 dark:bg-surface-900 text-primary-700 shadow-xs font-bold border border-surface'
                  : 'text-color hover:text-color hover:bg-surface-200 dark:hover:bg-surface-700'
              ]"
            >
              Tous
            </button>
            <button
                v-for="gt in (['CM', 'TD', 'TP'] as const)"
                :key="gt"
                type="button"
                @click="updateStudentGroupType(gt)"
                :aria-pressed="filters.studentFilters.groupType === gt"
                :class="[
                'py-1.5 text-xs font-semibold rounded-md text-center transition-all cursor-pointer',
                filters.studentFilters.groupType === gt
                  ? 'bg-surface-0 dark:bg-surface-900 text-primary-700 shadow-xs font-bold border border-surface'
                  : 'text-color hover:text-color hover:bg-surface-200 dark:hover:bg-surface-700'
              ]"
            >
              {{ gt }}
            </button>
          </div>
        </div>

        <!-- Specific Group Selection -->
        <div v-if="filters.studentFilters.groupType">
          <label for="trombi-groupe" class="block text-xs font-bold text-color mb-1.5 flex items-center justify-between">
            <span>Groupe spécifique</span>
            <span v-if="loadingGroups" class="text-[10px] text-muted-color font-normal">Chargement...</span>
          </label>
          <select
              id="trombi-groupe"
              :value="filters.studentFilters.group || ''"
              @change="updateStudentGroup($event.target.value || null)"
              class="w-full text-xs py-2 px-3 bg-surface-0 dark:bg-surface-900 border border-[var(--ui-border-color)] rounded-lg text-color font-medium focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 shadow-2xs transition-all cursor-pointer"
          >
            <option value="">Tous les groupes {{ filters.studentFilters.groupType }}</option>
            <option v-for="grp in availableGroups" :key="grp.id" :value="grp.id">
              {{ grp.libelle }}
            </option>
          </select>
        </div>
      </div>

      <!-- Staff Specific Filters -->
      <div v-if="filters.mode === 'staff'" class="space-y-4 pt-2 border-t border-surface">
        <div>
          <label for="trombi-statut" class="block text-xs font-bold text-color mb-1.5">
            Statut
          </label>
          <select
              id="trombi-statut"
              :value="filters.staffFilters.statut || ''"
              @change="updateStaffStatut($event.target.value || null)"
              class="w-full text-xs py-2 px-3 bg-surface-0 dark:bg-surface-900 border border-[var(--ui-border-color)] rounded-lg text-color font-medium focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 shadow-2xs transition-all cursor-pointer"
          >
            <option value="">Tous les statuts</option>
            <option v-for="st in availableStaffStatuts" :key="st" :value="st">
              {{ st }}
            </option>
          </select>
        </div>
      </div>

      <!-- Display Options -->
      <div class="space-y-4 pt-2 border-t border-surface">
        <p class="block text-xs font-bold text-color uppercase tracking-wider mb-2">
          Affichage & Tri
        </p>

        <!-- Sort By + Order -->
        <div>
          <label for="trombi-tri" class="block text-xs font-bold text-color mb-1.5">
            Trier par
          </label>
          <div class="flex items-center gap-2">
            <select
                id="trombi-tri"
                :value="filters.sortBy"
                @change="updateSortBy($event.target.value)"
                class="flex-1 text-xs py-2 px-3 bg-surface-0 dark:bg-surface-900 border border-[var(--ui-border-color)] rounded-lg text-color font-medium focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 shadow-2xs transition-all cursor-pointer"
            >
              <option value="nom">Nom</option>
              <option value="prenom">Prénom</option>
              <option v-if="filters.mode === 'students'" value="semester">Semestre</option>
              <option v-if="filters.mode === 'students'" value="groupe">Groupe</option>
              <option v-if="filters.mode === 'staff'" value="statut">Statut</option>
            </select>

            <button
                @click="toggleSortOrder"
                type="button"
                :title="filters.sortOrder === 'asc' ? 'Ordre croissant (A-Z)' : 'Ordre décroissant (Z-A)'"
                :aria-label="filters.sortOrder === 'asc' ? 'Ordre croissant (A-Z)' : 'Ordre décroissant (Z-A)'"
                class="p-2 bg-emphasis hover:bg-surface-200 dark:hover:bg-surface-700 border border-[var(--ui-border-color)] text-color rounded-lg transition-colors flex items-center justify-center cursor-pointer shadow-2xs"
            >
              <i :class="[filters.sortOrder === 'asc' ? 'pi pi-sort-amount-down-alt' : 'pi pi-sort-amount-up', 'text-xs']" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <!-- Grid vs List Mode -->
        <div>
          <p id="trombi-format" class="block text-xs font-bold text-color mb-1.5">
            Format de vue
          </p>
          <div role="group" aria-labelledby="trombi-format" class="grid grid-cols-2 p-1 bg-emphasis rounded-lg border border-surface">
            <button
                @click="updateViewMode('grid')"
                type="button"
                :aria-pressed="filters.viewMode === 'grid'"
                :class="[
                'flex items-center justify-center gap-1.5 py-1.5 text-xs font-semibold rounded-md transition-all cursor-pointer',
                filters.viewMode === 'grid'
                  ? 'bg-surface-0 dark:bg-surface-900 text-primary-700 shadow-xs font-bold border border-surface'
                  : 'text-color hover:text-color hover:bg-surface-200 dark:hover:bg-surface-700'
              ]"
            >
              <i class="pi pi-th-large text-xs" aria-hidden="true"></i>
              <span>Grille</span>
            </button>
            <button
                @click="updateViewMode('list')"
                type="button"
                :aria-pressed="filters.viewMode === 'list'"
                :class="[
                'flex items-center justify-center gap-1.5 py-1.5 text-xs font-semibold rounded-md transition-all cursor-pointer',
                filters.viewMode === 'list'
                  ? 'bg-surface-0 dark:bg-surface-900 text-primary-700 shadow-xs font-bold border border-surface'
                  : 'text-color hover:text-color hover:bg-surface-200 dark:hover:bg-surface-700'
              ]"
            >
              <i class="pi pi-bars text-xs" aria-hidden="true"></i>
              <span>Liste</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Exports Section -->
      <div class="space-y-2.5 pt-2 border-t border-surface">
        <p class="block text-xs font-bold text-color uppercase tracking-wider mb-1">
          Exports
        </p>

        <!-- Gotenberg PDF Trombinoscope export (if semester selected) -->
        <Button
            v-if="filters.mode === 'students' && filters.studentFilters.semester"
            type="button"
            label="Trombinoscope PDF"
            icon="pi pi-file-pdf"
            severity="secondary"
            outlined
            size="small"
            class="w-full"
            @click="emit('exportTrombiPdf')"
        />

        <!-- Listing Excel export (if semester selected) -->
        <Button
            v-if="filters.mode === 'students' && filters.studentFilters.semester"
            type="button"
            label="Listing d'émargement (Excel)"
            icon="pi pi-file-excel"
            severity="secondary"
            outlined
            size="small"
            class="w-full"
            @click="emit('exportListingExcel')"
        />

        <!-- CSV Export (Current view) -->
        <button
            type="button"
            @click="emit('exportData')"
            class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-xl text-xs font-semibold bg-emphasis text-color border border-[var(--ui-border-color)] hover:bg-surface-200 dark:hover:bg-surface-700 shadow-2xs transition-all duration-200 cursor-pointer"
        >
          <i class="pi pi-download text-xs text-muted-color" aria-hidden="true"></i>
          <span>Exporter la vue (CSV)</span>
        </button>
      </div>
    </div>
  </aside>
</template>
