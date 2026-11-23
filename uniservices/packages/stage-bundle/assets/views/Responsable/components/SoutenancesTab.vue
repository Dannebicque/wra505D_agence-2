<script setup>
import Button from 'primevue/button';
import { getStagePeriodeService, updateStagePeriodeService, getStageSoutenancesService, saveStageSoutenanceService, checkConflitStageSoutenanceService, generateStageSoutenancesService, deleteStageSoutenanceService } from '@/requests/stage_service';
import { useConfirmation } from '@composables/useConfirmation';
import { idDepuisRelation } from '@/utils/stage';
import { computed, ref, onMounted, watch } from 'vue';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
  periodStudents: {
    type: Array,
    required: true
  },
  teachers: {
    type: Array,
    required: true
  },
  selectedPeriodId: {
    type: [Number, String],
    required: true
  },
  activePeriodName: {
    type: String,
    default: ''
  }
});

const toast = useToast();
const { confirmer } = useConfirmation();
const slots = ref([]);
const isLoading = ref(false);

// Configuration state
const defaultDuration = ref(30);
const isUpdatingDuration = ref(false);

// Bulk Generator state
const bulkDate = ref('');
const bulkStartTime = ref('09:00');
const bulkEndTime = ref('17:00');
const bulkDuree = ref(30);
const bulkPause = ref(15);
const bulkSalle = ref('');
const isGeneratingBulk = ref(false);

// Conflict tracking per slot ID
const slotConflicts = ref({});

// Defense sessions list
const sessions = ref([]);

// Watch period changes
watch(() => props.selectedPeriodId, async (newVal) => {
  if (newVal) {
    // Set default bulk date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    bulkDate.value = tomorrow.toISOString().split('T')[0];

    await fetchPeriodDetails();
    await fetchSlots();
  }
}, { immediate: true });

async function fetchPeriodDetails() {
  try {
    const periode = await getStagePeriodeService(props.selectedPeriodId);
    if (periode) {
      defaultDuration.value = periode.dureeSoutenance ?? 30;
      bulkDuree.value = defaultDuration.value;
      sessions.value = periode.periodesSoutenance || [];
    }
  } catch (error) {
    console.error('Erreur lors de la récupération de la période:', error);
  }
}

async function updatePeriodDuration() {
  isUpdatingDuration.value = true;
  try {
    await updateStagePeriodeService(props.selectedPeriodId, { dureeSoutenance: defaultDuration.value });
    toast.add({
      severity: 'success',
      summary: 'Configuration enregistrée',
      detail: `La durée par défaut a été configurée à ${defaultDuration.value} minutes.`,
      life: 3000
    });
  } catch (error) {
    console.error('Erreur lors de la mise à jour de la durée:', error);
  } finally {
    isUpdatingDuration.value = false;
  }
}

// Une relation arrive en objet ({ id } / { '@id' }) ou en IRI (« /api/x/12 ») selon le format de réponse.
const idFromRelation = idDepuisRelation;

async function fetchSlots() {
  isLoading.value = true;
  try {
    const rawList = await getStageSoutenancesService(props.selectedPeriodId);
    const list = rawList.filter(s => String(idFromRelation(s.stagePeriode)) === String(props.selectedPeriodId));

    // Sort chronologically
    list.sort((a, b) => new Date(a.dateSoutenance) - new Date(b.dateSoutenance));

    // Map to local edit state
    slots.value = list.map(s => {
      const stageEtudiantId = idFromRelation(s.stageEtudiant);
      const enseignantJuryId = idFromRelation(s.enseignantJury);

      return {
        id: s.id,
        dateSoutenance: s.dateSoutenance,
        duree: s.duree || defaultDuration.value,
        salle: s.salle || '',
        stageEtudiantId: stageEtudiantId,
        enseignantJuryId: enseignantJuryId,
        isEdited: false,
        isSaving: false
      };
    });

    // Run conflict checks on load
    for (const s of slots.value) {
      if (s.stageEtudiantId || s.enseignantJuryId) {
        checkConflictForSlot(s);
      }
    }
  } catch (error) {
    console.error('Erreur lors de la récupération des créneaux:', error);
  } finally {
    isLoading.value = false;
  }
}

