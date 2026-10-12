<template>
  <Sidebar
    :visible="visible"
    position="right"
    class="w-full! md:w-(--largeur-panneau)! p-sidebar-md"
    :style="{ '--largeur-panneau': `min(${largeur}px, 100vw - 320px)` }"
    @update:visible="$emit('update:visible', $event)"
  >
    <template #header>
      <!-- Placée dans l'en-tête pour ne pas défiler avec le contenu ; positionnée sur le bord
           gauche du panneau, qui sert de référence à cause de sa transformation. -->
      <div
        role="separator"
        aria-orientation="vertical"
        aria-label="Largeur du panneau"
        :aria-valuenow="largeur"
        :aria-valuemin="LARGEUR_PANNEAU_MIN"
        :aria-valuemax="largeurMax(largeurFenetre())"
        tabindex="0"
        class="group absolute inset-y-0 left-0 z-10 hidden md:flex w-[44px] -translate-x-1/2 cursor-col-resize touch-none select-none items-center justify-center focus-visible:outline-none"
        @pointerdown="debuterRedimensionnement"
        @keydown="redimensionnerAuClavier"
      >
        <span
          aria-hidden="true"
          class="h-12 w-1.5 rounded-full bg-surface-500 group-hover:bg-primary group-focus-visible:bg-primary group-focus-visible:outline-2 group-focus-visible:outline-offset-4 group-focus-visible:outline-primary"
        ></span>
      </div>

      <div class="flex items-center space-x-3">
        <i :class="[getFileIcon(document?.type || 'pdf'), 'text-2xl']" aria-hidden="true"></i>
        <div class="min-w-0">
          <h3 class="text-lg! leading-snug! m-0! font-bold text-gray-900 break-words">{{ document?.title }}</h3>
          <p class="text-xs text-gray-600">{{ getFileExtension(document?.type || 'pdf') }}</p>
        </div>
      </div>
    </template>

    <div v-if="document" class="space-y-6 pt-4">
      <!-- File Preview Box -->
      <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
        <div :class="['mb-2', getFileIconColor(document.type)]">
          <i :class="[getFileIcon(document.type), 'text-5xl']" aria-hidden="true"></i>
        </div>
        <p class="font-semibold text-gray-800 text-sm break-words">{{ document.title }}</p>
        <p class="text-xs text-gray-600 mt-1">{{ formatFileSize(document.size) }}</p>
      </div>

      <!-- Quick Actions -->
      <div class="flex space-x-2">
        <Button
          v-if="typeApercu(document.type)"
          label="Aperçu"
          icon="pi pi-eye"
          outlined
          class="flex-1 min-h-[44px] border-primary!"
          @click="$emit('preview', document)"
        />

        <button
          @click="$emit('download', document)"
          class="flex-1 min-h-[44px] bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-3 rounded-lg text-sm transition-colors flex items-center justify-center space-x-2 shadow-sm"
        >
          <i class="pi pi-download" aria-hidden="true"></i>
          <span>Télécharger</span>
        </button>

        <button
          @click="$emit('toggleFavorite', document.id)"
          :class="[
            'min-w-[44px] min-h-[44px] px-3 py-2 border rounded-lg text-sm transition-colors flex items-center justify-center',
            document.isFavorite
              ? 'bg-yellow-50 text-yellow-700 border-yellow-300 hover:bg-yellow-100'
              : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
          ]"
          :title="document.isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris'"
          :aria-label="document.isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris'"
        >
          <i :class="document.isFavorite ? 'pi pi-star-fill' : 'pi pi-star'" aria-hidden="true"></i>
        </button>

        <ButtonDelete
          v-permission="'isPersonnel'"
          tooltip="Supprimer le document"
          @confirm="$emit('delete', document)"
        />
      </div>

      <!-- Metadata List -->
      <div class="space-y-3 pt-2 text-sm border-t border-gray-100">
        <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Informations</h4>

        <div class="flex justify-between py-1 border-b border-gray-50">
          <span class="text-gray-600">Auteur</span>
          <span class="font-medium text-gray-800">{{ document.author }}</span>
        </div>

        <div class="flex justify-between py-1 border-b border-gray-50">
          <span class="text-gray-600">Dernière modification</span>
          <span class="font-medium text-gray-800">{{ formatDate(document.lastModified) }}</span>
        </div>

        <div class="flex justify-between py-1 border-b border-gray-50">
          <span class="text-gray-600">Version</span>
          <span class="font-medium text-gray-800">{{ document.version }}</span>
        </div>

        <div class="flex justify-between py-1 border-b border-gray-50">
          <span class="text-gray-600">Taille</span>
          <span class="font-medium text-gray-800">{{ formatFileSize(document.size) }}</span>
        </div>

        <div v-if="document.enseignement" class="flex justify-between gap-4 py-1 border-b border-gray-50">
          <span class="text-gray-600">{{ document.enseignement.type === 'sae' ? 'SAÉ' : 'Matière' }}</span>
          <span class="font-medium text-gray-800 text-right">{{ libelleEnseignement(document.enseignement) }}</span>
        </div>

        <div v-if="categoryName" class="flex justify-between py-1 border-b border-gray-50">
          <span class="text-gray-600">Catégorie</span>
          <span class="font-medium text-primary-700 bg-primary-50 px-2 py-0.5 rounded text-xs">{{ categoryName }}</span>
        </div>
      </div>

      <!-- Description -->
      <div v-if="document.description" class="pt-2 border-t border-gray-100">
        <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Description</h4>
        <p class="text-sm text-gray-700 bg-gray-50 p-3 rounded-lg border border-gray-200">
          {{ document.description }}
        </p>
      </div>

      <!-- Tags -->
      <div v-if="document.tags.length > 0" class="pt-2 border-t border-gray-100">
        <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Étiquettes</h4>
        <div class="flex flex-wrap gap-1.5">
          <span
            v-for="tag in document.tags"
            :key="tag"
            class="px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200"
          >
            #{{ tag }}
          </span>
        </div>
      </div>
    </div>
  </Sidebar>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import Sidebar from 'primevue/sidebar';
