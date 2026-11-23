<script setup>
import { adresseLigne, dateFr, nomContact } from '@/utils/stage';
import EvaluationEntreprisePanel from '@/components/EvaluationEntreprisePanel.vue';
import { messageErreurApi } from '@/utils/erreurApi';
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { FilterMatchMode } from '@primevue/core/api';
import { useUsersStore, useAnneeUnivStore } from '@stores';
import { getStageEtudiantsService, updateStageEtudiantService, getStagePeriodesService, rapportStageUrl } from '@/requests/stage_service';
import { HeaderComponent, Card, Kpi, EmptyState } from '@components';
import {
  UserIcon,
  UsersIcon,
  DocumentTextIcon,
  StarIcon,
  MagnifyingGlassIcon,
  PencilSquareIcon,
  EyeIcon,
  ArrowUpTrayIcon,
  ExclamationTriangleIcon,
  CheckIcon,
  XMarkIcon,
  InformationCircleIcon,
  BuildingOfficeIcon,
  UserPlusIcon,
  BookOpenIcon,
  PlusIcon,
  CalendarDaysIcon,
  ChatBubbleLeftRightIcon,
  ClockIcon,
  EnvelopeIcon,
  ArchiveBoxIcon
} from '@heroicons/vue/24/outline';

const toast = useToast();
const userStore = useUsersStore();
const anneeUnivStore = useAnneeUnivStore();

const activeTab = ref('current');

// Current academic year libelle
const currentAnneeUnivLibelle = computed(() => {
  return anneeUnivStore.selectedAnneeUniv?.libelle 
    || anneeUnivStore.selectedAnneeUniv?.label
    || anneeUnivStore.anneeUniv?.libelle 
    || '2026-2027';
});

// Filtering state for current year
const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
  studentName: { value: null, matchMode: FilterMatchMode.CONTAINS },
  company: { value: null, matchMode: FilterMatchMode.CONTAINS },
  period: { value: null, matchMode: FilterMatchMode.EQUALS },
  reportUploaded: { value: null, matchMode: FilterMatchMode.EQUALS }
});

// Filtering state for archives
const archiveFilters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
  studentName: { value: null, matchMode: FilterMatchMode.CONTAINS },
  company: { value: null, matchMode: FilterMatchMode.CONTAINS },
  period: { value: null, matchMode: FilterMatchMode.EQUALS },
  reportUploaded: { value: null, matchMode: FilterMatchMode.EQUALS },
  anneeUniversitaire: { value: null, matchMode: FilterMatchMode.EQUALS }
});

const periodOptions = ref([]);
const statusOptions = ref([
  { label: 'Déposé', value: true },
  { label: 'En attente', value: false }
]);

const students = ref([]);
const loading = ref(false);

// Map backend StageEtudiant entity format to Vue template format
const mapStageEtudiantToVue = (se) => {
  const etu = se.etudiant || {};
  const ent = se.entreprise || {};
  const tut = se.tuteur || {};
  const p = se.stagePeriode || {};

  const formatDate = dateFr;

  const datesStr = (se.dateDebutStage && se.dateFinStage)
    ? `${formatDate(se.dateDebutStage)} au ${formatDate(se.dateFinStage)}`
    : '-';

  const supervisorName = nomContact(tut);

  const dateDebutDiff = se.dateDebutStage && p.dateDebut && se.dateDebutStage.substring(0, 10) !== p.dateDebut.substring(0, 10);
  const dateFinDiff = se.dateFinStage && p.dateFin && se.dateFinStage.substring(0, 10) !== p.dateFin.substring(0, 10);
  const isDatesDiff = !!(dateDebutDiff || dateFinDiff);

  const address = ent.adresse || {};
  const formattedAddress = adresseLigne(address);

  const tuteurUniv = se.tuteurUniversitaire || {};
  const academicTutorName = nomContact(tuteurUniv, 'Non affecté');

  // Signatory (Responsable de l'entreprise)
  const resp = ent.responsable || {};
  const signatoryName = nomContact(resp);

  const anneeUniv = p.anneeUniversitaire || {};
  const anneeUnivLibelle = (typeof anneeUniv === 'object' && anneeUniv !== null)
    ? (anneeUniv.libelle || '')
    : '';
  const anneeUnivId = (typeof anneeUniv === 'object' && anneeUniv !== null)
    ? (anneeUniv.id || null)
    : (typeof anneeUniv === 'string' ? parseInt(anneeUniv.split('/').pop(), 10) : null);
  const anneeUnivActif = (typeof anneeUniv === 'object' && anneeUniv !== null)
    ? (anneeUniv.actif === true)
    : false;

  return {
    id: se.id,
    studentName: `${etu.prenom || ''} ${etu.nom || ''}`.trim() || 'Étudiant inconnu',
    studentPhone: etu.tel1 || etu.tel2 || '',
    studentEmail: etu.mailPerso || etu.mailUniv || '',
    period: p.libelle || 'Période inconnue',
    anneeUniversitaire: anneeUnivLibelle || 'Non définie',
    anneeUnivId,
    anneeUnivActif,
    company: ent.raisonSociale || '-',
    siret: ent.siret || '-',
    companyAddress: formattedAddress,
    companyPhone: resp.telephone || '-',
    signatoryName: signatoryName,
    signatoryTitle: resp.fonction || '-',
    signatoryEmail: resp.email || '-',
    dates: datesStr,
    startDateStr: formatDate(se.dateDebutStage),
    endDateStr: formatDate(se.dateFinStage),
    periodStartDateStr: formatDate(p.dateDebut),
    periodEndDateStr: formatDate(p.dateFin),
    dateDebutDiff,
    dateFinDiff,
    isDatesDiff,
    supervisor: supervisorName,
    supervisorTitle: tut.fonction || '-',
    supervisorEmail: tut.email || '-',
    supervisorPhone: tut.telephone || '-',
    academicTutor: academicTutorName,
    subject: se.sujetStage || 'Non renseigné',
    activities: se.activites || 'Non renseignées',
    weeklyHours: se.dureeHebdomadaire || 35,
    reportUploaded: !!se.reportUploaded,
    reportName: se.reportName || '',
    grade: se.evaluationNote !== null && se.evaluationNote !== undefined ? String(se.evaluationNote) : '',
    comments: se.evaluationCommentaire || '',
    followups: se.suiviRencontres || []
  };
};