// Generate slots in bulk
async function generateBulkSlots() {
  if (!bulkDate.value || !bulkStartTime.value || !bulkEndTime.value) {
    toast.add({
      severity: 'warn',
      summary: 'Champs requis',
      detail: 'Veuillez renseigner la date, l\'heure de début et l\'heure de fin.',
      life: 3000
    });
    return;
  }

  isGeneratingBulk.value = true;
  try {
    const resultat = await generateStageSoutenancesService({
      stagePeriodeId: props.selectedPeriodId,
      date: bulkDate.value,
      startTime: bulkStartTime.value,
      endTime: bulkEndTime.value,
      duree: bulkDuree.value,
      pauseDuree: bulkPause.value,
      salle: bulkSalle.value
    });

    toast.add({
      severity: 'success',
      summary: 'Grille générée',
      detail: `${resultat.slotsCreated} créneaux ont été ajoutés au planning.`,
      life: 3000
    });

    await fetchSlots();
  } catch (error) {
    console.error('Erreur de génération en lot:', error);
    const errorMsg = error.response?.data?.error || 'Impossible de générer la grille de créneaux.';
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: errorMsg,
      life: 4000
    });
  } finally {
    isGeneratingBulk.value = false;
  }
}

// Students who have valid stage requests
const eligibleStudents = computed(() => {
  if (!props.periodStudents) return [];
  return props.periodStudents.filter(s => s.hasStage);
});

// Students who don't have a defense planned yet
const unscheduledStudents = computed(() => {
  const studentsList = eligibleStudents.value || [];
  const slotsList = slots.value || [];
  return studentsList.filter(s => {
    return !slotsList.some(sout => sout.stageEtudiantId === s.id);
  });
});

// Helper to get student details by mapped id
const getStudentDetails = (id) => {
  if (!id) return null;
  return eligibleStudents.value.find(s => s.id === id) || null;
};

// Available students for selection (excludes already assigned to other slots, but keeps the currently assigned one for the active slot)
const getAvailableStudentsForSlot = (activeSlot) => {
  return eligibleStudents.value.filter(s => {
    // If the student is assigned to this slot, they are available
    if (activeSlot.stageEtudiantId === s.id) return true;
    // If they are assigned to any other slot, they are unavailable
    return !slots.value.some(sout => sout.id !== activeSlot.id && sout.stageEtudiantId === s.id);
  });
};

// Check conflict for a specific slot on change
async function checkConflictForSlot(slot) {
  if (!slot.dateSoutenance) return;
  try {
    const verification = await checkConflitStageSoutenanceService({
      stageEtudiantId: slot.stageEtudiantId,
      enseignantJuryId: slot.enseignantJuryId,
      dateSoutenance: slot.dateSoutenance,
      duree: slot.duree,
      soutenanceId: slot.id,
      stagePeriodeId: props.selectedPeriodId
    });

    slotConflicts.value[slot.id] = verification.messages || [];
  } catch (error) {
    console.error('Erreur lors de la vérification de conflit:', error);
  }
}

// Handle field change in slot
const onSlotFieldChange = (slot) => {
  slot.isEdited = true;
  checkConflictForSlot(slot);
};

// Save a slot
async function saveSlot(slot) {
  slot.isSaving = true;
  try {
    await saveStageSoutenanceService({
      id: slot.id,
      stagePeriodeId: props.selectedPeriodId,
      stageEtudiantId: slot.stageEtudiantId,
      enseignantJuryId: slot.enseignantJuryId,
      dateSoutenance: slot.dateSoutenance,
      salle: slot.salle,
      duree: slot.duree
    });

    toast.add({
      severity: 'success',
      summary: 'Créneau mis à jour',
      detail: 'Les affectations ont été enregistrées avec succès.',
      life: 2000
    });

    slot.isEdited = false;
    await fetchSlots();
  } catch (error) {
    console.error('Erreur lors de la sauvegarde du créneau:', error);
    const errorMsg = error.response?.data?.error || 'Erreur lors de l\'enregistrement.';
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: errorMsg,
      life: 4000
    });
  } finally {
    slot.isSaving = false;
  }
}

// Clear slot assignment (unassign student & assesseur)
async function clearSlot(slot) {
  slot.stageEtudiantId = null;
  slot.enseignantJuryId = null;
  slot.isEdited = true;
  slotConflicts.value[slot.id] = [];
  await saveSlot(slot);
}

