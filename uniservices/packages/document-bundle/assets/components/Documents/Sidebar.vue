<template>
  <div class="h-full bg-white border-r border-gray-200 flex flex-col w-64">
    <!-- Action Header -->
    <div class="p-4 border-b border-gray-200 space-y-3">
      <button
        v-permission="'isPersonnel'"
        @click="$emit('openUploadModal')"
        class="w-full bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-4 rounded-lg shadow-sm hover:shadow transition-all flex items-center justify-center space-x-2 text-sm"
      >
        <span class="text-lg">+</span>
        <span>Nouveau document</span>
      </button>

      <SearchBar
          v-model="searchQuery"
          @search="handleSearch"
      />
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-4 py-4 space-y-2 overflow-y-auto">
      <!-- All Documents -->
      <button
        @click="$emit('selectAll')"
        :class="[
          'w-full flex items-center space-x-3 px-3 py-2 text-sm font-medium rounded-md transition-colors',
          selectedCategory === null && selectedEnseignement === null && !showFavorites
            ? 'bg-primary-50 text-primary-700 border border-primary-200'
            : 'text-gray-700 hover:bg-gray-50'
        ]"
      >
        <i class="pi pi-folder text-lg" aria-hidden="true"></i>
        <span>Tous les documents</span>
        <span class="ml-auto text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full">
          {{ totalDocuments }}
        </span>
      </button>

      <!-- Favorites -->
      <button
        @click="$emit('selectFavorites')"
        :class="[
          'w-full flex items-center space-x-3 px-3 py-2 text-sm font-medium rounded-md transition-colors',
          showFavorites
            ? 'bg-yellow-50 text-yellow-700 border border-yellow-200'
            : 'text-gray-700 hover:bg-gray-50'
        ]"
      >
        <i class="pi pi-star text-lg" aria-hidden="true"></i>
        <span>Favoris</span>
        <span class="ml-auto text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full">
          {{ favoriteCount }}
        </span>
      </button>

      <div
        v-for="section in sectionsEnseignements"
        :key="section.titre"
        class="mt-6"
      >
        <h3 class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">
          {{ section.titre }}
        </h3>
        <ul class="space-y-1">
          <li v-for="groupe in section.groupes" :key="groupe.enseignement.id">
            <button
              type="button"
              :aria-current="selectedEnseignement === groupe.enseignement.id ? 'true' : undefined"
              @click="$emit('selectEnseignement', groupe.enseignement.id)"
              :class="[
                'w-full min-h-[44px] flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md text-left transition-colors',
                selectedEnseignement === groupe.enseignement.id
                  ? 'bg-primary-50 text-primary-700 border border-primary-600'
                  : 'text-gray-700 hover:bg-gray-50'
              ]"
            >
              <span class="flex-1">
                <span class="font-semibold">{{ groupe.enseignement.code }}</span>
                {{ groupe.enseignement.libelle }}
              </span>
              <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full" aria-hidden="true">
                {{ groupe.documentCount }}
              </span>
              <span class="sr-only">{{ groupe.documentCount }} {{ groupe.documentCount > 1 ? 'documents' : 'document' }}</span>
            </button>
          </li>
        </ul>
      </div>

      <!-- Categories -->
      <div class="mt-6">
        <h3 class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">
          Catégories
        </h3>
        <div class="space-y-1">
          <CategoryItem
            v-for="category in categories"
            :key="category.id"
            :category="category"
            :selected-category="selectedCategory"
          />
        </div>
      </div>
    </nav>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import CategoryItem from './CategoryItem.vue';
import SearchBar from './SearchBar.vue';
import type { Category } from '@types';
import type { ClassementEnseignements } from '@/service/utils/enseignementUtils';

interface Props {
  categories: Category[];
  enseignements: ClassementEnseignements;
  selectedCategory: string | null;
  selectedEnseignement: string | null;
  showFavorites: boolean;
  totalDocuments: number;
  favoriteCount: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  selectAll: [];
  selectEnseignement: [enseignementId: string];
  selectFavorites: [];
  search: [query: string];
  openUploadModal: [];
}>();

const searchQuery = ref('');

const sectionsEnseignements = computed(() =>
  [
    { titre: 'Matières', groupes: props.enseignements.matieres },
    { titre: 'SAÉ', groupes: props.enseignements.saes }
  ].filter(section => section.groupes.length > 0)
);

const handleSearch = (query: string) => {
  emit('search', query);
};
</script>