// Check if a student belongs to the current academic year
const isStudentInCurrentYear = (s) => {
  if (s.anneeUnivActif) return true;
  const currentLib = currentAnneeUnivLibelle.value;
  if (s.anneeUniversitaire && currentLib && s.anneeUniversitaire === currentLib) return true;
  if (!s.anneeUnivId && (!s.anneeUniversitaire || s.anneeUniversitaire === 'Non définie')) return true;
  return false;
};

// Split into current year students and archives
const currentStudents = computed(() => {
  return students.value.filter(isStudentInCurrentYear);
});

const archiveStudents = computed(() => {
  return students.value.filter(s => !isStudentInCurrentYear(s));
});

const sortedArchiveStudents = computed(() => {
  return [...archiveStudents.value].sort((a, b) => {
    return (b.anneeUniversitaire || '').localeCompare(a.anneeUniversitaire || '');
  });
});

const archiveYearOptions = computed(() => {
  const years = archiveStudents.value
    .map(s => s.anneeUniversitaire)
    .filter(y => y && y !== 'Non définie');
  return [...new Set(years)].sort((a, b) => b.localeCompare(a));
});

const currentPeriodOptions = computed(() => {
  const list = currentStudents.value.map(s => s.period).filter(Boolean);
  return [...new Set(list)];
});

const archivePeriodOptions = computed(() => {
  const list = archiveStudents.value.map(s => s.period).filter(Boolean);
  return [...new Set(list)];
});

const fetchSupervisedStudents = async () => {
  if (!userStore.userId) return;
  loading.value = true;
  try {
    const data = await getStageEtudiantsService({
      tuteurUniversitaire: `/api/personnels/${userStore.userId}`,
      itemsPerPage: 300
    });
    const targetUserId = parseInt(userStore.userId, 10);
    const targetUserIri = `/api/personnels/${targetUserId}`;
    const supervisedOnly = (data || []).filter(se => {
      const tut = se.tuteurUniversitaire;
      if (!tut) return false;
      if (typeof tut === 'object' && tut !== null) {
        if (tut.id && parseInt(tut.id, 10) === targetUserId) return true;
        if (tut['@id'] && tut['@id'] === targetUserIri) return true;
      }
      if (typeof tut === 'string') {
        return tut === targetUserIri || parseInt(tut.split('/').pop(), 10) === targetUserId;
      }
      return false;
    });
    students.value = supervisedOnly.map(mapStageEtudiantToVue);
  } catch (error) {
    console.error('Erreur lors du chargement des étudiants suivis:', error);
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger vos étudiants.', life: 3000 });
  } finally {
    loading.value = false;
  }
};

const fetchPeriodOptions = async () => {
  try {
    const data = await getStagePeriodesService();
    periodOptions.value = (data || []).map(p => p.libelle).filter((v, i, a) => a.indexOf(v) === i);
  } catch (error) {
    console.error('Erreur lors du chargement des périodes pour filtre:', error);
  }
};

onMounted(async () => {
  await userStore.getUser();
  try {
    if (!anneeUnivStore.anneeUniv) {
      await anneeUnivStore.getCurrentAnneeUniv();
    }
  } catch (err) {
    console.error('Erreur chargement année univ:', err);
  }
  await fetchSupervisedStudents();
  await fetchPeriodOptions();
});

// Selection state for detail dialog
const selectedStudent = ref(null);
const showDetailDialog = ref(false);
const showInfoDialog = ref(false);

const openStudentInfo = (student) => {
  selectedStudent.value = student;
  showInfoDialog.value = true;
};

// New follow-up form state
const newFollowup = ref({
  date: new Date().toISOString().split('T')[0],
  type: 'Appel Téléphonique',
  summary: ''
});

// Grading form state
const gradingInput = ref('');
const commentsInput = ref('');

