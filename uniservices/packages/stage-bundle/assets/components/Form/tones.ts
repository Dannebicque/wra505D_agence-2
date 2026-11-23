export type ToneColor = 'blue' | 'purple' | 'emerald' | 'amber' | 'teal' | 'indigo' | 'slate' | 'red';

export interface ToneClasses {
  container: string;
  icon: string;
}

export const TONE_CLASSES: Record<ToneColor, ToneClasses> = {
  blue: {
    container: 'bg-emphasis  border-primary ',
    icon: 'text-primary '
  },
  teal: {
    container: 'bg-emphasis  border-surface ',
    icon: 'text-muted-color '
  },
  purple: {
    container: 'bg-emphasis  border-primary ',
    icon: 'text-primary '
  },
  emerald: {
    container: 'bg-emphasis  border-surface ',
    icon: 'text-muted-color '
  },
  amber: {
    container: 'bg-emphasis  border-surface ',
    icon: 'text-muted-color '
  },
  indigo: {
    container: 'bg-emphasis  border-primary ',
    icon: 'text-primary '
  },
  slate: {
    container: 'bg-emphasis  border-surface ',
    icon: 'text-muted-color '
  },
  red: {
    container: 'bg-emphasis  border-surface ',
    icon: 'text-muted-color '
  }
};
