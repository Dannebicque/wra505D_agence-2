<script setup>
import {computed} from 'vue';
import {saisonDeVacances} from '@/utils/agenda.js';
import DessinSaison from './DessinSaison.vue';

const props = defineProps({
  // Lundi de la semaine de vacances, AAAA-MM-JJ : il choisit la saison.
  lundi: {
    type: String,
    required: true
  }
});

const TITRES = {
  toussaint: 'Vacances de la Toussaint',
  noel: 'Vacances de Noël',
  hiver: 'Vacances d\'hiver',
  printemps: 'Vacances de printemps',
  ete: 'Vacances d\'été',
};

// Des bonbons à la Toussaint, des flocons à Noël et en hiver ; des confettis sinon.
const FORMES = {toussaint: 'bonbon', noel: 'flocon', hiver: 'flocon'};

const COULEURS = ['var(--p-primary-color)', 'var(--p-accent-color)', 'var(--p-seance-tp-pill-background)', 'var(--p-seance-sae-pill-background)'];
// La neige reste dans les violets de la DA.
const NEIGE = ['var(--p-primary-color)', 'color-mix(in srgb, var(--p-primary-color) 45%, var(--p-content-background))'];

const saison = computed(() => saisonDeVacances(props.lundi));
const theme = computed(() => FORMES[saison.value] ?? 'confetti');

// Un tirage pseudo-aléatoire fixé par le rang : les confettis semblent jetés au hasard, mais la
// page reste la même d'un rendu à l'autre.
const tirage = (rang, graine) => {
  const x = Math.sin(rang * 12.9898 + graine * 78.233) * 43758.5453;
  return x - Math.floor(x);
};

const couleur = (teinte) => {
  const palette = theme.value === 'flocon' ? NEIGE : COULEURS;
  return palette[Math.floor(teinte * palette.length)];
};

const confettis = Array.from({length: 32}, (_, rang) => ({
  rang,
  style: {
    left: `${(tirage(rang, 1) * 98).toFixed(1)}%`,
    top: `${(tirage(rang, 2) * 90).toFixed(1)}%`,
    animationDelay: `-${(tirage(rang, 4) * 6).toFixed(2)}s`,
    animationDuration: `${(5 + tirage(rang, 5) * 3).toFixed(2)}s`,
  },
  teinte: tirage(rang, 3),
  rond: tirage(rang, 6) < 0.35,
}));
</script>

<template>
  <div class="vacances">
    <div class="vacances__confettis" aria-hidden="true">
      <span v-for="confetti in confettis" :key="confetti.rang" :class="['vacances__piece', `vacances__piece--${theme}`]" :style="{ ...confetti.style, color: couleur(confetti.teinte) }">
        <svg v-if="theme === 'bonbon'" viewBox="0 0 24 12">
          <path d="M0 1 L6 6 0 11z M24 1 L18 6 24 11z" fill="currentColor" opacity="0.7" />
          <ellipse cx="12" cy="6" rx="7" ry="5" fill="currentColor" />
        </svg>
        <svg v-else-if="theme === 'flocon'" viewBox="0 0 20 20">
          <path d="M10 1 V19 M2.2 5.5 L17.8 14.5 M2.2 14.5 L17.8 5.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
        </svg>
        <span v-else :class="['vacances__confetti', { 'vacances__confetti--rond': confetti.rond }]"></span>
      </span>
    </div>
    <div class="vacances__carte">
      <DessinSaison :saison="saison" class="vacances__dessin" />
      <p class="vacances__titre">{{ TITRES[saison] }}</p>
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

.vacances__piece {
  @apply absolute block;
  opacity: 0.85;
  animation-name: tomber;
  animation-timing-function: linear;
  animation-iteration-count: infinite;
  svg {
    display: block;
    width: 100%;
    height: 100%;
  }
}

.vacances__piece--bonbon {
  width: 1.5rem;
  height: 0.75rem;
}

.vacances__piece--flocon {
  width: 1.125rem;
  height: 1.125rem;
}

.vacances__confetti {
  display: block;
  width: 0.5rem;
  height: 0.875rem;
  border-radius: 2px;
  background: currentColor;
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
  width: 5.5rem;
  height: 5.5rem;
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

/* Sans animation, confettis et dessin restent posés où leur rang les place. */
@media (prefers-reduced-motion: reduce) {
  .vacances__piece,
  .vacances__dessin {
    animation: none;
  }
}

.vacances__titre {
  @apply m-0 text-2xl font-bold text-primary;
}

.vacances__texte {
  @apply m-0 text-color;
}
</style>