// Computed counts based on current year
const totalSupervised = computed(() => currentStudents.value.length);
const reportsDeposited = computed(() => currentStudents.value.filter(s => s.reportUploaded).length);
const gradedCount = computed(() => currentStudents.value.filter(s => s.grade !== '').length);

const openStudentDetails = (student) => {
  selectedStudent.value = student;
  gradingInput.value = student.grade;
  commentsInput.value = student.comments;
  showDetailDialog.value = true;
};

const addFollowup = async () => {
  if (!newFollowup.value.summary.trim()) {
    toast.add({ severity: 'warn', summary: 'Erreur', detail: 'Le compte-rendu ne peut pas être vide.', life: 3000 });
    return;
  }

  const followupObj = {
    id: Date.now(),
    date: new Date(newFollowup.value.date).toLocaleDateString('fr-FR'),
    type: newFollowup.value.type,
    summary: newFollowup.value.summary
  };

  const updatedFollowups = [...(selectedStudent.value.followups || []), followupObj];

  try {
    await updateStageEtudiantService(selectedStudent.value.id, {
      suiviRencontres: updatedFollowups
    }, false);

    selectedStudent.value.followups = updatedFollowups;
    const studentIndex = students.value.findIndex(s => s.id === selectedStudent.value.id);
    if (studentIndex !== -1) {
      students.value[studentIndex].followups = updatedFollowups;
    }

    // Clear input
    newFollowup.value.summary = '';

    toast.add({
      severity: 'success',
      summary: 'Suivi enregistré',
      detail: 'Le compte-rendu de suivi a bien été ajouté.',
      life: 3000
    });
  } catch (error) {
    console.error('Erreur lors de la sauvegarde du suivi:', error);
    toast.add({ severity: 'error', summary: 'Erreur', detail: messageErreurApi(error, 'Impossible d\'enregistrer le suivi.'), life: 5000 });
  }
};

const saveGrading = async () => {
  const note = gradingInput.value.trim() !== '' ? parseFloat(gradingInput.value) : null;
  if (gradingInput.value.trim() !== '' && (isNaN(note) || note < 0 || note > 20)) {
    toast.add({ severity: 'warn', summary: 'Erreur', detail: 'La note doit être un nombre compris entre 0 et 20.', life: 3000 });
    return;
  }

  try {
    await updateStageEtudiantService(selectedStudent.value.id, {
      evaluationNote: note,
      evaluationCommentaire: commentsInput.value
    }, false);

    selectedStudent.value.grade = gradingInput.value.trim() !== '' ? String(note) : '';
    selectedStudent.value.comments = commentsInput.value;

    const studentIndex = students.value.findIndex(s => s.id === selectedStudent.value.id);
    if (studentIndex !== -1) {
      students.value[studentIndex].grade = selectedStudent.value.grade;
      students.value[studentIndex].comments = selectedStudent.value.comments;
    }

    toast.add({
      severity: 'success',
      summary: 'Évaluation enregistrée',
      detail: 'La note et les appréciations ont bien été mises à jour.',
      life: 3000
    });
  } catch (error) {
    console.error('Erreur lors de la sauvegarde de la note:', error);
    toast.add({ severity: 'error', summary: 'Erreur', detail: messageErreurApi(error, 'Impossible de sauvegarder l\'évaluation.'), life: 5000 });
  }
};

const getInitials = (name) => {
  return name.split(' ').map(n => n[0]).join('');
};
</script>

