<script setup>
import Tag from 'primevue/tag';
import { useConfirmation } from '@composables/useConfirmation';
import { dateFr, tagStatut } from '@/utils/stage';
import EvaluationEntreprisePanel from '@/components/EvaluationEntreprisePanel.vue';
import { ref, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import AutoComplete from 'primevue/autocomplete';
import { Kpi, Card, EmptyState } from '@components';
import {
  ArrowDownTrayIcon,
  ChartBarIcon,
  ChatBubbleLeftRightIcon,
  DocumentIcon,
  ClockIcon,
  UserGroupIcon,
  FunnelIcon,
  ArrowPathIcon,
  EyeIcon,
  PrinterIcon,
  PencilIcon,
  CheckCircleIcon,
  CheckIcon,
  UsersIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  TrashIcon,
  XMarkIcon,
  PencilSquareIcon,
  UserIcon,
  BriefcaseIcon,
  IdentificationIcon,
  InformationCircleIcon,
  EnvelopeIcon,
  XCircleIcon,
  DocumentCheckIcon,
  BuildingOffice2Icon,
} from '@heroicons/vue/24/outline';
import { DialogHeader, FormSection } from '../../../components/Form';

const props = defineProps({
  periods: {
    type: Array,
    required: true
  },
  selectedPeriodId: {
    type: [Number, String],
    required: true
  },
  periodStudents: {
    type: Array,
    required: true
  },
  kpis: {
    type: Object,
    required: true
  },
  teachers: {
    type: Array,
    required: true
  },
  activePeriodName: {
    type: String,
    required: true
  }
});

const emit = defineEmits(['update:selectedPeriodId', 'update-student']);

const toast = useToast();
const { confirmer } = useConfirmation();

import { FilterMatchMode } from '@primevue/core/api';

const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
  studentName: { value: null, matchMode: FilterMatchMode.CONTAINS },
  company: { value: null, matchMode: FilterMatchMode.CONTAINS },
  tutor: { value: null, matchMode: FilterMatchMode.CONTAINS },
  conventionStatus: { value: null, matchMode: FilterMatchMode.EQUALS },
  reportUploaded: { value: null, matchMode: FilterMatchMode.EQUALS }
});

const localSelectedPeriodId = computed({
  get: () => props.selectedPeriodId,
  set: (val) => emit('update:selectedPeriodId', val)
});

// Dialog state: review request
const selectedRequest = ref(null);
const showReviewDialog = ref(false);
const tutorSelect = ref('');
const rejectReason = ref('');

const filteredTutors = ref([]);
const searchTutors = (event) => {
  const query = (event.query || '').trim().toLowerCase();
  if (!query) {
    filteredTutors.value = [...props.teachers];
  } else {
    filteredTutors.value = props.teachers.filter(t =>
      (t.fullName && t.fullName.toLowerCase().includes(query)) ||
      (t.shortName && t.shortName.toLowerCase().includes(query)) ||
      (t.nom && t.nom.toLowerCase().includes(query)) ||
      (t.prenom && t.prenom.toLowerCase().includes(query))
    );
  }
};

const dialogTutorSuggestions = ref([]);
const searchDialogTutors = (event) => {
  const query = (event.query || '').trim().toLowerCase();
  if (!query) {
    dialogTutorSuggestions.value = [...props.teachers];
  } else {
    dialogTutorSuggestions.value = props.teachers.filter(t =>
      (t.fullName && t.fullName.toLowerCase().includes(query)) ||
      (t.shortName && t.shortName.toLowerCase().includes(query)) ||
      (t.nom && t.nom.toLowerCase().includes(query)) ||
      (t.prenom && t.prenom.toLowerCase().includes(query)) ||
      (t.email && t.email.toLowerCase().includes(query))
    );
  }
};

const getSelectedTutorName = () => {
  if (typeof tutorSelect.value === 'object' && tutorSelect.value !== null) {
    return tutorSelect.value.fullName || tutorSelect.value.shortName || '';
  }
  return tutorSelect.value || '';
};

// local form state for proofreading & editing
const parseSupervisor = (supervisorStr) => {
  if (!supervisorStr || supervisorStr === '-') {
    return { civilite: 'M', prenom: 'Robert', nom: 'LEGRAND', function: 'Tuteur en entreprise' };
  }
  const clean = supervisorStr.replace(/^(M\.|Mme\.|Mme|M)\s+/i, '').trim();
  const parts = clean.split(/\s+/);
  const civilite = supervisorStr.match(/Mme/i) ? 'Mme' : 'M';
  const prenom = parts[0] || 'Jean';
  const nom = parts.slice(1).join(' ') || 'DUPONT';
  return { civilite, prenom, nom, function: 'Tuteur en entreprise' };
};

const parseSalary = (salaryStr) => {
  if (!salaryStr || salaryStr === '-') return 4.35;
  const match = salaryStr.match(/(\d+([.,]\d+)?)/);
  return match ? parseFloat(match[1].replace(',', '.')) : 4.35;
};

const parseDatesToStartEnd = (datesStr) => {
  if (!datesStr || datesStr === '-') {
    return { start: '2026-03-02', end: '2026-06-26' };
  }
  const parts = datesStr.split(/\s+au\s+/i);
  if (parts.length === 2) {
    const parseFR = (str) => {
      const p = str.trim().split('/');
      if (p.length === 3) {
        return `${p[2]}-${p[1]}-${p[0]}`; // YYYY-MM-DD
      }
      return str;
    };
    return { start: parseFR(parts[0]), end: parseFR(parts[1]) };
  }
  return { start: '2026-03-02', end: '2026-06-26' };
};

// local form state for proofreading & editing
const editForm = ref({
  studentPhone: '',
  studentEmail: '',
  insuranceCompany: '',
  insurancePolicyNumber: '',
  companyName: '',
  companySiret: '',
  companyPhone: '',
  companyAddress: {
    adresse: '',
    complement1: '',
    complement2: '',
    ville: '',
    codePostal: '',
    pays: 'France'
  },
  signatoryCivilite: 'M',
  signatoryPrenom: '',
  signatoryNom: '',
  signatoryTitle: '',
  signatoryEmail: '',
  signatoryPhone: '',
  tuteurSameAsSignatory: false,
  supervisorCivilite: 'M',
  supervisorPrenom: '',
  supervisorNom: '',
  supervisorFunction: '',
  supervisorEmail: '',
  supervisorPhone: '',
  startDate: '',
  endDate: '',
  weeklyHours: 35,
  salaryAmount: 4.35,
  subject: '',
  activities: '',
  amenagementStage: ''
});