import Button from 'primevue/button';
import type { Document } from '@types';
import { ButtonDelete } from '@components';
import { getFileIcon, getFileIconColor, formatFileSize, formatDate, getFileExtension } from '@/service/utils/fileUtils';
import { libelleEnseignement } from '@/service/utils/enseignementUtils';
import { typeApercu } from '@/service/utils/apercuUtils';
import {
  LARGEUR_PANNEAU_DEFAUT,
  LARGEUR_PANNEAU_MIN,
  bornerLargeur,
  largeurAuClavier,
  largeurMax,
} from '@/service/utils/largeurPanneau';

interface Props {
  visible: boolean;
  document: Document | null;
  categoryName?: string;
}

defineProps<Props>();

defineEmits<{
  'update:visible': [value: boolean];
  download: [document: Document];
  preview: [document: Document];
  delete: [document: Document];
  toggleFavorite: [documentId: string];
}>();

const largeur = ref(LARGEUR_PANNEAU_DEFAUT);
const largeurFenetre = () => window.innerWidth;

const debuterRedimensionnement = (evenement: PointerEvent) => {
  const poignee = evenement.currentTarget as HTMLElement;
  const departX = evenement.clientX;
  const departLargeur = largeur.value;
  // La capture garde le glisser actif quand le pointeur quitte la poignée, fine sous le doigt.
  poignee.setPointerCapture(evenement.pointerId);
  evenement.preventDefault();

  const suivre = (deplacement: PointerEvent) => {
    largeur.value = bornerLargeur(departLargeur + departX - deplacement.clientX, largeurFenetre());
  };
  const terminer = () => {
    poignee.removeEventListener('pointermove', suivre);
    poignee.removeEventListener('pointerup', terminer);
    poignee.removeEventListener('pointercancel', terminer);
  };
  poignee.addEventListener('pointermove', suivre);
  poignee.addEventListener('pointerup', terminer);
  poignee.addEventListener('pointercancel', terminer);
};

const redimensionnerAuClavier = (evenement: KeyboardEvent) => {
  const nouvelle = largeurAuClavier(evenement.key, largeur.value, largeurFenetre());
  if (nouvelle === null) return;
  evenement.preventDefault();
  largeur.value = nouvelle;
};
</script>
