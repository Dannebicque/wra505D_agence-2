<script setup>
import { EmptyState } from '@components';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import { validateStageAvenantService } from '@/requests/stage_service';
import { computed, ref } from 'vue';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
  periodStudents: {
    type: Array,
    required: true
  },
  activePeriodName: {
    type: String,
    default: ''
  }
});

const emit = defineEmits(['reload']);

const toast = useToast();
const isValidating = ref({});

// Flatten and extract all avenants from students of this period
const allAvenants = computed(() => {
  const list = [];
  props.periodStudents.forEach(student => {
    if (student.avenants && student.avenants.length > 0) {
      student.avenants.forEach(av => {
        list.push({
          ...av,
          studentName: student.studentName,
          company: student.company
        });
      });
    }
  });
  // Sort by date creation descending
  return list.sort((a, b) => new Date(b.dateCreation) - new Date(a.dateCreation));
});

const validateAvenant = async (av) => {
  isValidating.value[av.id] = true;
  try {
    await validateStageAvenantService(av.id);
    toast.add({
      severity: 'success',
      summary: 'Avenant validé',
      detail: `L'avenant pour ${av.studentName} a été validé avec succès.`,
      life: 3000
    });
    // Trigger parent reload
    emit('reload');
  } catch (error) {
    console.error('Erreur lors de la validation de l\'avenant:', error);
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de valider l\'avenant.',
      life: 3000
    });
  } finally {
    isValidating.value[av.id] = false;
  }
};
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between border-b border-surface  pb-5">
      <div>
        <h2 class="text-lg font-bold text-color -contrast">Gestion des Avenants</h2>
        <p class="text-xs text-muted-color">
          Validez les demandes d'avenants soumises par les étudiants pour la période <strong class="text-primary ">{{ activePeriodName }}</strong>.
        </p>
      </div>
    </div>

    <!-- Table of Avenants -->
    <div class="bg-surface-0  rounded-3xl border border-surface  shadow-sm overflow-hidden">
      <div v-if="allAvenants.length > 0" class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-emphasis  text-muted-color uppercase font-black tracking-wider border-b border-surface ">
              <th class="p-4">Étudiant &amp; Entreprise</th>
              <th class="p-4">Objet</th>
              <th class="p-4">Type</th>
              <th class="p-4">Détails de la modification</th>
              <th class="p-4">Date de demande</th>
              <th class="p-4 text-center">Statut</th>
              <th class="p-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-surface ">
            <tr v-for="av in allAvenants" :key="av.id" class="hover:bg-emphasis dark:hover:bg-emphasis transition-colors">
              <td class="p-4 font-bold text-color ">
                <div>{{ av.studentName }}</div>
                <div class="text-[10px] text-muted-color font-medium mt-0.5">{{ av.company }}</div>
              </td>
              <td class="p-4 font-semibold text-color ">
                {{ av.libelle }}
              </td>
              <td class="p-4">
                <span class="px-2 py-0.5 rounded font-mono font-bold text-[9px] bg-emphasis  text-muted-color">
                  {{ av.typeModification }}
                </span>
              </td>
              <td class="p-4 text-muted-color  max-w-[250px] truncate" :title="av.texte">
                {{ av.texte }}
              </td>
              <td class="p-4 text-muted-color font-semibold">
                {{ new Date(av.dateCreation).toLocaleDateString('fr-FR') }}
              </td>
              <td class="p-4 text-center">
                <Tag :value="av.valide ? 'Validé' : 'En attente'" :severity="av.valide ? 'success' : 'warn'" />
              </td>
              <td class="p-4 text-right">
                <div class="flex justify-end gap-2">
                  <Button v-if="!av.valide" label="Valider" icon="pi pi-check" severity="success" size="small"
                    :loading="isValidating[av.id]" @click="validateAvenant(av)" />
                  <a 
                    v-if="av.valide"
                    :href="'/api/stage_avenants/' + av.id + '/pdf'"
                    target="_blank"
                    class="px-3 py-1.5 bg-primary hover:bg-primary text-primary-contrast font-bold rounded-lg shadow-sm flex items-center gap-1 transition-all decoration-none"
                  >
                    <i class="pi pi-download"></i>
                    <span>Télécharger PDF</span>
                  </a>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <EmptyState v-else icon="pi pi-file" title="Aucun avenant" description="Aucune demande d'avenant n'a été formulée par les étudiants pour cette période." />
    </div>
  </div>
</template>