const openReviewRequest = (student) => {
  selectedRequest.value = student;
  const foundTeacher = props.teachers.find(t => t.fullName === student.tutor || t.shortName === student.tutor);
  tutorSelect.value = foundTeacher || (student.tutor && student.tutor !== 'Non affecté' ? student.tutor : '');
  rejectReason.value = '';

  const supInfo = parseSupervisor(student.supervisor);
  const datesInfo = parseDatesToStartEnd(student.dates);
  const salaryVal = parseSalary(student.salary);

  let addrObj = {
    adresse: '15 Rue de la Paix',
    complement1: '',
    complement2: '',
    ville: 'Paris',
    codePostal: '75002',
    pays: 'France'
  };
  if (student.companyAddress) {
    if (typeof student.companyAddress === 'object') {
      addrObj = { ...addrObj, ...student.companyAddress };
    } else {
      addrObj.adresse = student.companyAddress;
    }
  } else if (student.company && student.company !== '-') {
    addrObj.adresse = '15 Rue de la Paix';
  }

  // Populate local form state for editing
  editForm.value = {
    studentPhone: student.studentPhone || '06 12 34 56 78',
    studentEmail: student.studentEmail || `${student.studentName.toLowerCase().replace(/\s+/g, '.')}@univ-troyes.fr`,
    insuranceCompany: student.insuranceCompany || 'MAIF',
    insurancePolicyNumber: student.insurancePolicyNumber || '9876543-A',
    companyName: (student.company !== '-' ? student.company : '') || '',
    companySiret: student.siret || '12345678900010',
    companyPhone: student.companyPhone || '01 44 55 66 77',
    companyAddress: addrObj,
    signatoryCivilite: student.signatoryCivilite || 'M',
    signatoryPrenom: student.signatoryPrenom || 'Sylvie',
    signatoryNom: student.signatoryNom || 'MARTIN',
    signatoryTitle: student.signatoryTitle || 'Directrice RH',
    signatoryEmail: student.signatoryEmail || 's.martin@entreprise.com',
    signatoryPhone: student.signatoryPhone || '01 44 55 66 88',
    tuteurSameAsSignatory: student.tuteurSameAsSignatory ?? false,
    supervisorCivilite: student.supervisorCivilite || supInfo.civilite,
    supervisorPrenom: student.supervisorPrenom || supInfo.prenom,
    supervisorNom: student.supervisorNom || supInfo.nom,
    supervisorFunction: student.supervisorFunction || supInfo.function,
    supervisorEmail: student.supervisorEmail || 'tuteur.entreprise@domain.com',
    supervisorPhone: student.supervisorPhone || '06 99 88 77 66',
    startDate: student.startDate || datesInfo.start,
    endDate: student.endDate || datesInfo.end,
    weeklyHours: student.weeklyHours || 35,
    salaryAmount: student.salaryAmount || salaryVal,
    subject: (student.subject !== '-' ? student.subject : '') || '',
    activities: student.activities || 'Développement d\'applications au sein de l\'équipe technique.',
    amenagementStage: student.amenagementStage || ''
  };

  showReviewDialog.value = true;
};

const saveEdits = (silent = false) => {
  if (editForm.value.tuteurSameAsSignatory) {
    editForm.value.supervisorCivilite = editForm.value.signatoryCivilite;
    editForm.value.supervisorPrenom = editForm.value.signatoryPrenom;
    editForm.value.supervisorNom = editForm.value.signatoryNom;
    editForm.value.supervisorFunction = editForm.value.signatoryTitle;
    editForm.value.supervisorEmail = editForm.value.signatoryEmail;
    editForm.value.supervisorPhone = editForm.value.signatoryPhone;
  }

  const formatDateFR = dateFr;
  const datesFormatted = `${formatDateFR(editForm.value.startDate)} au ${formatDateFR(editForm.value.endDate)}`;
  const supervisorFormatted = `${editForm.value.supervisorCivilite}. ${editForm.value.supervisorPrenom} ${editForm.value.supervisorNom}`;
  const salaryFormatted = `${editForm.value.salaryAmount} €/h`;

  const updatedChanges = {
    company: editForm.value.companyName,
    siret: editForm.value.companySiret,
    dates: datesFormatted,
    subject: editForm.value.subject,
    activities: editForm.value.activities,
    supervisor: supervisorFormatted,
    salary: salaryFormatted,
    tutor: getSelectedTutorName() || 'Non affecté',

    studentPhone: editForm.value.studentPhone,
    studentEmail: editForm.value.studentEmail,
    insuranceCompany: editForm.value.insuranceCompany,
    insurancePolicyNumber: editForm.value.insurancePolicyNumber,
    companyPhone: editForm.value.companyPhone,
    companyAddress: { ...editForm.value.companyAddress },
    signatoryCivilite: editForm.value.signatoryCivilite,
    signatoryPrenom: editForm.value.signatoryPrenom,
    signatoryNom: editForm.value.signatoryNom,
    signatoryTitle: editForm.value.signatoryTitle,
    signatoryEmail: editForm.value.signatoryEmail,
    signatoryPhone: editForm.value.signatoryPhone,
    tuteurSameAsSignatory: editForm.value.tuteurSameAsSignatory,
    supervisorCivilite: editForm.value.supervisorCivilite,
    supervisorPrenom: editForm.value.supervisorPrenom,
    supervisorNom: editForm.value.supervisorNom,
    supervisorFunction: editForm.value.supervisorFunction,
    supervisorEmail: editForm.value.supervisorEmail,
    supervisorPhone: editForm.value.supervisorPhone,
    startDate: editForm.value.startDate,
    endDate: editForm.value.endDate,
    weeklyHours: editForm.value.weeklyHours,
    salaryAmount: editForm.value.salaryAmount,
    amenagementStage: editForm.value.amenagementStage
  };

  emit('update-student', {
    id: selectedRequest.value.id,
    changes: updatedChanges
  });

  Object.assign(selectedRequest.value, updatedChanges);

  if (!silent) {
    toast.add({
      severity: 'success',
      summary: 'Modifications enregistrées',
      detail: 'Les détails de la convention ont été mis à jour avec succès.',
      life: 3000
    });
  }
};

const approveRequest = () => {
  // Save edits first
  saveEdits(true);

  const assignedTutorName = getSelectedTutorName() || 'Non affecté';
  const assignedTutorIri = typeof tutorSelect.value === 'object' && tutorSelect.value ? tutorSelect.value.iri : undefined;

  emit('update-student', {
    id: selectedRequest.value.id,
    changes: {
      conventionStatus: 'Validée',
      tutor: assignedTutorName,
      tutorIri: assignedTutorIri
    }
  });

  selectedRequest.value.conventionStatus = 'Validée';
  selectedRequest.value.tutor = assignedTutorName;
  if (assignedTutorIri) {
    selectedRequest.value.tutorIri = assignedTutorIri;
  }

  toast.add({
    severity: 'success',
    summary: 'Convention Validée',
    detail: `La demande de ${selectedRequest.value.studentName} a été approuvée. Tuteur : ${assignedTutorName}.`,
    life: 4000
  });
  showReviewDialog.value = false;
};

const rejectRequest = () => {
  if (!rejectReason.value.trim()) {
    toast.add({ severity: 'warn', summary: 'Erreur', detail: 'Veuillez saisir un motif de rejet.', life: 3000 });
    return;
  }

  emit('update-student', {
    id: selectedRequest.value.id,
    changes: {
      conventionStatus: 'Rejetée',
      motifRefus: rejectReason.value.trim()
    }
  });

  selectedRequest.value.conventionStatus = 'Rejetée';

  toast.add({
    severity: 'error',
    summary: 'Convention Rejetée',
    detail: `La demande de ${selectedRequest.value.studentName} a été rejetée. Motif : ${rejectReason.value}`,
    life: 4000
  });
  showReviewDialog.value = false;
};

