<script setup>
import { computed } from 'vue';

const props = defineProps({
    commande: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['selectStep']);

const steps = computed(() => {
    const c = props.commande;
    const hasReception = c.receptions && c.receptions.length > 0;
    const rec = hasReception ? c.receptions[0] : null;

    const isStep1Done = c.avisDirecteur === 'FAVORABLE';
    const isStep2Done = isStep1Done && !!c.dateVerificationFinanciere;
    const isStep3Done = isStep2Done && !!c.dateVisaEngagement;
    const isStep4Done = isStep3Done && !!rec?.dateEffectiveReception;
    const isStep5Done = isStep4Done && (!!c.factureFinale || !!c.datePaiement || !!c.dateCertificationServiceFait);

    return [
        {
            id: '1',
            title: '1. Demande & Avis',
            desc: c.avisDirecteur === 'FAVORABLE'
                ? 'Avis favorable'
                : (c.avisDirecteur === 'DEFAVORABLE' ? 'Défavorable' : (c.pdfFilePath ? 'PDF signé déposé' : 'Demande en cours')),
            date: c.dateAvisDirecteur || c.dateDemande,
            completed: isStep1Done,
            active: !isStep1Done,
            icon: 'pi pi-file-edit',
            activeClass: 'bg-primary text-primary-contrast ring-4 ring-primary-100 dark:ring-primary-950',
            activeTextClass: 'text-primary',
        },
        {
            id: '2',
            title: '2. Vérification SIFAC',
            desc: c.numeroBonCommande ? `BC N° ${c.numeroBonCommande}` : (isStep1Done ? 'En attente vérification' : 'Verrouillé (Attente Étape 1)'),
            date: c.dateVerificationFinanciere,
            completed: isStep2Done,
            active: isStep1Done && !isStep2Done,
            locked: !isStep1Done,
            icon: 'pi pi-search',
            activeClass: 'bg-primary text-primary-contrast ring-4 ring-primary-100 dark:ring-primary-950',
            activeTextClass: 'text-primary',
        },
        {
            id: '3',
            title: '3. Visa Engagement',
            desc: c.dateVisaEngagement ? 'Visa accordé' : (isStep2Done ? 'En attente visa CSA' : 'Verrouillé (Attente Étape 2)'),
            date: c.dateVisaEngagement,
            completed: isStep3Done,
            active: isStep2Done && !isStep3Done,
            locked: !isStep2Done,
            icon: 'pi pi-check-circle',
            activeClass: 'bg-primary text-primary-contrast ring-4 ring-primary-100 dark:ring-primary-950',
            activeTextClass: 'text-primary',
        },
        {
            id: '4',
            title: '4. Constatation SF',
            desc: rec?.dateEffectiveReception ? `Reçu le ${new Date(rec.dateEffectiveReception).toLocaleDateString('fr-FR')}` : (isStep3Done ? 'En attente réception' : 'Verrouillé (Attente Étape 3)'),
            date: rec?.dateEffectiveReception,
            completed: isStep4Done,
            active: isStep3Done && !isStep4Done,
            locked: !isStep3Done,
            icon: 'pi pi-box',
            activeClass: 'bg-primary text-primary-contrast ring-4 ring-primary-100 dark:ring-primary-950',
            activeTextClass: 'text-primary',
        },
        {
            id: '5',
            title: '5. Facture & Paiement',
            desc: c.factureFinale ? 'Facture soldée' : (isStep4Done ? 'En cours de règlement' : 'Verrouillé (Attente Étape 4)'),
            date: c.datePaiement || c.dateCertificationServiceFait,
            completed: isStep5Done,
            active: isStep4Done && !isStep5Done,
            locked: !isStep4Done,
            icon: 'pi pi-wallet',
            activeClass: 'bg-primary text-primary-contrast ring-4 ring-primary-100 dark:ring-primary-950',
            activeTextClass: 'text-primary',
        },
    ];
});
</script>

<template>
    <div class="workflow-stepper py-2">
        <div class="flex items-center justify-between relative">
            <div class="absolute top-1/2 left-0 right-0 h-1 bg-emphasis -translate-y-1/2 z-0"></div>

            <div
                v-for="(step, idx) in steps"
                :key="step.id"
                class="flex flex-col items-center relative z-10 text-center flex-1 cursor-pointer select-none group"
                @click="emit('selectStep', step.id)"
            >
                <div
                    class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all shadow-sm"
                    :class="[
                        step.completed
                            ? 'bg-primary text-primary-contrast ring-4 ring-primary-100 dark:ring-primary-950'
                            : step.active
                            ? (step.activeClass + ' animate-pulse')
                            : step.locked
                            ? 'bg-emphasis text-muted-color border border-surface'
                            : 'bg-surface-0 dark:bg-surface-900 text-muted-color border-2 border-surface'
                    ]"
                >
                    <i aria-hidden="true" v-if="step.completed" class="pi pi-check text-base"></i>
                    <i aria-hidden="true" v-else-if="step.locked" class="pi pi-lock text-sm"></i>
                    <i aria-hidden="true" v-else :class="step.icon"></i>
                </div>

                <div class="mt-2">
                    <div
                        class="text-xs font-bold group-hover:underline"
                        :class="step.completed ? 'text-primary' : (step.active ? step.activeTextClass : 'text-muted-color')"
                    >
                        {{ step.title }}
                    </div>
                    <div class="text-[11px] text-muted-color max-w-[130px] truncate mx-auto" :title="step.desc">
                        {{ step.desc }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.workflow-stepper {
    width: 100%;
}
</style>