// Delete slot completely
async function deleteSlot(id) {
  if (!await confirmer({ message: 'Supprimer définitivement ce créneau de la grille ?', titre: 'Supprimer le créneau', libelle: 'Supprimer', danger: true })) {
    return;
  }
  try {
    await deleteStageSoutenanceService(id);
    toast.add({
      severity: 'info',
      summary: 'Créneau supprimé',
      detail: 'Le créneau a été retiré de la grille.',
      life: 3000
    });
    await fetchSlots();
  } catch (error) {
    console.error('Erreur lors de la suppression du créneau:', error);
  }
}

const getSoutenanceEnd = (startStr, durationMin) => {
  const start = new Date(startStr);
  const end = new Date(start.getTime() + durationMin * 60000);
  return end.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
  <div class="space-y-6 text-xs">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-surface  pb-5">
      <div>
        <h2 class="text-lg font-bold text-color -contrast">Planning par Grille de Soutenances</h2>
        <p class="text-xs text-muted-color">
          Générez une grille de créneaux et affectez-y directement les étudiants et les jurys pour <strong class="text-primary ">{{ activePeriodName }}</strong>.
        </p>
      </div>

      <!-- Duration Config -->
      <div class="bg-emphasis  p-3 rounded-2xl border border-surface  flex items-center gap-3">
        <div class="flex flex-col">
          <label class="text-[9px] font-black uppercase text-muted-color">Durée par soutenance</label>
          <div class="flex items-center gap-1.5 mt-0.5">
            <input 
              v-model.number="defaultDuration"
              type="number"
              min="10"
              max="180"
              class="w-16 p-1 text-xs font-bold border border-surface  rounded-lg bg-surface-0  text-color "
            />
            <span class="text-muted-color font-semibold">min</span>
          </div>
        </div>
        <Button label="Enregistrer" icon="pi pi-check" size="small" :loading="isUpdatingDuration" @click="updatePeriodDuration" />
      </div>
    </div>

    <!-- Scheduled Sessions Info -->
    <div v-if="sessions.length > 0" class="p-4 bg-emphasis  border border-surface  rounded-3xl space-y-2">
      <div class="font-bold text-color  flex items-center gap-1.5">
        <i class="pi pi-info-circle text-primary "></i>
        <span>Sessions de soutenances prévues pour cette période :</span>
      </div>
      <div class="flex flex-wrap gap-2">
        <span v-for="s in sessions" :key="s.id" class="px-3 py-1.5 bg-emphasis  text-primary  font-bold rounded-xl text-[10px]">
          Du {{ new Date(s.dateDebut).toLocaleDateString('fr-FR') }} au {{ new Date(s.dateFin).toLocaleDateString('fr-FR') }}
          <span class="text-muted-color  font-medium"> (Rapport avant le {{ new Date(s.dateRenduRapport).toLocaleDateString('fr-FR') }})</span>
        </span>
      </div>
    </div>

    <!-- Bulk Slot Generator Panel -->
    <div class="bg-surface-0  p-5 rounded-3xl border border-surface  shadow-sm space-y-4">
      <div class="flex items-center gap-2 border-b border-surface  pb-2">
        <i class="pi pi-calendar-plus text-primary  text-base"></i>
        <h3 class="text-sm font-bold text-color ">Générateur de créneaux vides en lot</h3>
      </div>
      
      <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
        <!-- Date -->
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-bold text-muted-color">Date des examens</label>
          <input 
            v-model="bulkDate"
            type="date"
            class="p-2 border border-surface  rounded-xl bg-emphasis  text-color "
          />
        </div>

        <!-- Start Time -->
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-bold text-muted-color">Heure de début</label>
          <input 
            v-model="bulkStartTime"
            type="time"
            class="p-2 border border-surface  rounded-xl bg-emphasis  text-color "
          />
        </div>

        <!-- End Time -->
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-bold text-muted-color">Heure de fin</label>
          <input 
            v-model="bulkEndTime"
            type="time"
            class="p-2 border border-surface  rounded-xl bg-emphasis  text-color "
          />
        </div>

        <!-- Duration -->
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-bold text-muted-color">Durée (minutes)</label>
          <input 
            v-model.number="bulkDuree"
            type="number"
            min="10"
            class="p-2 border border-surface  rounded-xl bg-emphasis  text-color "
          />
        </div>

        <!-- Pause -->
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-bold text-muted-color">Pause entre créneaux</label>
          <input 
            v-model.number="bulkPause"
            type="number"
            min="0"
            class="p-2 border border-surface  rounded-xl bg-emphasis  text-color "
          />
        </div>

        <!-- Salle -->
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-bold text-muted-color">Salle par défaut</label>
          <input 
            v-model="bulkSalle"
            type="text"
            placeholder="Ex: Salle B05"
            class="p-2 border border-surface  rounded-xl bg-emphasis  text-color "
          />
        </div>
      </div>

      <div class="flex justify-end pt-2 border-t border-surface ">
        <Button label="Générer la grille" icon="pi pi-calendar-plus" :loading="isGeneratingBulk" @click="generateBulkSlots" />
      </div>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="bg-surface-0  border border-surface  p-4 rounded-3xl shadow-sm flex items-center gap-3.5">
        <div class="w-10 h-10 rounded-2xl bg-emphasis  text-primary  flex items-center justify-center shrink-0">
          <i class="pi pi-calendar text-lg"></i>
        </div>
        <div>
          <span class="text-[9px] font-black uppercase tracking-wider text-muted-color block">Créneaux dans la grille</span>
          <span class="text-lg font-black text-color -contrast mt-0.5 block">{{ slots.length }}</span>
        </div>
      </div>

      <div class="bg-surface-0  border border-surface  p-4 rounded-3xl shadow-sm flex items-center gap-3.5">
        <div class="w-10 h-10 rounded-2xl bg-emphasis  text-muted-color  flex items-center justify-center shrink-0">
          <i class="pi pi-users text-lg"></i>
        </div>
        <div>
          <span class="text-[9px] font-black uppercase tracking-wider text-muted-color block">Créneaux libres</span>
          <span class="text-lg font-black text-color -contrast mt-0.5 block">{{ slots.filter(s => !s.stageEtudiantId).length }}</span>
        </div>
      </div>

      <div class="bg-surface-0  border border-surface  p-4 rounded-3xl shadow-sm flex items-center gap-3.5">
        <div class="w-10 h-10 rounded-2xl bg-emphasis  text-muted-color  flex items-center justify-center shrink-0">
          <i class="pi pi-user-minus text-lg"></i>
        </div>
        <div>
          <span class="text-[9px] font-black uppercase tracking-wider text-muted-color block">Étudiants restants à planifier</span>
          <span class="text-lg font-black text-color -contrast mt-0.5 block">{{ unscheduledStudents.length }}</span>
        </div>
      </div>
    </div>

    <!-- Interactive Grid Table -->
    <div class="bg-surface-0  rounded-3xl border border-surface  shadow-sm overflow-hidden">
      <div v-if="slots.length > 0" class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-emphasis  text-muted-color uppercase font-black tracking-wider text-[10px] border-b border-surface ">
              <th class="p-4 w-48">Date &amp; Horaire</th>
              <th class="p-4 w-28">Salle</th>
              <th class="p-4 w-64">Étudiant (Stagiaire)</th>
              <th class="p-4 w-44">Tuteur Universitaire</th>
              <th class="p-4 w-52">Enseignant Assesseur (Candide)</th>
              <th class="p-4 text-center w-28">Statut</th>
              <th class="p-4 text-right w-36">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-surface ">
            <tr 
              v-for="s in slots" 
              :key="s.id" 
              class="hover:bg-emphasis dark:hover:bg-emphasis transition-colors"
              :class="{'bg-emphasis ': s.isEdited}"
            >
              <!-- Time slot -->
              <td class="p-4">
                <div class="font-bold text-color ">
                  {{ new Date(s.dateSoutenance).toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' }) }}
                </div>
                <div class="text-primary  font-black mt-0.5">
                  {{ new Date(s.dateSoutenance).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}
                  -
                  {{ getSoutenanceEnd(s.dateSoutenance, s.duree) }}
                </div>
              </td>

              <!-- Salle -->
              <td class="p-4">
                <input 
                  v-model="s.salle"
                  type="text"
                  @change="onSlotFieldChange(s)"
                  placeholder="Ex: B05"
                  class="w-full p-2 border border-surface  rounded-xl bg-emphasis  text-xs text-color  focus:outline-primary font-bold"
                />
              </td>

              <!-- Student Dropdown -->
              <td class="p-4">
                <select 
                  v-model="s.stageEtudiantId"
                  @change="onSlotFieldChange(s)"
                  class="w-full p-2 border border-surface  rounded-xl bg-emphasis  text-xs text-color  focus:outline-primary font-semibold"
                >
                  <option :value="null">-- Créneau libre (Libre) --</option>
                  <option 
                    v-for="std in getAvailableStudentsForSlot(s)" 
                    :key="std.id" 
                    :value="std.id"
                  >
                    {{ std.studentName }}
                  </option>
                </select>
                <div v-if="s.stageEtudiantId && getStudentDetails(s.stageEtudiantId)" class="text-[10px] text-muted-color font-medium mt-1 pl-1">
                  {{ getStudentDetails(s.stageEtudiantId).company }}
                </div>
              </td>

              <!-- Tutor (Auto display) -->
              <td class="p-4">
                <span 
                  v-if="s.stageEtudiantId && getStudentDetails(s.stageEtudiantId)"
                  class="px-2 py-1 rounded-lg bg-emphasis  text-primary  font-bold border border-primary  text-[10px] block text-center"
                >
                  {{ getStudentDetails(s.stageEtudiantId).tutor }}
                </span>
                <span v-else class="text-muted-color italic text-[10px]">Auto-rempli</span>
              </td>

              <!-- Assesseur / Candide Dropdown -->
              <td class="p-4">
                <select 
                  v-model="s.enseignantJuryId"
                  @change="onSlotFieldChange(s)"
                  class="w-full p-2 border border-surface  rounded-xl bg-emphasis  text-xs text-color  focus:outline-primary"
                >
                  <option :value="null">Aucun assesseur (Candide)</option>
                  <option 
                    v-for="t in teachers" 
                    :key="t.id" 
                    :value="t.id"
                  >
                    {{ t.prenom }} {{ t.nom }}
                  </option>
                </select>
              </td>

              <!-- Conflict Status Badge -->
              <td class="p-4 text-center">
                <div v-if="slotConflicts[s.id] && slotConflicts[s.id].length > 0" class="inline-flex items-center justify-center gap-1 text-muted-color font-bold bg-emphasis  border border-surface  px-2.5 py-1 rounded-xl" :title="slotConflicts[s.id].join('\n')">
                  <i class="pi pi-exclamation-triangle"></i>
                  <span>Conflit</span>
                </div>
                <div v-else-if="s.stageEtudiantId" class="inline-flex items-center justify-center gap-1 text-muted-color font-bold bg-emphasis  border border-surface  px-2.5 py-1 rounded-xl">
                  <i class="pi pi-check"></i>
                  <span>Prêt</span>
                </div>
                <span v-else class="text-muted-color italic">Libre</span>
              </td>

              <!-- Actions -->
              <td class="p-4 text-right">
                <div class="flex justify-end gap-1.5">
                  <!-- Save -->
                  <Button icon="pi pi-save" severity="success" rounded size="small" :loading="s.isSaving"
                    :disabled="slotConflicts[s.id] && slotConflicts[s.id].length > 0"
                    aria-label="Enregistrer les affectations" v-tooltip.top="'Enregistrer les affectations'" @click="saveSlot(s)" />
                  
                  <!-- Free Slot -->
                  <button 
                    @click="clearSlot(s)"
                    v-if="s.stageEtudiantId"
                    class="p-2 border border-surface  rounded-xl bg-surface-0  text-muted-color hover:text-muted-color hover:border-surface transition-all cursor-pointer"
                    title="Libérer le créneau (Désassigner)"
                  >
                    <i class="pi pi-user-minus"></i>
                  </button>

                  <!-- Delete Slot completely -->
                  <button 
                    @click="deleteSlot(s.id)"
                    class="p-2 border border-surface  rounded-xl bg-surface-0  text-muted-color hover:text-muted-color hover:border-surface transition-all cursor-pointer"
                    title="Supprimer le créneau de la grille"
                  >
                    <i class="pi pi-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="p-12 text-center text-muted-color">
        <i class="pi pi-calendar-times text-4xl text-color block mb-3"></i>
        <h3 class="text-sm font-bold text-color ">Grille vide</h3>
        <p class="text-xs text-muted-color mt-1">Utilisez le générateur ci-dessus pour ajouter des créneaux horaires.</p>
      </div>
    </div>
  </div>
</template>