const resetStudentSubmission = async () => {
  if (await confirmer({
    message: `Réinitialiser la saisie de ${selectedRequest.value.studentName} ? Toutes ses données saisies seront effacées, mais il pourra de nouveau saisir sa demande immédiatement.`,
    titre: 'Réinitialiser la saisie', libelle: 'Réinitialiser', danger: true
  })) {
    emit('update-student', {
      id: selectedRequest.value.id,
      changes: {
        hasStage: false,
        company: '-',
        siret: '',
        dates: '-',
        subject: '-',
        activities: '',
        supervisor: '-',
        salary: '-',
        conventionStatus: 'Aucune',
        tutor: 'Non affecté',
        inputAuthorized: true, // Keep authorized
        reportUploaded: false,
        reportName: '',
        studentPhone: '',
        studentEmail: '',
        insuranceCompany: '',
        insurancePolicyNumber: '',
        companyPhone: '',
        companyAddress: { adresse: '', complement1: '', complement2: '', ville: '', codePostal: '', pays: 'France' },
        signatoryCivilite: 'M',
        signatoryPrenom: '',
        signatoryNom: '',
        signatoryTitle: '',
        signatoryEmail: '',
        signatoryPhone: '',
        tuteurSameAsSignatory: false,
        supervisorCivilite: 'M',
        supervisorPrenom: '',
        supervisorNom: '',
        supervisorFunction: '',
        supervisorEmail: '',
        supervisorPhone: '',
        startDate: '',
        endDate: '',
        weeklyHours: 35,
        salaryAmount: 4.35,
        amenagementStage: ''
      }
    });

    toast.add({
      severity: 'info',
      summary: 'Saisie réinitialisée',
      detail: `La saisie de ${selectedRequest.value.studentName} a été vidée. La saisie reste autorisée.`,
      life: 3500
    });
    showReviewDialog.value = false;
  }
};

const deleteStudentSubmission = async () => {
  if (await confirmer({
    message: `Supprimer définitivement la demande de convention de ${selectedRequest.value.studentName} ? Cela effacera ses données et bloquera son droit de saisie.`,
    titre: 'Supprimer la demande', libelle: 'Supprimer', danger: true
  })) {
    emit('update-student', {
      id: selectedRequest.value.id,
      changes: {
        hasStage: false,
        company: '-',
        siret: '',
        dates: '-',
        subject: '-',
        activities: '',
        supervisor: '-',
        salary: '-',
        conventionStatus: 'Aucune',
        tutor: 'Non affecté',
        inputAuthorized: false, // Revoke authorization
        reportUploaded: false,
        reportName: '',
        studentPhone: '',
        studentEmail: '',
        insuranceCompany: '',
        insurancePolicyNumber: '',
        companyPhone: '',
        companyAddress: { adresse: '', complement1: '', complement2: '', ville: '', codePostal: '', pays: 'France' },
        signatoryCivilite: 'M',
        signatoryPrenom: '',
        signatoryNom: '',
        signatoryTitle: '',
        signatoryEmail: '',
        signatoryPhone: '',
        tuteurSameAsSignatory: false,
        supervisorCivilite: 'M',
        supervisorPrenom: '',
        supervisorNom: '',
        supervisorFunction: '',
        supervisorEmail: '',
        supervisorPhone: '',
        startDate: '',
        endDate: '',
        weeklyHours: 35,
        salaryAmount: 4.35,
        amenagementStage: ''
      }
    });

    toast.add({
      severity: 'warn',
      summary: 'Demande supprimée',
      detail: `La demande de ${selectedRequest.value.studentName} a été supprimée et la saisie a été bloquée.`,
      life: 3500
    });
    showReviewDialog.value = false;
  }
};

const openConventionPdf = (studentOrId) => {
  const id = typeof studentOrId === 'object' ? (studentOrId.stageId || studentOrId.id) : studentOrId;
  if (id) {
    window.open(`/api/stage_etudiants/${id}/pdf`, '_blank');
  }
};

const generatePDF = () => {
  if (selectedRequest.value && (selectedRequest.value.stageId || selectedRequest.value.id)) {
    openConventionPdf(selectedRequest.value);
  }
  emit('update-student', {
    id: selectedRequest.value.id,
    changes: {
      conventionStatus: 'En cours de signature'
    }
  });
  selectedRequest.value.conventionStatus = 'En cours de signature';
  toast.add({
    severity: 'success',
    summary: 'PDF Généré',
    detail: `La convention PDF pour ${selectedRequest.value.studentName} a été générée et envoyée en signature.`,
    life: 3500
  });
};

const assignTutorDirectly = (student, newTutor) => {
  const tutorName = typeof newTutor === 'object' && newTutor ? newTutor.fullName : (newTutor || 'Non affecté');
  const tutorIri = typeof newTutor === 'object' && newTutor ? newTutor.iri : undefined;

  student.tutor = tutorName;
  if (tutorIri !== undefined) {
    student.tutorIri = tutorIri;
  }

  emit('update-student', {
    id: student.id,
    changes: {
      tutor: tutorName,
      tutorIri: tutorIri
    }
  });

  toast.add({
    severity: 'success',
    summary: 'Tuteur affecté',
    detail: `Tuteur de ${student.studentName} mis à jour : ${tutorName}.`,
    life: 3000
  });
};

const contactStudent = (studentName) => {
  toast.add({
    severity: 'info',
    summary: 'Rappel envoyé',
    detail: `Un e-mail de rappel de recherche de stage a été envoyé à ${studentName}.`,
    life: 3500
  });
};

const toggleInputAuthorization = (student) => {
  const isAuthNow = !student.inputAuthorized;
  emit('update-student', {
    id: student.id,
    changes: {
      inputAuthorized: isAuthNow
    }
  });

  toast.add({
    severity: isAuthNow ? 'success' : 'info',
    summary: isAuthNow ? 'Saisie autorisée' : 'Saisie bloquée',
    detail: `Le droit de saisie pour ${student.studentName} a été ${isAuthNow ? 'ouvert' : 'verrouillé'}.`,
    life: 3000
  });
};

const getInitials = (name) => {
  if (!name) return '';
  return name.split(' ').map(n => n[0]).join('');
};

// Pipeline selection filters
const studentsToValidate = computed(() => {
  return props.periodStudents.filter(s => s.hasStage && s.conventionStatus === 'En attente');
});

const studentsToPrint = computed(() => {
  return props.periodStudents.filter(s => s.hasStage && s.conventionStatus === 'Validée');
});

const studentsSigning = computed(() => {
  return props.periodStudents.filter(s => s.hasStage && s.conventionStatus === 'En cours de signature');
});

const studentsSigned = computed(() => {
  return props.periodStudents.filter(s => s.hasStage && s.conventionStatus === 'Signée');
});

const printConvention = (student) => {
  if (student && (student.stageId || student.id)) {
    openConventionPdf(student);
  }
  emit('update-student', {
    id: student.id,
    changes: {
      conventionStatus: 'En cours de signature'
    }
  });
  student.conventionStatus = 'En cours de signature';
  toast.add({
    severity: 'info',
    summary: 'PDF Imprimé',
    detail: `La convention pour ${student.studentName} a été générée/imprimée et envoyée en signature.`,
    life: 3500
  });
};

const finalizeSignature = (student) => {
  emit('update-student', {
    id: student.id,
    changes: {
      conventionStatus: 'Signée'
    }
  });
  student.conventionStatus = 'Signée';
  toast.add({
    severity: 'success',
    summary: 'Signature Finalisée',
    detail: `La convention signée pour ${student.studentName} a été récupérée avec succès.`,
    life: 3500
  });
};
</script>

