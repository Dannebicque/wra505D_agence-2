<template>
  <div>
    <div
      :class="[
        'w-full flex items-center space-x-2 pr-3 text-sm font-medium rounded-md transition-colors',
        selectedCategory === category.id
          ? 'bg-primary-50 text-primary-700 border border-primary-200'
          : 'text-gray-700 hover:bg-gray-50'
      ]"
    >
      <button
        v-if="category.children && category.children.length > 0"
        type="button"
        :aria-label="`Sous-catégories de ${category.name}`"
        :aria-expanded="expanded"
        @click="toggleExpanded"
        class="min-w-[44px] min-h-[44px] flex items-center justify-center hover:bg-gray-100 rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
      >
        <svg
          :class="[
            'w-3 h-3 transition-transform',
            expanded ? 'rotate-90' : ''
          ]"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M9 5l7 7-7 7"
          />
        </svg>
      </button>
      <div v-else class="w-[44px] shrink-0"></div>

      <RouterLink
        :to="{ query: { categorie: category.id } }"
        :aria-current="selectedCategory === category.id ? 'page' : undefined"
        class="flex items-center space-x-2 flex-1 min-h-[44px] rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
      >
        <i :class="[category.icon, 'text-lg']" aria-hidden="true"></i>
        <span class="flex-1 text-left">{{ category.name }}</span>

        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full" aria-hidden="true">
          {{ category.documentCount }}
        </span>
        <span class="sr-only">{{ category.documentCount }} {{ category.documentCount > 1 ? 'documents' : 'document' }}</span>
      </RouterLink>
    </div>

    <div
      v-if="expanded && category.children"
      class="ml-4 mt-1 space-y-1"
    >
      <CategoryItem
        v-for="child in category.children"
        :key="child.id"
        :category="child"
        :selected-category="selectedCategory"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import type { Category } from '@types';
import { contientCategorie } from '@/service/utils/categorieUtils';

interface Props {
  category: Category;
  selectedCategory: string | null;
}

const props = defineProps<Props>();

const contientLaSelection = () => contientCategorie(props.category.children ?? [], props.selectedCategory);

const expanded = ref(contientLaSelection());

watch(() => props.selectedCategory, () => {
  if (contientLaSelection()) {
    expanded.value = true;
  }
});

const toggleExpanded = () => {
  expanded.value = !expanded.value;
};
</script>