<template>
  <div class="space-y-6">


    <!-- Top Header -->
    <HeaderComponent
      :icon="UsersIcon"
      color="violet"
      titre="Mes Étudiants en Stage"
      description="Suivez l'avancement des stages dont vous êtes le tuteur universitaire désigné."
    />

    <!-- Quick Stats Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <Kpi
        :value="totalSupervised"
        :label="`Étudiants suivis (${currentAnneeUnivLibelle})`"
        :icon="UserIcon"
        color="indigo"
      />
      <Kpi
        :value="reportsDeposited + ' / ' + totalSupervised"
        label="Rapports déposés"
        :icon="DocumentTextIcon"
        color="emerald"
      />
      <Kpi
        :value="gradedCount + ' / ' + totalSupervised"
        label="Évaluations saisies"
        :icon="StarIcon"
        color="amber"
      />
    </div>

    <!-- Navigation Tabs: Année en cours & Archives -->
    <Tabs v-model:value="activeTab" class="w-full">
      <TabList class="mb-4 border-b border-surface ">
        <Tab value="current" class="flex items-center gap-2 cursor-pointer font-bold text-sm py-3 px-4">
          <CalendarDaysIcon class="w-4 h-4 text-primary " />
          <span>Année en cours ({{ currentAnneeUnivLibelle }})</span>
          <span class="ml-1.5 px-2 py-0.5 rounded-full text-xs font-bold bg-emphasis  text-primary ">
            {{ currentStudents.length }}
          </span>
        </Tab>
        <Tab value="archives" class="flex items-center gap-2 cursor-pointer font-bold text-sm py-3 px-4">
          <ArchiveBoxIcon class="w-4 h-4 text-muted-color" />
          <span>Archives des années précédentes</span>
          <span class="ml-1.5 px-2 py-0.5 rounded-full text-xs font-bold bg-emphasis  text-muted-color ">
            {{ archiveStudents.length }}
          </span>
        </Tab>
      </TabList>

      <TabPanels class="p-0 bg-transparent">
        <!-- Tab 1: Current Academic Year -->
        <TabPanel value="current">
          <Card
            title="Étudiants de l'année en cours"
            :subtitle="`Stages en cours pour l'année universitaire ${currentAnneeUnivLibelle}`"
            :icon="UsersIcon"
            color="violet"
          >
            <DataTable
              v-model:filters="filters"
              :value="currentStudents"
              :loading="loading"
              dataKey="id"
              paginator
              :rows="10"
              :rowsPerPageOptions="[10, 25, 50]"
              paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
              currentPageReportTemplate="Affichage de {first} à {last} sur {totalRecords} étudiants"
              responsiveLayout="scroll"
              class="text-xs text-color "
              stripedRows
              :globalFilterFields="['studentName', 'company', 'supervisor', 'period']"
            >
              <template #header>
                <div class="flex flex-wrap gap-4 items-center justify-between p-2 bg-emphasis  border-b border-surface ">
                  <div></div>
                  <div class="flex flex-wrap gap-3 items-center ml-auto">
                    <!-- Global Search -->
                    <IconField>
                      <InputIcon>
                        <MagnifyingGlassIcon class="w-4 h-4 text-muted-color" />
                      </InputIcon>
                      <InputText v-model="filters.global.value" placeholder="Rechercher..." class="py-1.5 px-3 text-xs w-[180px] bg-surface-0  border border-surface  rounded-xl focus:outline-none" />
                    </IconField>
                    
                    <!-- Filter by Period -->
                    <Select v-model="filters.period.value" :options="currentPeriodOptions.length > 0 ? currentPeriodOptions : periodOptions" showClear placeholder="Formation" class="text-xs min-w-[150px] bg-surface-0  border border-surface  rounded-xl" />

                    <!-- Filter by Report Status -->
                    <Select v-model="filters.reportUploaded.value" :options="statusOptions" optionLabel="label" optionValue="value" showClear placeholder="État du rapport" class="text-xs min-w-[150px] bg-surface-0  border border-surface  rounded-xl" />
                  </div>
                </div>
              </template>
              
              <Column header="Étudiant" field="studentName" sortable class="font-semibold text-color -contrast">
                <template #body="slotProps">
                  <div class="flex items-center gap-3 py-1">
                    <div
                      class="w-8 h-8 rounded-lg bg-emphasis  text-primary  flex items-center justify-center font-bold text-xs uppercase">
                      {{ getInitials(slotProps.data.studentName) }}
                    </div>
                    <div>
                      <span class="font-bold text-sm block">{{ slotProps.data.studentName }}</span>
                      <span class="text-[10px] text-muted-color block">{{ slotProps.data.period }}</span>
                    </div>
                  </div>
                </template>
              </Column>

              <Column field="company" header="Entreprise" sortable />
              
              <Column header="Dates">
                <template #body="slotProps">
                  <span :class="{'text-muted-color  font-black flex items-center gap-1 w-max': slotProps.data.isDatesDiff}" :title="slotProps.data.isDatesDiff ? 'Dates différentes de la période de stage (' + slotProps.data.periodStartDateStr + ' au ' + slotProps.data.periodEndDateStr + ')' : ''">
                    <span>{{ slotProps.data.dates }}</span>
                    <ExclamationTriangleIcon v-if="slotProps.data.isDatesDiff" class="w-3.5 h-3.5 text-muted-color" />
                  </span>
                </template>
              </Column>
              
              <Column field="supervisor" header="Maître de Stage" sortable />

              <Column header="Rapport" class="text-center">
                <template #body="slotProps">
                  <span v-if="slotProps.data.reportUploaded"
                    class="bg-emphasis  text-color  px-2 py-0.5 rounded font-bold text-[10px] flex items-center gap-1 w-max">
                    <CheckIcon class="w-3 h-3 text-muted-color " />
                    <span>Déposé</span>
                  </span>
                  <span v-else
                    class="bg-emphasis  text-color  px-2 py-0.5 rounded font-bold text-[10px] flex items-center gap-1 w-max">
                    <XMarkIcon class="w-3 h-3 text-muted-color " />
                    <span>En attente</span>
                  </span>
                </template>
              </Column>

              <Column field="grade" header="Note" sortable class="text-center font-bold">
                <template #body="slotProps">
                  <span>{{ slotProps.data.grade ? slotProps.data.grade + ' / 20' : '-' }}</span>
                </template>
              </Column>

              <Column header="Actions" class="text-right py-1">
                <template #body="slotProps">
                  <div class="flex gap-2 justify-end">
                    <button @click="openStudentInfo(slotProps.data)"
                      class="text-xs font-bold px-2.5 py-1.5 bg-emphasis hover:bg-emphasis  dark:hover:bg-emphasis text-color  rounded-xl transition-all flex items-center gap-1.5 cursor-pointer border-0 shadow-sm">
                      <EyeIcon class="w-3.5 h-3.5 text-muted-color " />
                      <span>Détails</span>
                    </button>
                    <button @click="openStudentDetails(slotProps.data)"
                      class="text-xs font-bold px-2.5 py-1.5 bg-emphasis  hover:bg-primary hover:text-primary-contrast dark:hover:bg-primary rounded-xl transition-all flex items-center gap-1.5 cursor-pointer border-0 shadow-sm">
                      <ChatBubbleLeftRightIcon class="w-3.5 h-3.5" />
                      <span>Suivre</span>
                    </button>
                  </div>
                </template>
              </Column>

              <template #empty>
                <EmptyState compact :icon="UsersIcon" title="Aucun étudiant en stage" :description="`Aucun stagiaire suivi pour l'année universitaire ${currentAnneeUnivLibelle}.`" />
              </template>
            </DataTable>
          </Card>
        </TabPanel>

        <!-- Tab 2: Archives of Previous Academic Years -->
        <TabPanel value="archives">
          <Card
            title="Archives des stages"
            subtitle="Historique des étudiants suivis lors des années universitaires précédentes."
            :icon="ArchiveBoxIcon"
            color="slate"
          >
            <DataTable
              v-model:filters="archiveFilters"
              :value="sortedArchiveStudents"
              :loading="loading"
              dataKey="id"
              paginator
              :rows="10"
              :rowsPerPageOptions="[10, 25, 50]"
              paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
              currentPageReportTemplate="Affichage de {first} à {last} sur {totalRecords} étudiants archivés"
              responsiveLayout="scroll"
              class="text-xs text-color "
              stripedRows
              :globalFilterFields="['studentName', 'company', 'supervisor', 'period', 'anneeUniversitaire']"
            >
              <template #header>
                <div class="flex flex-wrap gap-4 items-center justify-between p-2 bg-emphasis  border-b border-surface ">
                  <div></div>
                  <div class="flex flex-wrap gap-3 items-center ml-auto">
                    <!-- Global Search -->
                    <IconField>
                      <InputIcon>
                        <MagnifyingGlassIcon class="w-4 h-4 text-muted-color" />
                      </InputIcon>
                      <InputText v-model="archiveFilters.global.value" placeholder="Rechercher..." class="py-1.5 px-3 text-xs w-[180px] bg-surface-0  border border-surface  rounded-xl focus:outline-none" />
                    </IconField>
                    
                    <!-- Filter by Année Universitaire -->
                    <Select v-if="archiveYearOptions.length > 0" v-model="archiveFilters.anneeUniversitaire.value" :options="archiveYearOptions" showClear placeholder="Année Univ." class="text-xs min-w-[140px] bg-surface-0  border border-surface  rounded-xl" />

                    <!-- Filter by Period -->
                    <Select v-model="archiveFilters.period.value" :options="archivePeriodOptions.length > 0 ? archivePeriodOptions : periodOptions" showClear placeholder="Formation" class="text-xs min-w-[150px] bg-surface-0  border border-surface  rounded-xl" />

                    <!-- Filter by Report Status -->
                    <Select v-model="archiveFilters.reportUploaded.value" :options="statusOptions" optionLabel="label" optionValue="value" showClear placeholder="État du rapport" class="text-xs min-w-[150px] bg-surface-0  border border-surface  rounded-xl" />
                  </div>
                </div>
              </template>

              <!-- Column 1: Année Universitaire (with Badge) -->
              <Column header="Année Univ." field="anneeUniversitaire" sortable class="font-bold min-w-[130px]">
                <template #body="slotProps">
                  <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emphasis  text-color  border border-surface ">
                    <CalendarDaysIcon class="w-3.5 h-3.5 mr-1 text-muted-color" />
                    {{ slotProps.data.anneeUniversitaire }}
                  </span>
                </template>
              </Column>

              <!-- Column 2: Étudiant -->
              <Column header="Étudiant" field="studentName" sortable class="font-semibold text-color -contrast">
                <template #body="slotProps">
                  <div class="flex items-center gap-3 py-1">
                    <div
                      class="w-8 h-8 rounded-lg bg-emphasis  text-color  flex items-center justify-center font-bold text-xs uppercase">
                      {{ getInitials(slotProps.data.studentName) }}
                    </div>
                    <div>
                      <span class="font-bold text-sm block">{{ slotProps.data.studentName }}</span>
                      <span class="text-[10px] text-muted-color block">{{ slotProps.data.period }}</span>
                    </div>
                  </div>
                </template>
              </Column>

              <Column field="company" header="Entreprise" sortable />
              <Column header="Dates" field="dates" />
              <Column field="supervisor" header="Maître de Stage" sortable />

              <Column header="Rapport" class="text-center">
                <template #body="slotProps">
                  <span v-if="slotProps.data.reportUploaded"
                    class="bg-emphasis  text-color  px-2 py-0.5 rounded font-bold text-[10px] flex items-center gap-1 w-max">
                    <CheckIcon class="w-3 h-3 text-muted-color " />
                    <span>Déposé</span>
                  </span>
                  <span v-else
                    class="bg-emphasis  text-color  px-2 py-0.5 rounded font-bold text-[10px] flex items-center gap-1 w-max">
                    <XMarkIcon class="w-3 h-3 text-muted-color " />
                    <span>En attente</span>
                  </span>
                </template>
              </Column>

              <Column field="grade" header="Note" sortable class="text-center font-bold">
                <template #body="slotProps">
                  <span>{{ slotProps.data.grade ? slotProps.data.grade + ' / 20' : '-' }}</span>
                </template>
              </Column>

              <Column header="Actions" class="text-right py-1">
                <template #body="slotProps">
                  <div class="flex gap-2 justify-end">
                    <button @click="openStudentInfo(slotProps.data)"
                      class="text-xs font-bold px-2.5 py-1.5 bg-emphasis hover:bg-emphasis  dark:hover:bg-emphasis text-color  rounded-xl transition-all flex items-center gap-1.5 cursor-pointer border-0 shadow-sm">
                      <EyeIcon class="w-3.5 h-3.5 text-muted-color " />
                      <span>Détails</span>
                    </button>
                    <button @click="openStudentDetails(slotProps.data)"
                      class="text-xs font-bold px-2.5 py-1.5 bg-emphasis  hover:bg-primary hover:text-primary-contrast dark:hover:bg-primary rounded-xl transition-all flex items-center gap-1.5 cursor-pointer border-0 shadow-sm">
                      <ChatBubbleLeftRightIcon class="w-3.5 h-3.5" />
                      <span>Suivre</span>
                    </button>
                  </div>
                </template>
              </Column>

              <template #empty>
                <EmptyState compact :icon="ArchiveBoxIcon" title="Aucun stage archivé" description="Aucun stage suivi les années précédentes." />
              </template>
            </DataTable>
          </Card>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <!-- Student Detail & Follow-up Slideout Dialog -->
    <Dialog v-model:visible="showDetailDialog" modal header="Suivi individualisé de l'étudiant"
      :style="{ width: '80vw', maxWidth: '850px' }" class="text-xs  ">
      <div v-if="selectedStudent" class="grid grid-cols-1 lg:grid-cols-2 gap-8 py-4">

        <!-- Left Column: Internship Info & Followups history -->
        <div class="space-y-6">

          <!-- Summary Info Box -->
          <div class="bg-emphasis  rounded-2xl p-5 border border-surface ">
            <h4 class="text-sm font-bold text-color  flex items-center gap-2">
              <InformationCircleIcon class="w-4 h-4 text-primary" />
              <span>Détails du stage</span>
            </h4>
            <div class="grid grid-cols-2 gap-4 mt-4 text-[11px]">
              <div>
                <span class="text-muted-color block">Étudiant</span>
                <span class="font-bold text-color ">{{ selectedStudent.studentName }}</span>
              </div>
              <div>
                <span class="text-muted-color block">Année universitaire</span>
                <span class="font-bold text-color ">{{ selectedStudent.anneeUniversitaire }}</span>
              </div>
              <div>
                <span class="text-muted-color block">Entreprise</span>
                <span class="font-bold text-color ">{{ selectedStudent.company }}</span>
              </div>
              <div>
                <span class="text-muted-color block">Maître de stage</span>
                <span class="font-bold text-color ">{{ selectedStudent.supervisor }}</span>
              </div>
              <div class="col-span-2">
                <span class="text-muted-color block">Période de stage</span>
                <span class="font-bold text-color ">{{ selectedStudent.dates }}</span>
              </div>
            </div>

            <!-- Report Download link if uploaded -->
            <div v-if="selectedStudent.reportUploaded"
              class="mt-4 pt-3 border-t border-surface flex items-center justify-between bg-surface-0  p-2.5 rounded-xl border border-surface ">
              <span class="font-bold text-color  truncate max-w-[200px] flex items-center gap-2">
                <DocumentTextIcon class="w-4 h-4 text-muted-color" />
                {{ selectedStudent.reportName }}
              </span>
              <a :href="rapportStageUrl(selectedStudent.id)"
                class="text-[10px] font-bold text-primary  hover:underline flex items-center gap-1">
                <ArrowUpTrayIcon class="w-3.5 h-3.5" />
                <span>Télécharger</span>
              </a>
            </div>
          </div>

          <!-- Logged Follow-ups List -->
          <div>
            <h4 class="text-sm font-bold text-color  mb-4 flex items-center gap-2">
              <CalendarDaysIcon class="w-4 h-4 text-primary" />
              <span>Historique des Suivis ({{ selectedStudent.followups.length }})</span>
            </h4>

            <div class="space-y-4 max-h-[220px] overflow-y-auto pr-2">
              <div v-for="follow in selectedStudent.followups" :key="follow.id"
                class="bg-surface-0  border border-surface  rounded-xl p-4 space-y-1 relative">
                <div class="flex items-center justify-between">
                  <span
                    class="bg-emphasis  text-primary  px-2 py-0.5 rounded font-bold text-[9px]">
                    {{ follow.type }}
                  </span>
                  <span class="text-[9px] text-muted-color">{{ follow.date }}</span>
                </div>
                <p class="text-[10px] text-muted-color  leading-relaxed pt-1">
                  {{ follow.summary }}
                </p>
              </div>

              <EmptyState v-if="selectedStudent.followups.length === 0" compact icon="pi pi-comments" title="Aucun entretien de suivi" description="Ajoutez le compte rendu de votre premier échange avec le stagiaire." />
            </div>
          </div>

        </div>

        <!-- Right Column: Add follow-up & Grading -->
        <div class="space-y-6 lg:border-l lg:border-surface lg: lg:pl-6">

          <!-- Add Follow-up Form -->
          <div class="space-y-4">
            <h4 class="text-sm font-bold text-color  flex items-center gap-2">
              <PlusIcon class="w-4 h-4 text-primary" />
              <span>Ajouter un suivi</span>
            </h4>

            <div class="grid grid-cols-2 gap-4">
              <div class="flex flex-col gap-1.5">
                <label class="text-[10px] font-bold text-muted-color">Date du suivi</label>
                <input type="date" v-model="newFollowup.date"
                  class="p-2 border border-surface  rounded-lg bg-emphasis  text-color  text-xs" />
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-[10px] font-bold text-muted-color">Canal</label>
                <select v-model="newFollowup.type"
                  class="p-2 border border-surface  rounded-lg bg-emphasis  text-color  text-xs font-semibold">
                  <option value="Appel Téléphonique">Appel Téléphonique</option>
                  <option value="Visite Entreprise">Visite Entreprise</option>
                  <option value="Visioconférence">Visioconférence</option>
                  <option value="Échange d'E-mails">Échange d'E-mails</option>
                </select>
              </div>
            </div>

            <div class="flex flex-col gap-1.5">
              <label class="text-[10px] font-bold text-muted-color">Compte-rendu du suivi</label>
              <textarea v-model="newFollowup.summary" rows="2" placeholder="Renseignez le déroulement de l'échange..."
                class="w-full p-2.5 border border-surface  rounded-lg bg-emphasis  text-xs text-color  focus:outline-none focus:ring-1 focus:ring-primary"></textarea>
            </div>

            <button @click="addFollowup"
              class="w-full py-2 bg-primary hover:bg-primary text-primary-contrast font-bold rounded-lg text-xs transition-all flex items-center justify-center gap-2 border-0 cursor-pointer">
              <CheckIcon class="w-3.5 h-3.5" />
              <span>Enregistrer le suivi</span>
            </button>
          </div>

          <!-- Évaluation par le tuteur en entreprise -->
          <div class="pt-4 border-t border-surface  space-y-2">
            <h4 class="text-sm font-bold text-color ">Évaluation par l'entreprise</h4>
            <EvaluationEntreprisePanel :stage-id="selectedStudent.id" />
          </div>

          <!-- Grading Evaluation Panel -->
          <div class="pt-4 border-t border-surface  space-y-4">
            <h4 class="text-sm font-bold text-color  flex items-center gap-2">
              <StarIcon class="w-4 h-4 text-primary" />
              <span>Notation & Appréciation</span>
            </h4>

            <div class="flex gap-4 items-end">
              <div class="flex flex-col gap-1.5 w-1/3">
                <label class="text-[10px] font-bold text-muted-color">Note / 20</label>
                <input type="text" v-model="gradingInput" placeholder="Ex: 15.5"
                  class="p-2.5 border border-surface  rounded-lg bg-emphasis  text-color  text-xs font-bold text-center" />
              </div>
              <div class="flex-1">
                <span class="text-[10px] text-muted-color block pb-1">
                  La note doit être saisie après réception et lecture du rapport de stage.
                </span>
              </div>
            </div>

            <div class="flex flex-col gap-1.5">
              <label class="text-[10px] font-bold text-muted-color">Appréciations pédagogiques</label>
              <textarea v-model="commentsInput" rows="3"
                placeholder="Rédigez l'appréciation globale qui figurera sur le procès-verbal de stage..."
                class="w-full p-2.5 border border-surface  rounded-lg bg-emphasis  text-xs text-color  focus:outline-none focus:ring-1 focus:ring-primary"></textarea>
            </div>

            <button @click="saveGrading"
              class="w-full py-2 bg-emphasis hover:bg-emphasis text-primary-contrast font-bold rounded-lg text-xs transition-all flex items-center justify-center gap-2 border-0 cursor-pointer">
              <ArrowUpTrayIcon class="w-3.5 h-3.5" />
              <span>Sauvegarder l'évaluation</span>
            </button>
          </div>

        </div>
      </div>
    </Dialog>

    <!-- Internship Details Modal -->
    <Dialog v-model:visible="showInfoDialog" modal header="Informations détaillées du stage"
      :style="{ width: '90vw', maxWidth: '650px' }" class="text-xs  ">
      <div v-if="selectedStudent" class="space-y-6 py-2">
        
        <!-- Section: L'Étudiant & Le Stage -->
        <div class="bg-primary  p-4 rounded-2xl border border-primary">
          <h3 class="text-sm font-bold text-primary  flex items-center gap-2 mb-3">
            <UserIcon class="w-4 h-4 text-primary " />
            <span>L'Étudiant & Le Stage</span>
          </h3>
          <div class="grid grid-cols-2 gap-4 text-[11px]">
            <div>
              <span class="text-muted-color block">Nom de l'étudiant</span>
              <span class="font-bold text-color ">{{ selectedStudent.studentName }}</span>
            </div>
            <div>
              <span class="text-muted-color block">Formation</span>
              <span class="font-bold text-color ">{{ selectedStudent.period }}</span>
            </div>
            <div>
              <span class="text-muted-color block">Année universitaire</span>
              <span class="font-bold text-color ">{{ selectedStudent.anneeUniversitaire }}</span>
            </div>
            <div>
              <span class="text-muted-color block">Durée hebdomadaire</span>
              <span class="font-bold text-color ">{{ selectedStudent.weeklyHours }} heures</span>
            </div>
            <div class="col-span-2">
              <span class="text-muted-color block">Dates de stage</span>
              <span class="font-bold text-color " :class="{'text-muted-color ': selectedStudent.isDatesDiff}">
                {{ selectedStudent.dates }}
                <ExclamationTriangleIcon v-if="selectedStudent.isDatesDiff" class="w-3.5 h-3.5 text-muted-color inline ml-1" />
              </span>
            </div>
          </div>
        </div>

        <!-- Section: L'Entreprise -->
        <div class="bg-emphasis  p-4 rounded-2xl border border-surface ">
          <h3 class="text-sm font-bold text-color  flex items-center gap-2 mb-3">
            <BuildingOfficeIcon class="w-4 h-4 text-muted-color " />
            <span>L'Entreprise</span>
          </h3>
          <div class="grid grid-cols-2 gap-4 text-[11px] mb-3">
            <div>
              <span class="text-muted-color block">Raison Sociale</span>
              <span class="font-bold text-color ">{{ selectedStudent.company }}</span>
            </div>
            <div>
              <span class="text-muted-color block">SIRET</span>
              <span class="font-bold text-color ">{{ selectedStudent.siret }}</span>
            </div>
          </div>
          <div class="text-[11px] mb-3">
            <span class="text-muted-color block">Adresse de stage</span>
            <span class="font-bold text-color ">{{ selectedStudent.companyAddress }}</span>
          </div>
          <div class="grid grid-cols-2 gap-4 text-[11px] pt-3 border-t border-surface">
            <div>
              <span class="text-muted-color block">Signataire de la convention</span>
              <span class="font-bold text-color ">{{ selectedStudent.signatoryName }} ({{ selectedStudent.signatoryTitle }})</span>
            </div>
            <div>
              <span class="text-muted-color block">Contact Signataire</span>
              <span class="font-bold text-color ">{{ selectedStudent.signatoryEmail }} / {{ selectedStudent.companyPhone }}</span>
            </div>
          </div>
        </div>

        <!-- Section: Les Tuteurs -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="bg-emphasis  p-4 rounded-2xl border border-surface ">
            <h4 class="text-xs font-bold text-color  flex items-center gap-2 mb-2">
              <UserPlusIcon class="w-4 h-4 text-primary " />
              <span>Maître de Stage (Entreprise)</span>
            </h4>
            <div class="space-y-2 text-[11px]">
              <div>
                <span class="text-muted-color">Nom :</span> <span class="font-bold text-color ">{{ selectedStudent.supervisor }}</span>
              </div>
              <div>
                <span class="text-muted-color">Fonction :</span> <span class="font-bold text-color ">{{ selectedStudent.supervisorTitle }}</span>
              </div>
              <div>
                <span class="text-muted-color">Email :</span> <span class="font-bold text-color ">{{ selectedStudent.supervisorEmail }}</span>
              </div>
              <div>
                <span class="text-muted-color">Téléphone :</span> <span class="font-bold text-color ">{{ selectedStudent.supervisorPhone }}</span>
              </div>
            </div>
          </div>

          <div class="bg-emphasis  p-4 rounded-2xl border border-surface ">
            <h4 class="text-xs font-bold text-color  flex items-center gap-2 mb-2">
              <UserIcon class="w-4 h-4 text-primary " />
              <span>Tuteur Universitaire</span>
            </h4>
            <div class="space-y-2 text-[11px]">
              <div>
                <span class="text-muted-color">Nom :</span> <span class="font-bold text-color ">{{ selectedStudent.academicTutor }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Section: Sujet & Activités -->
        <div class="bg-emphasis  p-4 rounded-2xl border border-surface ">
          <h3 class="text-sm font-bold text-color  flex items-center gap-2 mb-3">
            <BookOpenIcon class="w-4 h-4 text-muted-color " />
            <span>Sujet & Activités</span>
          </h3>
          <div class="space-y-3 text-[11px]">
            <div>
              <span class="text-muted-color block">Sujet du stage</span>
              <p class="font-semibold text-color  bg-surface-0  p-2.5 rounded-xl border border-surface  mt-1 leading-relaxed">
                {{ selectedStudent.subject }}
              </p>
            </div>
            <div>
              <span class="text-muted-color block">Activités confiées</span>
              <p class="font-semibold text-color  bg-surface-0  p-2.5 rounded-xl border border-surface  mt-1 leading-relaxed whitespace-pre-line">
                {{ selectedStudent.activities }}
              </p>
            </div>
          </div>
        </div>

      </div>
    </Dialog>

  </div>
</template>