<template>
  <div class="space-y-4">
    <!-- Dynamic KPIs for the selected Period -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
      <!-- KPI 1: Effectif Total -->
       <Kpi
        :value="kpis.total"
        label="Effectif Total"
        :icon="UserGroupIcon"
        color="slate"
        />

      <!-- KPI 2: Placed Count -->
       <Kpi
        :value="`${(kpis.placed / kpis.total * 100).toFixed(0)}%`"
        label="Taux de Placement"
        :icon="ChartBarIcon"
        color="emerald"
        />

      <!-- KPI 3: Placement Rate -->
       <Kpi
       :value="kpis.placed"
       label="Etudiant placés"
       :icon="UserGroupIcon"
       color="orange"
       />

      <!-- KPI 4: Pending Conventions -->
      <Kpi
        :value="kpis.pending"
        label="À Valider"
        :icon="ClockIcon"
        color="yellow"
      />

      <!-- KPI 5: Reports Deposited -->
      <Kpi
        :value="kpis.reports"
        label="Rapports reçus"
        :icon="DocumentIcon"
        color="purple"
      />
    </div>

    <!-- Pipeline de Suivi des Conventions -->
    <Card
      title="Suivi du flux des conventions"
      subtitle="Cycle de vie de la validation à la signature finale. Traitez les dossiers en un clic."
      :icon="ArrowPathIcon"
      iconClass="text-primary "
      iconBgClass="bg-emphasis  border-primary "
      bodyClass="space-y-4"
    >

      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Etape 1: A Valider -->
        <div class="bg-emphasis  p-4 rounded-2xl border-t-4 border-t-amber-500 border-x border-b border-surface  flex flex-col h-full min-h-[160px]">
          <div class="flex items-center justify-between pb-3 border-b border-surface ">
            <span class="font-bold text-[11px] text-color  flex items-center gap-1.5">
              <ClockIcon class="w-3.5 h-3.5 text-muted-color shrink-0" />
              <span>1. À Valider</span>
            </span>
            <span class="bg-emphasis  text-color  text-[10px] px-2 py-0.5 rounded-full font-bold">
              {{ studentsToValidate.length }}
            </span>
          </div>
          <div class="flex-1 mt-3 max-h-56 overflow-y-auto space-y-2 pr-1">
            <div v-for="s in studentsToValidate" :key="s.id" class="flex items-center justify-between p-2.5 rounded-xl bg-surface-0  border border-surface  shadow-sm transition-all hover:border-primary">
              <div class="flex flex-col min-w-0">
                <span class="font-bold text-xs truncate">{{ s.studentName }}</span>
                <span class="text-[9px] text-muted-color truncate">{{ s.company }}</span>
              </div>
              <button @click="openReviewRequest(s)" class="p-1.5 bg-emphasis  text-muted-color  hover:bg-emphasis hover:text-color rounded-lg transition-all cursor-pointer" v-tooltip.left="'Ouvrir la relecture'">
                <EyeIcon class="w-3.5 h-3.5" />
              </button>
            </div>
            <EmptyState v-if="studentsToValidate.length === 0" compact title="Aucun dossier à valider" />
          </div>
        </div>

        <!-- Etape 2: A Imprimer -->
        <div class="bg-emphasis  p-4 rounded-2xl border-t-4 border-t-blue-500 border-x border-b border-surface  flex flex-col h-full min-h-[160px]">
          <div class="flex items-center justify-between pb-3 border-b border-surface ">
            <span class="font-bold text-[11px] text-color  flex items-center gap-1.5">
              <PrinterIcon class="w-3.5 h-3.5 text-primary shrink-0" />
              <span>2. À Imprimer</span>
            </span>
            <span class="bg-emphasis  text-primary  text-[10px] px-2 py-0.5 rounded-full font-bold">
              {{ studentsToPrint.length }}
            </span>
          </div>
          <div class="flex-1 mt-3 max-h-56 overflow-y-auto space-y-2 pr-1">
            <div v-for="s in studentsToPrint" :key="s.id" class="flex items-center justify-between p-2.5 rounded-xl bg-surface-0  border border-surface  shadow-sm transition-all hover:border-primary">
              <div class="flex flex-col min-w-0">
                <span class="font-bold text-xs truncate">{{ s.studentName }}</span>
                <span class="text-[9px] text-muted-color truncate">{{ s.company }}</span>
              </div>
              <button @click="printConvention(s)" class="p-1.5 bg-emphasis  text-primary  hover:bg-emphasis hover:text-primary rounded-lg transition-all cursor-pointer" v-tooltip.left="'Générer PDF &amp; envoyer en signature'">
                <PrinterIcon class="w-3.5 h-3.5" />
              </button>
            </div>
            <EmptyState v-if="studentsToPrint.length === 0" compact title="Aucune convention à imprimer" />
          </div>
        </div>

        <!-- Etape 3: En cours de signature -->
        <div class="bg-emphasis  p-4 rounded-2xl border-t-4 border-t-indigo-500 border-x border-b border-surface  flex flex-col h-full min-h-[160px]">
          <div class="flex items-center justify-between pb-3 border-b border-surface ">
            <span class="font-bold text-[11px] text-color  flex items-center gap-1.5">
              <PencilIcon class="w-3.5 h-3.5 text-primary shrink-0" />
              <span>3. En Signature</span>
            </span>
            <span class="bg-emphasis  text-primary  text-[10px] px-2 py-0.5 rounded-full font-bold">
              {{ studentsSigning.length }}
            </span>
          </div>
          <div class="flex-1 mt-3 max-h-56 overflow-y-auto space-y-2 pr-1">
            <div v-for="s in studentsSigning" :key="s.id" class="flex items-center justify-between p-2.5 rounded-xl bg-surface-0  border border-surface  shadow-sm transition-all hover:border-primary">
              <div class="flex flex-col min-w-0">
                <span class="font-bold text-xs truncate">{{ s.studentName }}</span>
                <span class="text-[9px] text-muted-color truncate">{{ s.company }}</span>
              </div>
              <div class="flex items-center gap-1">
                <button @click="openConventionPdf(s)" class="p-1.5 bg-emphasis  text-muted-color  hover:bg-emphasis dark:hover:bg-emphasis rounded-lg transition-all cursor-pointer" v-tooltip.left="'Voir la convention PDF'">
                  <i class="pi pi-file-pdf text-[11px] text-muted-color"></i>
                </button>
                <button @click="finalizeSignature(s)" class="p-1.5 bg-emphasis  text-primary  hover:bg-emphasis hover:text-primary rounded-lg transition-all cursor-pointer" v-tooltip.left="'Marquer comme signée/récupérée'">
                  <CheckCircleIcon class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
            <EmptyState v-if="studentsSigning.length === 0" compact title="Aucune convention en cours" />
          </div>
        </div>

        <!-- Etape 4: Récupérée signée -->
        <div class="bg-emphasis  p-4 rounded-2xl border-t-4 border-t-emerald-500 border-x border-b border-surface  flex flex-col h-full min-h-[160px]">
          <div class="flex items-center justify-between pb-3 border-b border-surface ">
            <span class="font-bold text-[11px] text-color  flex items-center gap-1.5">
              <CheckCircleIcon class="w-3.5 h-3.5 text-muted-color shrink-0" />
              <span>4. Signée / Terminé</span>
            </span>
            <span class="bg-emphasis  text-color  text-[10px] px-2 py-0.5 rounded-full font-bold">
              {{ studentsSigned.length }}
            </span>
          </div>
          <div class="flex-1 mt-3 max-h-56 overflow-y-auto space-y-2 pr-1">
            <div v-for="s in studentsSigned" :key="s.id" class="flex items-center justify-between p-2.5 rounded-xl bg-surface-0  border border-surface  shadow-sm transition-all">
              <div class="flex flex-col min-w-0">
                <span class="font-bold text-xs truncate">{{ s.studentName }}</span>
                <span class="text-[9px] text-muted-color truncate">{{ s.company }}</span>
              </div>
              <div class="flex items-center gap-1">
                <button @click="openConventionPdf(s)" class="p-1.5 bg-emphasis  text-muted-color  hover:bg-emphasis dark:hover:bg-emphasis rounded-lg transition-all cursor-pointer" v-tooltip.left="'Télécharger le PDF'">
                  <i class="pi pi-file-pdf text-[11px] text-muted-color"></i>
                </button>
                <div class="p-1 text-muted-color ">
                  <CheckIcon class="w-3.5 h-3.5 text-muted-color" />
                </div>
              </div>
            </div>
            <EmptyState v-if="studentsSigned.length === 0" compact title="Aucune convention finalisée" />
          </div>
        </div>
      </div>
    </Card>

    <!-- Table List wrapped in Card -->
    <Card
      title="Liste des étudiants de la période"
      subtitle="Suivi de placement, tuteur universitaire et livrables."
      :icon="UsersIcon"
      iconClass="text-primary "
      iconBgClass="bg-emphasis  border-primary "
      :badge="'Période : ' + activePeriodName"
      badgeSeverity="info"
    >

      <DataTable
        v-model:filters="filters"
        :value="periodStudents"
        dataKey="id"
        filterDisplay="row"
        :globalFilterFields="['studentName', 'company', 'tutor']"
        paginator
        :rows="10"
        :rowsPerPageOptions="[10, 25, 50, 100]"
        paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
        currentPageReportTemplate="Affichage de {first} à {last} sur {totalRecords} étudiants"
        responsiveLayout="scroll"
        class="text-xs text-color "
        stripedRows
      >
        <template #header>
          <div class="flex justify-end p-2 bg-emphasis  border-b border-surface ">
            <IconField>
              <InputIcon>
                <MagnifyingGlassIcon class="w-4 h-4 text-muted-color" />
              </InputIcon>
              <InputText v-model="filters['global'].value" placeholder="Rechercher un étudiant, une entreprise..."
                class="py-1.5 px-3 text-xs rounded-lg shadow-sm border border-surface  focus:outline-none focus:ring-1 focus:ring-primary-500 w-64" />
            </IconField>
          </div>
        </template>

        <!-- Column 1: Student Identity -->
        <Column header="Étudiant" field="studentName" sortable :showFilterMenu="false" class="font-semibold text-color -contrast min-w-[180px]">
          <template #body="slotProps">
            <div class="flex items-center gap-3 py-1">
              <div
                class="w-8 h-8 rounded-lg bg-emphasis  text-primary  flex items-center justify-center font-bold text-xs">
                {{ getInitials(slotProps.data.studentName) }}
              </div>
              <div>
                <span class="font-bold text-sm block">{{ slotProps.data.studentName }}</span>
                <span class="text-[9px] text-muted-color block" v-if="slotProps.data.hasStage">Maître : {{ slotProps.data.supervisor }}</span>
              </div>
            </div>
          </template>
          <template #filter="{ filterModel, filterCallback }">
            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" placeholder="Filtrer..."
              class="w-full text-xs border border-surface  rounded p-1 px-2 focus:ring-1 focus:ring-primary-500 bg-surface-0  text-color " />
          </template>
        </Column>

        <!-- Column 2: Stage status -->
        <Column header="État Placement" field="company" sortable :showFilterMenu="false" class="min-w-[150px]">
          <template #body="slotProps">
            <div v-if="slotProps.data.hasStage" class="space-y-1">
              <span
                class="bg-emphasis  text-color  px-2 py-0.5 rounded font-bold text-[9px] uppercase tracking-wider inline-block">
                Placé
              </span>
              <span class="text-[10px] font-medium text-muted-color  block truncate max-w-[140px]">
                {{ slotProps.data.company }}
              </span>
            </div>
            <div v-else class="space-y-1">
              <span
                class="bg-emphasis  text-color  px-2 py-0.5 rounded font-bold text-[9px] uppercase tracking-wider inline-block">
                Sans Stage
              </span>
              <span class="text-[9px] text-muted-color block">Recherche active</span>
            </div>
          </template>
          <template #filter="{ filterModel, filterCallback }">
            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" placeholder="Filtrer..."
              class="w-full text-xs border border-surface  rounded p-1 px-2 focus:ring-1 focus:ring-primary-500 bg-surface-0  text-color " />
          </template>
        </Column>

        <!-- Column 3: Convention progress -->
        <Column header="Convention" field="conventionStatus" sortable :showFilterMenu="false" class="min-w-[120px]">
          <template #body="slotProps">
            <Tag v-if="tagStatut(slotProps.data.conventionStatus)" :value="tagStatut(slotProps.data.conventionStatus).libelle"
              :severity="tagStatut(slotProps.data.conventionStatus).severite" :icon="tagStatut(slotProps.data.conventionStatus).icone" />
            <div v-else>
              <!-- Toggle input authorization for student without stage -->
              <button
                @click="toggleInputAuthorization(slotProps.data)"
                :class="[
                  'px-2 py-0.5 rounded text-[9px] font-bold transition-all duration-200 flex items-center gap-1 cursor-pointer select-none border shadow-sm',
                  slotProps.data.inputAuthorized
                    ? 'bg-emphasis  text-color  border-surface  hover:bg-emphasis dark:hover:bg-emphasis'
                    : 'bg-emphasis  text-muted-color  border-surface  hover:bg-emphasis dark:hover:bg-emphasis'
                ]"
              >
                <i :class="['pi text-[8px]', slotProps.data.inputAuthorized ? 'pi-lock-open text-muted-color' : 'pi-lock text-muted-color']"></i>
                <span>{{ slotProps.data.inputAuthorized ? 'Saisie ouverte' : 'Saisie bloquée' }}</span>
              </button>
            </div>
          </template>
          <template #filter="{ filterModel, filterCallback }">
            <Select v-model="filterModel.value" @change="filterCallback()" :options="['En attente', 'Validée', 'En cours de signature', 'Signée', 'Rejetée']" placeholder="Tous" :showClear="true"
              class="w-full text-xs border border-surface  rounded focus:ring-1 focus:ring-primary-500 bg-surface-0  text-color " />
          </template>
        </Column>

        <!-- Column 4: Tutor assignment AutoComplete -->
        <Column header="Tuteur Universitaire" field="tutor" sortable :showFilterMenu="false" class="min-w-[200px]">
          <template #body="slotProps">
            <div v-if="slotProps.data.hasStage" class="w-full max-w-[210px]">
              <AutoComplete
                :modelValue="slotProps.data.tutor && slotProps.data.tutor !== 'Non affecté' ? slotProps.data.tutor : ''"
                :suggestions="filteredTutors"
                @complete="searchTutors"
                optionLabel="fullName"
                placeholder="Assigner un tuteur..."
                dropdown
                class="w-full text-xs"
                inputClass="text-[11px] py-1 px-2 border border-surface  rounded-lg bg-emphasis  text-color  w-full focus:outline-none"
                scrollHeight="220px"
                appendTo="body"
                @item-select="(e) => assignTutorDirectly(slotProps.data, e.value)"
                @clear="assignTutorDirectly(slotProps.data, 'Non affecté')"
                :showClear="Boolean(slotProps.data.tutor && slotProps.data.tutor !== 'Non affecté')"
              >
                <template #option="{ option }">
                  <div class="flex flex-col py-0.5">
                    <span class="text-xs font-semibold text-color ">{{ option.fullName }}</span>
                    <span v-if="option.email" class="text-[9px] text-muted-color">{{ option.email }}</span>
                  </div>
                </template>
                <template #empty>
                  <EmptyState compact title="Aucun enseignant trouvé" />
                </template>
              </AutoComplete>
            </div>
            <span v-else class="text-[10px] text-muted-color italic">Sans stage</span>
          </template>
          <template #filter="{ filterModel, filterCallback }">
            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" placeholder="Filtrer..."
              class="w-full text-xs border border-surface  rounded p-1 px-2 focus:ring-1 focus:ring-primary-500 bg-surface-0  text-color " />
          </template>
        </Column>

        <!-- Column 5: Student Report uploaded -->
        <Column header="Rapport" field="reportUploaded" sortable :showFilterMenu="false" class="text-center min-w-[100px]">
          <template #body="slotProps">
            <a v-if="slotProps.data.reportUploaded" :href="`/api/stage_etudiants/${slotProps.data.id}/rapport`"
              class="bg-emphasis  text-primary  px-2.5 py-0.5 rounded-full font-bold text-[9px] flex items-center gap-1 w-max mx-auto hover:underline"
              v-tooltip="`Télécharger : ${slotProps.data.reportName}`">
              <i class="pi pi-download text-[8px]"></i>
              <span>Reçu</span>
            </a>
            <span v-else class="text-muted-color text-[10px]">-</span>
          </template>
          <template #filter="{ filterModel, filterCallback }">
            <Select v-model="filterModel.value" @change="filterCallback()" :options="[true, false]" placeholder="Tous" :showClear="true"
              class="w-full text-xs border border-surface  rounded focus:ring-1 focus:ring-primary-500 bg-surface-0  text-color ">
              <template #option="slotProps">
                <span>{{ slotProps.option ? 'Reçu' : 'Non reçu' }}</span>
              </template>
            </Select>
          </template>
        </Column>

        <!-- Column 6: Contextual Action Buttons -->
        <Column header="Actions" class="text-right min-w-[125px]">
          <template #body="slotProps">
            <!-- Case A: Convention needs validation -->
            <button v-if="slotProps.data.conventionStatus === 'En attente'" @click="openReviewRequest(slotProps.data)"
              class="text-[10px] font-bold px-3 py-1.5 bg-primary hover:bg-primary text-primary-contrast rounded-lg transition-all flex items-center gap-1.5 ml-auto cursor-pointer">
              <i class="pi pi-check text-[8px]"></i>
              <span>Valider</span>
            </button>

            <!-- Case B: Convention needs printing -->
            <button v-else-if="slotProps.data.conventionStatus === 'Validée'" @click="printConvention(slotProps.data)"
              class="text-[10px] font-bold px-3 py-1.5 bg-primary hover:bg-primary text-primary-contrast rounded-lg transition-all flex items-center gap-1.5 ml-auto cursor-pointer">
              <i class="pi pi-print text-[8px]"></i>
              <span>Imprimer</span>
            </button>

            <!-- Case C: Convention in signature -->
            <div v-else-if="slotProps.data.conventionStatus === 'En cours de signature'" class="flex items-center gap-1.5 ml-auto">
              <button @click="openConventionPdf(slotProps.data)"
                class="text-[10px] font-bold p-1.5 bg-emphasis hover:bg-emphasis  dark:hover:bg-emphasis text-color  rounded-lg transition-all flex items-center cursor-pointer"
                v-tooltip.top="'Télécharger PDF'">
                <i class="pi pi-file-pdf text-[10px] text-muted-color"></i>
              </button>
              <button @click="finalizeSignature(slotProps.data)"
                class="text-[10px] font-bold px-2.5 py-1.5 bg-primary hover:bg-primary text-primary-contrast rounded-lg transition-all flex items-center gap-1 cursor-pointer">
                <i class="pi pi-check-square text-[8px]"></i>
                <span>Finaliser</span>
              </button>
            </div>

            <!-- Case D: Student has no placement, send reminder -->
            <button v-else-if="!slotProps.data.hasStage" @click="contactStudent(slotProps.data.studentName)"
              class="text-[10px] font-bold px-3 py-1.5 bg-emphasis  hover:bg-emphasis dark:hover:bg-emphasis text-color  border border-surface  rounded-lg transition-all flex items-center gap-1.5 ml-auto cursor-pointer">
              <i class="pi pi-envelope text-[8px]"></i>
              <span>Contacter</span>
            </button>

            <!-- Case E: Convention already signed / rejected, show detailed sheet -->
            <div v-else class="flex items-center gap-1.5 ml-auto">
              <button v-if="slotProps.data.hasStage && slotProps.data.conventionStatus === 'Signée'"
                @click="openConventionPdf(slotProps.data)"
                class="text-[10px] font-bold p-1.5 bg-emphasis hover:bg-emphasis  dark:hover:bg-emphasis text-color  rounded-lg transition-all flex items-center cursor-pointer"
                v-tooltip.top="'Télécharger PDF'">
                <i class="pi pi-file-pdf text-[10px] text-muted-color"></i>
              </button>
              <button @click="openReviewRequest(slotProps.data)"
                class="text-[10px] font-bold px-3 py-1.5 bg-emphasis  hover:bg-emphasis dark:hover:bg-emphasis text-color  rounded-lg transition-all flex items-center gap-1.5 cursor-pointer">
                <i class="pi pi-info-circle text-[8px]"></i>
                <span>Détails</span>
              </button>
            </div>
          </template>
        </Column>
      </DataTable>
    </Card>

    <!-- DIALOG: EXAMINER / VALIDATION FLOW & DETAIL SHEET -->
    <Dialog
      v-model:visible="showReviewDialog"
      modal
      :closable="false"
      :style="{ width: '90vw', maxWidth: '850px' }"
      class="p-dialog-clean"
    >
      <template #header>
        <DialogHeader
          :icon="DocumentCheckIcon"
          tone="teal"
          title="Instruction de la demande de convention"
          :subtitle="selectedRequest ? `Étudiant : ${selectedRequest.studentName}` : ''"
          @close="showReviewDialog = false"
        />
      </template>

      <div v-if="selectedRequest" class="space-y-6 pt-1 max-h-[70vh] overflow-y-auto pr-1">
        <!-- Relecture & Édition des informations de stage/convention -->
        <div v-if="selectedRequest.hasStage" class="space-y-4">
          <div class="flex items-center justify-between border-b border-surface  pb-3">
            <h4 class="text-xs font-bold uppercase tracking-wider text-color  flex items-center gap-1.5">
              <PencilSquareIcon class="w-4 h-4" />
              <span>Relecture et modification des données (Responsable)</span>
            </h4>
            <span :class="['px-2.5 py-0.5 text-xs font-bold rounded-full uppercase tracking-wider',
              selectedRequest.conventionStatus === 'Validée' ? 'bg-emphasis text-color  ' :
              selectedRequest.conventionStatus === 'Rejetée' ? 'bg-emphasis text-color  ' :
              'bg-emphasis text-color  '
            ]">
              Statut : {{ selectedRequest.conventionStatus }}
            </span>
          </div>

          <div class="space-y-4">
            <!-- Section 1 : Profil Étudiant & Sécurité sociale -->
            <FormSection
              :icon="UserIcon"
              tone="teal"
              title="1. Profil Étudiant & Sécurité sociale"
            >
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1">
                  <label class="q-label">Téléphone personnel</label>
                  <input v-model="editForm.studentPhone" type="text" class="q-input q-input-sm" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="q-label">E-mail personnel (de secours)</label>
                  <input v-model="editForm.studentEmail" type="email" class="q-input q-input-sm" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="q-label">Organisme de sécurité sociale</label>
                  <input v-model="editForm.insuranceCompany" type="text" class="q-input q-input-sm" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="q-label">Adresse de l'organisme</label>
                  <input v-model="editForm.insurancePolicyNumber" type="text" class="q-input q-input-sm" />
                </div>
              </div>
            </FormSection>

            <!-- Section 2 : Entreprise d'accueil -->
            <FormSection
              :icon="BuildingOffice2Icon"
              tone="teal"
              title="2. Entreprise d'accueil"
            >
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1">
                  <label class="q-label">Raison Sociale</label>
                  <input v-model="editForm.companyName" type="text" class="q-input q-input-sm" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="q-label">Numéro SIRET</label>
                  <input v-model="editForm.companySiret" type="text" class="q-input q-input-sm" />
                </div>
                <div class="flex flex-col gap-1 md:col-span-2">
                  <label class="q-label">Téléphone standard entreprise</label>
                  <input v-model="editForm.companyPhone" type="text" class="q-input q-input-sm" />
                </div>

                <!-- Adresse de l'entreprise -->
                <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-3 bg-surface-0  p-3 rounded-xl border border-surface  shadow-2xs">
                  <div class="flex flex-col gap-1 md:col-span-3">
                    <label class="q-label">Adresse (Rue, Avenue...)</label>
                    <input v-model="editForm.companyAddress.adresse" type="text" class="q-input q-input-sm" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <label class="q-label">Code Postal</label>
                    <input v-model="editForm.companyAddress.codePostal" type="text" class="q-input q-input-sm" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <label class="q-label">Ville</label>
                    <input v-model="editForm.companyAddress.ville" type="text" class="q-input q-input-sm" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <label class="q-label">Pays</label>
                    <input v-model="editForm.companyAddress.pays" type="text" class="q-input q-input-sm" />
                  </div>
                </div>
              </div>
            </FormSection>

            <!-- Section 3 : Représentant Légal (Signataire) -->
            <FormSection
              :icon="IdentificationIcon"
              tone="indigo"
              title="3. Représentant Légal (Signataire de la convention)"
            >
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="flex flex-col gap-1">
                  <label class="q-label">Civilité</label>
                  <select v-model="editForm.signatoryCivilite" class="q-input q-input-sm">
                    <option value="M">Monsieur (M.)</option>
                    <option value="Mme">Madame (Mme)</option>
                  </select>
                </div>
                <div class="flex flex-col gap-1">
                  <label class="q-label">Prénom</label>
                  <input v-model="editForm.signatoryPrenom" type="text" class="q-input q-input-sm" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="q-label">Nom</label>
                  <input v-model="editForm.signatoryNom" type="text" class="q-input q-input-sm" />
                </div>
                <div class="flex flex-col gap-1 md:col-span-3">
                  <label class="q-label">Fonction / Titre</label>
                  <input v-model="editForm.signatoryTitle" type="text" class="q-input q-input-sm" />
                </div>
                <div class="flex flex-col gap-1 md:col-span-2">
                  <label class="q-label">E-mail direct</label>
                  <input v-model="editForm.signatoryEmail" type="email" class="q-input q-input-sm" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="q-label">Téléphone direct</label>
                  <input v-model="editForm.signatoryPhone" type="text" class="q-input q-input-sm" />
                </div>
              </div>
            </FormSection>

            <!-- Section 4 : Maître de Stage (Tuteur entreprise) -->
            <FormSection
              :icon="UserIcon"
              tone="purple"
              title="4. Maître de Stage (Tuteur entreprise)"
            >
              <!-- Checkbox tuteurSameAsSignatory -->
              <label class="q-choice mb-3">
                <input type="checkbox" v-model="editForm.tuteurSameAsSignatory" class="q-check mt-0.5" />
                <div class="flex-1 min-w-0">
                  <span class="q-choice-title">Le maître de stage est identique au signataire</span>
                  <p class="q-choice-desc">Cochez cette case pour synchroniser automatiquement les coordonnées.</p>
                </div>
              </label>

              <!-- Champs du tuteur, visibles si différent -->
              <div v-if="!editForm.tuteurSameAsSignatory" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="flex flex-col gap-1">
                  <label class="q-label">Civilité</label>
                  <select v-model="editForm.supervisorCivilite" class="q-input q-input-sm">
                    <option value="M">Monsieur (M.)</option>
                    <option value="Mme">Madame (Mme)</option>
                  </select>
                </div>
                <div class="flex flex-col gap-1">
                  <label class="q-label">Prénom</label>
                  <input v-model="editForm.supervisorPrenom" type="text" class="q-input q-input-sm" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="q-label">Nom</label>
                  <input v-model="editForm.supervisorNom" type="text" class="q-input q-input-sm" />
                </div>
                <div class="flex flex-col gap-1 md:col-span-3">
                  <label class="q-label">Fonction</label>
                  <input v-model="editForm.supervisorFunction" type="text" class="q-input q-input-sm" />
                </div>
                <div class="flex flex-col gap-1 md:col-span-2">
                  <label class="q-label">E-mail</label>
                  <input v-model="editForm.supervisorEmail" type="email" class="q-input q-input-sm" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="q-label">Téléphone</label>
                  <input v-model="editForm.supervisorPhone" type="text" class="q-input q-input-sm" />
                </div>
              </div>
              <div v-else class="q-callout q-callout-info">
                <i class="pi pi-info-circle text-xs mr-1"></i>
                <span>Le maître de stage est identique au signataire de la convention.</span>
              </div>
            </FormSection>

            <!-- Section 5 : Modalités & Mission -->
            <FormSection
              :icon="BriefcaseIcon"
              tone="teal"
              title="5. Modalités & Mission"
            >
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1">
                  <label class="q-label">Date de début</label>
                  <input v-model="editForm.startDate" type="date" class="q-input q-input-sm" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="q-label">Date de fin</label>
                  <input v-model="editForm.endDate" type="date" class="q-input q-input-sm" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="q-label">Volume horaire hebdomadaire</label>
                  <input v-model="editForm.weeklyHours" type="number" class="q-input q-input-sm" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="q-label">Gratification horaire nette (€/h)</label>
                  <input v-model="editForm.salaryAmount" type="number" step="0.01" class="q-input q-input-sm" />
                </div>
                <div class="flex flex-col gap-1 md:col-span-2">
                  <label class="q-label">Tuteur Universitaire (IUT)</label>
                  <AutoComplete
                    v-model="tutorSelect"
                    :suggestions="dialogTutorSuggestions"
                    @complete="searchDialogTutors"
                    optionLabel="fullName"
                    placeholder="Taper un nom ou choisir un tuteur..."
                    dropdown
                    class="w-full text-xs"
                    inputClass="q-input q-input-sm w-full"
                    scrollHeight="220px"
                    appendTo="body"
                    :showClear="true"
                  >
                    <template #option="{ option }">
                      <div class="flex items-center justify-between py-0.5">
                        <span class="font-medium text-xs">{{ option.fullName }}</span>
                        <span v-if="option.email" class="text-[10px] text-muted-color ml-2">{{ option.email }}</span>
                      </div>
                    </template>
                    <template #empty>
                      <EmptyState compact title="Aucun enseignant trouvé" />
                    </template>
                  </AutoComplete>
                </div>
                <div class="flex flex-col gap-1 md:col-span-2">
                  <label class="q-label">Sujet du stage / Mission principale</label>
                  <input v-model="editForm.subject" type="text" class="q-input q-input-sm" />
                </div>
                <div class="flex flex-col gap-1 md:col-span-2">
                  <label class="q-label">Détail des activités confiées</label>
                  <textarea v-model="editForm.activities" rows="3" class="q-input q-input-sm"></textarea>
                </div>
                <div class="flex flex-col gap-1 md:col-span-2">
                  <label class="q-label">Aménagements éventuels (ex: Télétravail...)</label>
                  <input v-model="editForm.amenagementStage" type="text" class="q-input q-input-sm" />
                </div>
              </div>
            </FormSection>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button @click="saveEdits(false)" class="px-4 py-2 bg-emphasis  hover:bg-emphasis text-primary-contrast font-bold rounded-xl text-xs flex items-center gap-1.5 transition-all shadow-xs cursor-pointer">
              <i class="pi pi-save"></i>
              <span>Enregistrer les corrections</span>
            </button>
          </div>
        </div>

        <!-- If student doesn't have a stage yet (Case B / active search) -->
        <div v-else class="space-y-4">
          <div class="bg-emphasis  border border-surface  rounded-2xl p-6 text-center">
            <div class="w-12 h-12 bg-emphasis  text-muted-color rounded-full flex items-center justify-center mx-auto mb-3">
              <i class="pi pi-search text-xl"></i>
            </div>
            <h4 class="text-sm font-bold text-color ">Étudiant en recherche active de stage</h4>
            <p class="text-xs text-muted-color  mt-2 max-w-md mx-auto leading-relaxed">
              Cet étudiant ({{ selectedRequest.studentName }}) n'a pas encore déclaré d'entreprise ni saisi ses informations de convention.
            </p>

            <div class="mt-6 flex flex-col sm:flex-row justify-center items-center gap-4">
              <div class="flex items-center gap-3">
                <span class="text-xs font-bold text-muted-color ">Droit de saisie étudiant :</span>
                <button
                  @click="toggleInputAuthorization(selectedRequest)"
                  :class="[
                    'px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 border shadow-sm cursor-pointer',
                    selectedRequest.inputAuthorized
                      ? 'bg-emphasis  text-color  border-surface  hover:bg-emphasis'
                      : 'bg-emphasis text-color   border-surface  hover:bg-emphasis'
                  ]"
                >
                  <i :class="['pi text-xs', selectedRequest.inputAuthorized ? 'pi-lock-open' : 'pi-lock']"></i>
                  <span>{{ selectedRequest.inputAuthorized ? 'Autorisée' : 'Bloquée' }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Évaluation par le tuteur en entreprise (convention en signature ou signée) -->
      <div v-if="selectedRequest && ['En cours de signature', 'Signée'].includes(selectedRequest.conventionStatus)"
        class="mt-4 p-4 rounded-2xl border border-surface  space-y-2">
        <h4 class="text-xs font-extrabold uppercase tracking-wider text-color ">Évaluation par l'entreprise</h4>
        <EvaluationEntreprisePanel :stage-id="selectedRequest.id" />
      </div>

      <!-- Footing buttons -->
      <template #footer>
        <div class="border-t border-surface  pt-4 flex flex-wrap items-center justify-between gap-3 w-full">
          <!-- Left side: Reset / Delete (Danger Zone) -->
          <div v-if="selectedRequest && selectedRequest.hasStage" class="flex gap-2">
            <button @click="resetStudentSubmission" class="px-3.5 py-2 border border-surface  hover:bg-emphasis dark:hover:bg-emphasis text-color  font-bold rounded-xl text-[11px] transition-all flex items-center gap-1.5 cursor-pointer">
              <i class="pi pi-refresh"></i>
              <span>Réinitialiser la saisie</span>
            </button>
            <button @click="deleteStudentSubmission" class="px-3.5 py-2 border border-surface  hover:bg-emphasis dark:hover:bg-emphasis text-muted-color  font-bold rounded-xl text-[11px] transition-all flex items-center gap-1.5 cursor-pointer">
              <i class="pi pi-trash"></i>
              <span>Supprimer la demande</span>
            </button>
          </div>
          <div v-else></div>

          <!-- Right side: Validate / Reject / Generate PDF / Close -->
          <div class="flex items-center gap-2 ml-auto">
            <!-- If validated, show Generate PDF button -->
            <button v-if="selectedRequest && selectedRequest.hasStage && selectedRequest.conventionStatus === 'Validée'" @click="generatePDF" class="px-4 py-2 bg-emphasis hover:bg-emphasis text-primary-contrast font-bold rounded-xl text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-xs">
              <i class="pi pi-file-pdf"></i>
              <span>Générer la convention PDF</span>
            </button>

            <!-- If in signature or signed, show View PDF button -->
            <button v-if="selectedRequest && selectedRequest.hasStage && ['En cours de signature', 'Signée'].includes(selectedRequest.conventionStatus)" @click="openConventionPdf(selectedRequest)" class="px-3.5 py-2 bg-emphasis hover:bg-emphasis  dark:hover:bg-emphasis text-color  font-bold rounded-xl text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-xs">
              <i class="pi pi-file-pdf text-muted-color"></i>
              <span>Télécharger la convention PDF</span>
            </button>

            <!-- If in signature, show Finalize signature button -->
            <button v-if="selectedRequest && selectedRequest.hasStage && selectedRequest.conventionStatus === 'En cours de signature'" @click="finalizeSignature(selectedRequest); showReviewDialog = false" class="px-4 py-2 bg-emphasis hover:bg-emphasis text-primary-contrast font-bold rounded-xl text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-xs">
              <i class="pi pi-check-square"></i>
              <span>Finaliser la signature</span>
            </button>

            <!-- Validate / Reject (Only if status is En attente) -->
            <div v-if="selectedRequest && selectedRequest.hasStage && selectedRequest.conventionStatus === 'En attente'" class="flex items-center gap-2">
              <button @click="showReviewDialog = false" class="px-4 py-2 bg-emphasis hover:bg-emphasis  dark:hover:bg-emphasis text-color  font-bold rounded-xl text-xs transition-all cursor-pointer">
                Annuler
              </button>

              <!-- Rejection input context -->
              <div class="flex items-center gap-2 border-l border-surface  pl-3">
                <input v-model="rejectReason" type="text" placeholder="Motif de rejet..." class="p-2 text-xs border border-surface  rounded-lg bg-emphasis  text-color  focus:outline-none w-[140px]" />
                <button @click="rejectRequest" class="px-3.5 py-2 bg-emphasis hover:bg-emphasis text-primary-contrast font-bold rounded-xl text-xs transition-all flex items-center gap-1 cursor-pointer shadow-xs">
                  <i class="pi pi-times"></i>
                  <span>Rejeter</span>
                </button>
              </div>

              <button @click="approveRequest" class="px-4 py-2 bg-emphasis hover:bg-emphasis text-primary-contrast font-bold rounded-xl text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-xs">
                <i class="pi pi-check"></i>
                <span>Valider la convention</span>
              </button>
            </div>

            <!-- Standard Close Button -->
            <button v-else @click="showReviewDialog = false" class="px-4 py-2 bg-emphasis hover:bg-emphasis  dark:hover:bg-emphasis text-color  font-bold rounded-xl text-xs transition-all cursor-pointer">
              Fermer
            </button>
          </div>
        </div>
      </template>
    </Dialog>
  </div>
</template>
