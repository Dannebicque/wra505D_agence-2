<template>
  <div
    class="card relative p-3 hover:shadow-md border border-gray-200 hover:border-primary-300 transition-all duration-200 group cursor-pointer bg-white rounded-lg"
  >
    <div class="flex items-center space-x-4">
      <div :class="['p-2 rounded-lg bg-gray-50 flex items-center justify-center', getFileIconColor(document.type)]">
        <i :class="[getFileIcon(document.type), 'text-xl']" aria-hidden="true"></i>
      </div>

      <div class="flex-1 min-w-0">
        <div class="flex items-center justify-between">
          <h3 class="text-base! leading-snug! m-0! font-medium text-gray-900 line-clamp-2 break-words group-hover:text-primary-600 transition-colors">
            <button type="button" class="ouvrir-document text-left" @click="$emit('selectDocument', document)">
              {{ document.title }}
            </button>
          </h3>
          <div class="flex items-center space-x-2">
            <button
              @click.stop="$emit('downloadDocument', document)"
              class="action-document relative z-10 min-w-[44px] min-h-[44px] inline-flex items-center justify-center opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-opacity text-gray-600 hover:text-primary-600 hover:bg-gray-100 rounded"
              title="Télécharger"
              :aria-label="`Télécharger ${document.title}`"
            >
              <i class="pi pi-download" aria-hidden="true"></i>
            </button>
            <button
              v-permission="'isPersonnel'"
              @click.stop="$emit('deleteDocument', document)"
              class="action-document relative z-10 min-w-[44px] min-h-[44px] inline-flex items-center justify-center opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-opacity text-gray-600 hover:text-red-600 hover:bg-gray-100 rounded"
              title="Supprimer"
              :aria-label="`Supprimer ${document.title}`"
            >
              <i class="pi pi-trash" aria-hidden="true"></i>
            </button>
            <button
              @click.stop="$emit('toggleFavorite', document.id)"
              class="action-document relative z-10 min-w-[44px] min-h-[44px] inline-flex items-center justify-center hover:bg-gray-100 rounded transition-colors"
              :title="document.isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris'"
              :aria-label="document.isFavorite ? `Retirer ${document.title} des favoris` : `Ajouter ${document.title} aux favoris`"
            >
              <i
                :class="document.isFavorite ? 'pi pi-star-fill text-amber-700' : 'pi pi-star text-gray-600 group-hover:text-gray-700'"
                aria-hidden="true"
              ></i>
            </button>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600 mt-1">
          <span class="font-semibold uppercase text-gray-700">{{ getFileExtension(document.type) }}</span>
          <span>{{ formatFileSize(document.size) }}</span>
          <span><i class="pi pi-user text-xs me-1" aria-hidden="true"></i>{{ document.author }}</span>
          <span><i class="pi pi-calendar text-xs me-1" aria-hidden="true"></i>{{ formatDate(document.lastModified) }}</span>
          <span class="bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded font-mono">{{ document.version }}</span>
        </div>
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

<style scoped>
/* Même principe que la carte : le titre ouvre la fiche, et sa zone couvre toute la ligne. */
.ouvrir-document::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: 0.5rem;
}

.ouvrir-document:focus-visible {
  outline: none;
}

.ouvrir-document:focus-visible::after,
.action-document:focus-visible {
  outline: 2px solid var(--p-primary-500);
  outline-offset: 2px;
}
</style>
