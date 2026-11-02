/**
 * Métadonnées des applications : nom, libellé court, icône.
 * Une seule couleur pour toutes (A6) : un badge d'application prend le style du thème,
 * l'application se reconnaît à son nom et à son icône.
 */

export const APPLICATION_REGISTRY = {
  intranet: {
    slug: 'intranet',
    name: 'Intranet',
    shortLabel: 'Intranet',
    icon: 'pi pi-desktop',
    isDefault: true,
  },
  stage: {
    slug: 'stage',
    name: 'Stage/Alternance',
    shortLabel: 'Stages',
    icon: 'pi pi-briefcase',
    isDefault: false,
  },
  questionnaire: {
    slug: 'questionnaire',
    name: 'Questionnaires',
    shortLabel: 'Questionnaires',
    icon: 'pi pi-check-square',
    isDefault: false,
  },
  documents: {
    slug: 'documents',
    name: 'Documents',
    shortLabel: 'Documents',
    icon: 'pi pi-folder',
    isDefault: false,
  },
  helpdesk: {
    slug: 'helpdesk',
    name: 'HelpDesk',
    shortLabel: 'HelpDesk',
    icon: 'pi pi-question-circle',
    isDefault: false,
  },
  unifolio: {
    slug: 'unifolio',
    name: 'UniFolio',
    shortLabel: 'UniFolio',
    icon: 'pi pi-book',
    isDefault: false,
  },
  finance: {
    slug: 'finance',
    name: 'Finances & Achats',
    shortLabel: 'Finance',
    icon: 'pi pi-wallet',
    isDefault: false,
  },
  auth: {
    slug: 'auth',
    name: 'Mon Espace / Portail',
    shortLabel: 'Portail',
    icon: 'pi pi-user',
    isDefault: false,
  },
  correcto: {
    slug: 'correcto',
    name: 'Correcto',
    shortLabel: 'Correcto',
    icon: 'pi pi-pencil',
    isDefault: false,
  },
  message: {
    slug: 'message',
    name: 'Messagerie',
    shortLabel: 'Messages',
    icon: 'pi pi-inbox',
    isDefault: false,
  },
};

/**
 * Normalise any slug or app name variation to the canonical registry key.
 */
export function normalizeAppSlug(appInput) {
  if (!appInput) return '';
  const raw = typeof appInput === 'object'
    ? (appInput.urlSlug || appInput.slug || appInput.name || '')
    : String(appInput);

  const clean = raw.trim().toLowerCase();

  const aliases = {
    intranet: 'intranet',
    stages: 'stage',
    questionnaires: 'questionnaire',
    document: 'documents',
    finances: 'finance',
    portail: 'auth',
    'help-desk': 'helpdesk',
    portfolio: 'unifolio',
    portfolios: 'unifolio',
    messages: 'message',
    messagerie: 'message',
  };

  return aliases[clean] || clean;
}

/**
 * Métadonnées d'une application, ou d'une application inconnue à partir de son nom.
 */
export function getApplicationMeta(appInput) {
  const slug = normalizeAppSlug(appInput);
  if (slug && APPLICATION_REGISTRY[slug]) {
    return APPLICATION_REGISTRY[slug];
  }

  // Fallback for unknown / generic tools
  const rawLabel = typeof appInput === 'object'
    ? (appInput.name || appInput.urlSlug || 'Application')
    : String(appInput || 'Application');

  const formattedLabel = rawLabel.charAt(0).toUpperCase() + rawLabel.slice(1);

  return {
    slug: slug || 'unknown',
    name: formattedLabel,
    shortLabel: formattedLabel,
    icon: 'pi pi-box',
    isDefault: false,
  };
}

/**
 * Libellé et icône d'une application, pour un Tag ou un badge.
 */
export function getAppTagInfo(appInput) {
  const meta = getApplicationMeta(appInput);
  return {
    label: meta.shortLabel || meta.name,
    icon: meta.icon,
  };
}
