<template>
  <div
    @click="$emit('selectDocument', document)"
    class="card p-3 md:p-4 hover:shadow-lg border border-gray-200 hover:border-primary-300 transition-all duration-200 group cursor-pointer flex flex-col justify-between relative bg-white rounded-xl"
  >
    <div>
      <!-- Header -->
      <!-- Le titre vient en premier dans le code, pour être lu avant le reste, mais s'affiche sous
           l'icône : à côté d'elle et de l'étoile, il n'avait que 90 px. -->
      <div class="flex flex-col gap-2 md:mb-3">
        <h3 class="order-2 text-base! leading-snug! m-0! font-semibold text-gray-900 line-clamp-2 break-words group-hover:text-primary-600 transition-colors">
          {{ document.title }}
        </h3>
        <div class="order-1 flex items-center justify-between gap-2">
          <div class="flex flex-1 items-center gap-2 min-w-0">
            <div :class="['p-2 rounded-lg bg-gray-50 flex items-center justify-center shrink-0', getFileIconColor(document.type)]">
              <i :class="[getFileIcon(document.type), 'text-xl']" aria-hidden="true"></i>
            </div>
            <p class="text-xs text-gray-600 whitespace-nowrap m-0">
              {{ getFileExtension(document.type) }} • {{ formatFileSize(document.size) }}
            </p>
          </div>

          <button
            @click.stop="$emit('toggleFavorite', document.id)"
            class="min-w-[44px] min-h-[44px] inline-flex items-center justify-center hover:bg-gray-100 rounded transition-colors"
            :title="document.isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris'"
            :aria-label="document.isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris'"
          >
            <i
              :class="[document.isFavorite ? 'pi pi-star-fill text-amber-700' : 'pi pi-star text-gray-600 group-hover:text-gray-700', 'text-lg']"
              aria-hidden="true"
            ></i>
          </button>
        </div>
      </div>

      <!-- Info badges -->
      <div class="flex flex-wrap gap-x-3 gap-y-1 md:block md:space-y-1.5 text-xs text-gray-600 my-2">
        <div class="flex items-center">
          <i class="pi pi-user w-4 me-1 opacity-70" aria-hidden="true"></i>
          <span class="truncate">{{ document.author }}</span>
        </div>

        <div class="flex items-center">
          <i class="pi pi-calendar w-4 me-1 opacity-70" aria-hidden="true"></i>
          <span>{{ formatDate(document.lastModified) }}</span>
        </div>
      </div>

      <div v-if="document.description" class="max-md:hidden mt-2 text-xs text-gray-600 line-clamp-2">
        {{ document.description }}
      </div>

      <!-- Tags -->
      <div v-if="document.tags.length > 0" class="max-md:hidden mt-3 flex flex-wrap gap-1">
        <span
          v-for="tag in document.tags.slice(0, 3)"
          :key="tag"
          class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600"
        >
          #{{ tag }}
        </span>
        <span
          v-if="document.tags.length > 3"
          class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600 font-medium"
        >
          +{{ document.tags.length - 3 }}
        </span>
      </div>
    </div>

    <!-- Quick Footer Actions -->
    <div class="max-md:hidden mt-4 pt-3 border-t border-gray-100 flex items-center justify-between opacity-0 group-hover:opacity-100 transition-opacity">
      <span class="text-xs font-medium text-primary-600 hover:underline">
        Voir détails <i class="pi pi-arrow-right text-xs" aria-hidden="true"></i>
      </span>
      <div class="flex items-center space-x-1">
        <button
          @click.stop="$emit('downloadDocument', document)"
          class="p-1.5 text-gray-600 hover:text-primary-600 hover:bg-primary-50 rounded transition-colors"
          title="Télécharger"
          aria-label="Télécharger"
        >
          <i class="pi pi-download" aria-hidden="true"></i>
        </button>
        <button
          v-permission="'isPersonnel'"
          @click.stop="$emit('deleteDocument', document)"
          class="p-1.5 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
          title="Supprimer"
          aria-label="Supprimer"
        >
          <i class="pi pi-trash" aria-hidden="true"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Document } from '@types';
import { getFileIcon, getFileIconColor, formatFileSize, formatDate, getFileExtension } from '@/service/utils/fileUtils';

interface Props {
  document: Document;
}

defineProps<Props>();

defineEmits<{
  selectDocument: [document: Document];
  downloadDocument: [document: Document];
  deleteDocument: [document: Document];
  toggleFavorite: [documentId: string];
}>();
</script>
