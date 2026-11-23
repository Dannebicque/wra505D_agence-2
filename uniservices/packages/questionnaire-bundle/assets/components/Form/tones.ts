export type FormTone = 'primary' | 'blue' | 'emerald' | 'purple' | 'amber' | 'rose' | 'slate';

/** Classes complètes (littérales) pour que Tailwind les détecte. */
export const toneClasses: Record<FormTone, string> = {
  primary: 'bg-primary-50 -950/60 -primary -400 border-primary-200 -800/60',
  blue: 'bg-emphasis  text-primary  border-primary ',
  emerald: 'bg-emphasis  text-color  border-surface ',
  purple: 'bg-emphasis  text-primary  border-primary ',
  amber: 'bg-emphasis  text-color  border-surface ',
  rose: 'bg-emphasis  text-color  border-surface ',
  slate: 'bg-emphasis  text-muted-color  border-surface '
};

/** Couleur d'icône seule (cartes de choix). */
export const toneIconClasses: Record<FormTone, string> = {
  primary: '-primary -400',
  blue: 'text-primary ',
  emerald: 'text-muted-color ',
  purple: 'text-primary ',
  amber: 'text-muted-color ',
  rose: 'text-muted-color ',
  slate: 'text-muted-color '
};

/** Fond/bordure d'une carte de choix active. */
export const toneActiveClasses: Record<FormTone, string> = {
  primary: '-primary -700/80 bg-primary-50/60 -950/30',
  blue: 'border-primary  bg-emphasis ',
  emerald: 'border-surface  bg-emphasis ',
  purple: 'border-primary  bg-emphasis ',
  amber: 'border-surface  bg-emphasis ',
  rose: 'border-surface  bg-emphasis ',
  slate: 'border-surface  bg-emphasis '
};
