/**
 * Constantes et définitions unifiées du workflow Finance & Achats
 * Cohérence stricte : Étape (1 à 5), couleur sémantique, icône et libellés
 */

export const WORKFLOW_STEPS = [
    {
        id: '1',
        stepNumber: 1,
        title: '1. Demande & Avis',
        shortTitle: 'Demande & Avis',
        icon: 'pi pi-file-edit',
        desc: 'Demande initiale, signature externe et avis hiérarchique'
    },
    {
        id: '2',
        stepNumber: 2,
        title: '2. Vérification SIFAC',
        shortTitle: 'Vérification SIFAC',
        icon: 'pi pi-search',
        desc: 'Contrôle financier, fournisseur et N° BC SIFAC'
    },
    {
        id: '3',
        stepNumber: 3,
        title: '3. Visa Engagement',
        shortTitle: 'Visa Engagement',
        icon: 'pi pi-check-circle',
        desc: 'Visa CSA / Responsable financier autorisant la commande'
    },
    {
        id: '4',
        stepNumber: 4,
        title: '4. Constatation SF',
        shortTitle: 'Constatation SF',
        icon: 'pi pi-box',
        desc: 'Réception des biens / prestations et PV de service fait'
    },
    {
        id: '5',
        stepNumber: 5,
        title: '5. Facture & Paiement',
        shortTitle: 'Facture & Paiement',
        icon: 'pi pi-wallet',
        desc: 'Liquidation de la facture et mise en paiement'
    }
];

export const STATUS_WORKFLOW_MAP = {
    BROUILLON: {
        step: 1,
        stepLabel: 'Étape 1',
        label: '1. Brouillon',
        fullLabel: 'Étape 1 : Brouillon',
        severity: 'secondary',
        icon: 'pi pi-pencil',
    },
    SOUMIS: {
        step: 1,
        stepLabel: 'Étape 1',
        label: '1. Demande soumise',
        fullLabel: 'Étape 1 : Demande soumise',
        severity: 'info',
        icon: 'pi pi-send',
    },
    AVIS_DIRECTION: {
        step: 1,
        stepLabel: 'Étape 1',
        label: '1. Avis Direction',
        fullLabel: 'Étape 1 : Avis Direction',
        severity: 'info',
        icon: 'pi pi-compass',
    },
    VERIFICATION_FINANCIERE: {
        step: 2,
        stepLabel: 'Étape 2',
        label: '2. Vérif. SIFAC',
        fullLabel: 'Étape 2 : Vérification SIFAC',
        severity: 'warn',
        icon: 'pi pi-search',
    },
    VISA_ENGAGEMENT: {
        step: 3,
        stepLabel: 'Étape 3',
        label: '3. Visa Engagement',
        fullLabel: 'Étape 3 : Visa Engagement',
        severity: 'help',
        icon: 'pi pi-check-circle',
    },
    COMMANDE_VALIDEE: {
        step: 3,
        stepLabel: 'Étape 3',
        label: '3. Commande validée',
        fullLabel: 'Étape 3 : Commande validée',
        severity: 'help',
        icon: 'pi pi-shopping-cart',
    },
    COMMANDE_TRANSMISE: {
        step: 3,
        stepLabel: 'Étape 3',
        label: '3. BC transmis',
        fullLabel: 'Étape 3 : BC transmis fournisseur',
        severity: 'help',
        icon: 'pi pi-truck',
    },
    SERVICE_FAIT_CONSTATE: {
        step: 4,
        stepLabel: 'Étape 4',
        label: '4. Service Fait constaté',
        fullLabel: 'Étape 4 : Service Fait constaté',
        severity: 'success',
        icon: 'pi pi-box',
    },
    SERVICE_FAIT_CERTIFIE: {
        step: 4,
        stepLabel: 'Étape 4',
        label: '4. Service Fait certifié',
        fullLabel: 'Étape 4 : Service Fait certifié',
        severity: 'success',
        icon: 'pi pi-verified',
    },
    FACTURE_RECUE: {
        step: 5,
        stepLabel: 'Étape 5',
        label: '5. Facture reçue',
        fullLabel: 'Étape 5 : Facture reçue',
        severity: 'warn',
        icon: 'pi pi-receipt',
    },
    PAYE: {
        step: 5,
        stepLabel: 'Étape 5',
        label: '5. Payé & Soldé',
        fullLabel: 'Étape 5 : Payé & Soldé',
        severity: 'success',
        icon: 'pi pi-dollar',
    },
    CLOTURE: {
        step: 5,
        stepLabel: 'Étape 5',
        label: '5. Clôturé',
        fullLabel: 'Étape 5 : Clôturé',
        severity: 'secondary',
        icon: 'pi pi-lock',
    },
    REJETE: {
        step: -1,
        stepLabel: 'Rejeté',
        label: 'Rejeté',
        fullLabel: 'Demande rejetée',
        severity: 'danger',
        icon: 'pi pi-times-circle',
    },
};

export function getStatusBadgeInfo(statut) {
    return STATUS_WORKFLOW_MAP[statut] || {
        step: 1,
        stepLabel: 'Étape',
        label: statut || 'Inconnu',
        fullLabel: statut || 'Inconnu',
        severity: 'info',
        icon: 'pi pi-info-circle',
    };
}
