<script setup>
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
      <p class="vacances__titre">Vacances !</p>
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
  .vacances__confetti {
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

.vacances__titre {
  @apply m-0 text-3xl font-bold text-primary;
}

.vacances__texte {
  @apply m-0 text-color;
}
</style>
