<script setup>
import {computed} from 'vue';

const props = defineProps({
  // Lundi de la semaine de vacances, AAAA-MM-JJ : il choisit la saison.
  lundi: {
    type: String,
    required: true
  }
});

// Les vacances scolaires d'un IUT, par mois de début.
const SAISONS = {
  toussaint: {titre: 'Vacances de la Toussaint', mois: [9, 10]},
  noel: {titre: 'Vacances de Noël', mois: [11]},
  hiver: {titre: 'Vacances d\'hiver', mois: [0, 1, 2]},
  printemps: {titre: 'Vacances de printemps', mois: [3, 4]},
  ete: {titre: 'Vacances d\'été', mois: [5, 6, 7, 8]},
};

const saison = computed(() => {
  const mois = Number(props.lundi.split('-')[1]) - 1;
  return Object.keys(SAISONS).find(nom => SAISONS[nom].mois.includes(mois)) ?? 'ete';
});

const COULEURS = ['var(--p-primary-color)', 'var(--p-accent-color)', 'var(--p-seance-tp-pill-background)', 'var(--p-seance-sae-pill-background)'];

// Un tirage pseudo-aléatoire fixé par le rang : les confettis semblent jetés au hasard, mais la
// page reste la même d'un rendu à l'autre.
const tirage = (rang, graine) => {
  const x = Math.sin(rang * 12.9898 + graine * 78.233) * 43758.5453;
  return x - Math.floor(x);
};

const confettis = Array.from({length: 32}, (_, rang) => ({
  rang,
  style: {
    left: `${(tirage(rang, 1) * 98).toFixed(1)}%`,
    top: `${(tirage(rang, 2) * 90).toFixed(1)}%`,
    background: COULEURS[Math.floor(tirage(rang, 3) * COULEURS.length)],
    animationDelay: `-${(tirage(rang, 4) * 6).toFixed(2)}s`,
    animationDuration: `${(4 + tirage(rang, 5) * 3).toFixed(2)}s`,
  },
  forme: tirage(rang, 6) < 0.35 ? 'rond' : 'bande',
}));
</script>

