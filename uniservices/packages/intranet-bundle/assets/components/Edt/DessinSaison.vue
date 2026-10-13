<script setup>
import {useId} from 'vue';

// Plusieurs dessins peuvent cohabiter dans la page : chaque découpe a son propre identifiant.
const id = useId();

defineProps({
  // toussaint, noel, hiver, printemps ou ete
  saison: {
    type: String,
    required: true
  }
});
</script>

<template>
  <!-- Les couleurs viennent des jetons du thème : les dessins suivent le mode clair et le sombre. -->
  <svg class="dessin-saison" viewBox="0 0 80 80" aria-hidden="true">
    <g v-if="saison === 'toussaint'">
      <defs>
        <clipPath :id="`${id}-citrouille`">
          <ellipse cx="25" cy="48" rx="15" ry="22" />
          <ellipse cx="55" cy="48" rx="15" ry="22" />
          <ellipse cx="40" cy="48" rx="17" ry="25" />
        </clipPath>
      </defs>
      <path d="M40 18 q3 -10 13 -9" fill="none" stroke="var(--p-seance-tp-pill-background)" stroke-width="3" stroke-linecap="round" />
      <path d="M53 9 q9 -3 13 4 q-9 3 -13 -4z" fill="var(--p-seance-tp-pill-background)" />
      <rect x="37" y="14" width="6" height="11" rx="3" fill="var(--p-seance-td-pill-background)" />
      <ellipse cx="25" cy="48" rx="15" ry="22" fill="var(--p-accent-color)" />
      <ellipse cx="55" cy="48" rx="15" ry="22" fill="var(--p-accent-color)" />
      <ellipse cx="40" cy="48" rx="17" ry="25" fill="var(--p-accent-color)" />
      <g :clip-path="`url(#${id}-citrouille)`" fill="none" stroke="var(--p-seance-td-pill-background)" stroke-width="1.5" opacity="0.35">
        <path d="M31 20 q-7 28 0 56" />
        <path d="M49 20 q7 28 0 56" />
      </g>
      <path d="M28 41 l6 -8 6 8z M40 41 l6 -8 6 8z" fill="var(--p-primary-color)" />
      <path d="M37.5 48 l2.5 -3.5 2.5 3.5z" fill="var(--p-primary-color)" />
      <path d="M27 53 Q40 68 53 53 Q40 59 27 53z" fill="var(--p-primary-color)" />
      <rect x="34" y="55" width="3.5" height="3.5" fill="var(--p-accent-color)" />
      <rect x="43" y="55" width="3.5" height="3.5" fill="var(--p-accent-color)" />
    </g>

    <g v-else-if="saison === 'noel'">
      <defs>
        <clipPath :id="`${id}-sapin`">
          <path d="M40 12 L55 32 H48 L61 48 H53 L67 66 H13 L27 48 H19 L32 32 H25z" />
        </clipPath>
      </defs>
      <rect x="35" y="64" width="10" height="10" rx="2" fill="var(--p-seance-td-pill-background)" />
      <path d="M40 12 L55 32 H48 L61 48 H53 L67 66 H13 L27 48 H19 L32 32 H25z" fill="var(--p-seance-tp-pill-background)" stroke="var(--p-seance-tp-pill-background)" stroke-width="2" stroke-linejoin="round" />
      <g :clip-path="`url(#${id}-sapin)`" fill="none" stroke="var(--p-accent-color)" stroke-width="2.5" stroke-linecap="round">
        <path d="M18 37 Q40 50 62 33" />
        <path d="M10 54 Q40 70 70 50" />
      </g>
      <circle cx="35" cy="30" r="2.8" fill="var(--p-seance-evaluation-pill-background)" />
      <circle cx="46" cy="43" r="2.8" fill="var(--p-seance-sae-pill-background)" />
      <circle cx="31" cy="50" r="2.8" fill="var(--p-primary-color)" />
      <circle cx="51" cy="60" r="2.8" fill="var(--p-seance-evaluation-pill-background)" />
      <circle cx="28" cy="62" r="2.8" fill="var(--p-accent-color)" />
      <path d="M40 2 l2.6 5.4 5.9 0.7 -4.4 4 1.2 5.9 -5.3 -3 -5.3 3 1.2 -5.9 -4.4 -4 5.9 -0.7z" fill="var(--p-accent-color)" />
    </g>

    <g v-else-if="saison === 'hiver'">
      <circle cx="40" cy="56" r="17" fill="color-mix(in srgb, var(--p-primary-color) 10%, var(--p-content-background))" stroke="var(--p-primary-color)" stroke-width="2" />
      <circle cx="40" cy="30" r="12" fill="color-mix(in srgb, var(--p-primary-color) 10%, var(--p-content-background))" stroke="var(--p-primary-color)" stroke-width="2" />
      <rect x="29" y="14" width="22" height="4" rx="2" fill="var(--p-primary-color)" />
      <rect x="33" y="4" width="14" height="12" rx="2" fill="var(--p-primary-color)" />
      <path d="M28 40 q12 6 24 0 l2 5 q-14 5 -28 0z" fill="var(--p-accent-color)" />
      <rect x="45" y="42" width="5" height="12" rx="2" fill="var(--p-accent-color)" />
      <circle cx="36" cy="28" r="1.8" fill="var(--p-primary-color)" />
      <circle cx="44" cy="28" r="1.8" fill="var(--p-primary-color)" />
      <path d="M40 31 l8 2 -8 2z" fill="var(--p-seance-td-pill-background)" />
      <circle cx="40" cy="54" r="1.8" fill="var(--p-primary-color)" />
      <circle cx="40" cy="62" r="1.8" fill="var(--p-primary-color)" />
    </g>

    <g v-else-if="saison === 'printemps'">
      <path d="M40 76 V40" stroke="var(--p-seance-tp-pill-background)" stroke-width="4" stroke-linecap="round" />
      <path d="M40 62 q14 -10 22 -2 q-12 10 -22 2z M40 54 q-14 -8 -20 0 q10 8 20 0z" fill="var(--p-seance-tp-pill-background)" />
      <path d="M62 66 V52" stroke="var(--p-seance-tp-pill-background)" stroke-width="3" stroke-linecap="round" />
      <ellipse cx="62" cy="48" rx="5" ry="7" fill="var(--p-seance-sae-pill-background)" />
      <g fill="var(--p-seance-sae-pill-background)">
        <ellipse cx="40" cy="14" rx="7" ry="10" />
        <ellipse cx="40" cy="14" rx="7" ry="10" transform="rotate(60 40 26)" />
        <ellipse cx="40" cy="14" rx="7" ry="10" transform="rotate(120 40 26)" />
        <ellipse cx="40" cy="14" rx="7" ry="10" transform="rotate(180 40 26)" />
        <ellipse cx="40" cy="14" rx="7" ry="10" transform="rotate(240 40 26)" />
        <ellipse cx="40" cy="14" rx="7" ry="10" transform="rotate(300 40 26)" />
      </g>
      <circle cx="40" cy="26" r="8" fill="var(--p-accent-color)" />
      <circle cx="37" cy="24" r="1.3" fill="var(--p-seance-td-pill-background)" />
      <circle cx="43" cy="25" r="1.3" fill="var(--p-seance-td-pill-background)" />
      <circle cx="40" cy="29" r="1.3" fill="var(--p-seance-td-pill-background)" />
    </g>

    <g v-else>
      <g stroke="var(--p-accent-color)" stroke-width="4" stroke-linecap="round">
        <path d="M40 4 V14 M40 66 V76 M4 40 H14 M66 40 H76" />
        <path d="M15 15 L21 21 M59 59 L65 65 M15 65 L21 59 M59 21 L65 15" stroke-width="3" />
      </g>
      <circle cx="40" cy="40" r="20" fill="var(--p-accent-color)" />
      <path d="M24 36 h32" stroke="var(--p-primary-color)" stroke-width="2" />
      <rect x="25" y="33" width="13" height="9" rx="4" fill="var(--p-primary-color)" />
      <rect x="42" y="33" width="13" height="9" rx="4" fill="var(--p-primary-color)" />
      <path d="M32 49 q8 7 16 0" fill="none" stroke="var(--p-primary-color)" stroke-width="3" stroke-linecap="round" />
      <circle cx="28" cy="48" r="2.5" fill="var(--p-seance-sae-pill-background)" opacity="0.6" />
      <circle cx="52" cy="48" r="2.5" fill="var(--p-seance-sae-pill-background)" opacity="0.6" />
    </g>
  </svg>
</template>