<template>
  <div class="vacances">
    <div class="vacances__confettis" aria-hidden="true">
      <span v-for="confetti in confettis" :key="confetti.rang" :class="['vacances__confetti', `vacances__confetti--${confetti.forme}`]" :style="confetti.style"></span>
    </div>
    <div class="vacances__carte">
      <svg class="vacances__dessin" viewBox="0 0 80 80" aria-hidden="true">
        <g v-if="saison === 'toussaint'">
          <rect x="37" y="12" width="6" height="12" rx="3" fill="var(--p-seance-tp-pill-background)" />
          <ellipse cx="26" cy="46" rx="16" ry="22" fill="var(--p-accent-color)" />
          <ellipse cx="54" cy="46" rx="16" ry="22" fill="var(--p-accent-color)" />
          <ellipse cx="40" cy="46" rx="16" ry="24" fill="var(--p-accent-color)" />
          <path d="M28 40 l6 -8 6 8z M40 40 l6 -8 6 8z" fill="var(--p-primary-color)" />
          <path d="M26 54 q14 10 28 0 q-14 4 -28 0z" fill="var(--p-primary-color)" />
        </g>
        <g v-else-if="saison === 'noel'">
          <rect x="36" y="60" width="8" height="12" rx="2" fill="var(--p-primary-color)" />
          <path d="M40 18 L60 46 H20z" fill="var(--p-seance-tp-pill-background)" />
          <path d="M40 30 L64 62 H16z" fill="var(--p-seance-tp-pill-background)" />
          <circle cx="40" cy="14" r="6" fill="var(--p-accent-color)" />
          <circle cx="32" cy="48" r="3" fill="var(--p-seance-evaluation-pill-background)" />
          <circle cx="48" cy="56" r="3" fill="var(--p-accent-color)" />
          <circle cx="44" cy="40" r="3" fill="var(--p-seance-sae-pill-background)" />
        </g>
        <g v-else-if="saison === 'hiver'" stroke="var(--p-primary-color)" stroke-width="5" stroke-linecap="round">
          <path d="M40 10 V70 M14 25 L66 55 M14 55 L66 25" />
          <path d="M32 14 L40 22 48 14 M32 66 L40 58 48 66" fill="none" />
          <circle cx="40" cy="40" r="7" fill="var(--p-accent-color)" stroke="none" />
        </g>
        <g v-else-if="saison === 'printemps'">
          <rect x="37" y="44" width="6" height="30" rx="3" fill="var(--p-seance-tp-pill-background)" />
          <ellipse cx="52" cy="60" rx="10" ry="5" fill="var(--p-seance-tp-pill-background)" />
          <circle cx="40" cy="16" r="10" fill="var(--p-seance-sae-pill-background)" />
          <circle cx="54" cy="28" r="10" fill="var(--p-seance-sae-pill-background)" />
          <circle cx="48" cy="44" r="10" fill="var(--p-seance-sae-pill-background)" />
          <circle cx="32" cy="44" r="10" fill="var(--p-seance-sae-pill-background)" />
          <circle cx="26" cy="28" r="10" fill="var(--p-seance-sae-pill-background)" />
          <circle cx="40" cy="31" r="9" fill="var(--p-accent-color)" />
        </g>
        <g v-else>
          <g stroke="var(--p-accent-color)" stroke-width="5" stroke-linecap="round">
            <path d="M40 6 V16 M40 64 V74 M6 40 H16 M64 40 H74 M16 16 L23 23 M57 57 L64 64 M16 64 L23 57 M57 23 L64 16" />
          </g>
          <circle cx="40" cy="40" r="17" fill="var(--p-accent-color)" />
          <circle cx="34" cy="37" r="2.5" fill="var(--p-primary-color)" />
          <circle cx="46" cy="37" r="2.5" fill="var(--p-primary-color)" />
          <path d="M32 45 q8 7 16 0" fill="none" stroke="var(--p-primary-color)" stroke-width="3" stroke-linecap="round" />
        </g>
      </svg>
      <p class="vacances__titre">{{ SAISONS[saison].titre }}</p>
      <p class="vacances__texte">Pas de cours cette semaine. Profitez-en bien.</p>
    </div>
  </div>
</template>

<style scoped>
@reference "../../assets/tailwind.css";

.vacances {
  @apply absolute z-10 flex items-center justify-center overflow-hidden pointer-events-none;
}

.vacances__confettis {
  @apply absolute inset-0;
}

.vacances__confetti {
  @apply absolute;
  width: 0.5rem;
  height: 0.875rem;
  border-radius: 2px;
  opacity: 0.85;
  animation-name: tomber;
  animation-timing-function: linear;
  animation-iteration-count: infinite;
}

.vacances__confetti--rond {
  width: 0.625rem;
  height: 0.625rem;
  border-radius: 999px;
}

@keyframes tomber {
  from {
    transform: translateY(-120%) rotate(0deg);
    top: -5%;
  }
  to {
    transform: translateY(0) rotate(540deg);
    top: 105%;
  }
}

/* Sans animation, les confettis restent posés où leur rang les place. */
@media (prefers-reduced-motion: reduce) {
  .vacances__confetti,
  .vacances__dessin {
    animation: none;
  }
}

.vacances__carte {
  @apply relative flex flex-col items-center gap-1 text-center pointer-events-auto;
  padding: 1.75rem 2.5rem;
  border-radius: 28px;
  background:
      radial-gradient(circle 1.75rem at 0.75rem 0.75rem, color-mix(in srgb, var(--p-accent-color) 35%, var(--p-content-background)) calc(100% - 0.5px), transparent 100%),
      var(--p-content-background);
  box-shadow: 0 16px 40px -16px rgb(0 0 0 / 0.35);
}

.vacances__dessin {
  width: 5rem;
  height: 5rem;
  margin-bottom: 0.5rem;
  animation: balancer 3s ease-in-out infinite;
}

@keyframes balancer {
  0%, 100% {
    transform: rotate(-6deg);
  }
  50% {
    transform: rotate(6deg);
  }
}

.vacances__titre {
  @apply m-0 text-2xl font-bold text-primary;
}

.vacances__texte {
  @apply m-0 text-color;
}
</style>
