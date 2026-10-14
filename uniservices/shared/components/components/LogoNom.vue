<script setup>
// Le mot est tracé au trait, comme le logo : un seul trait épais aux bouts ronds, sans police.
// Chaque lettre est décalée de sa largeur plus l'espace voulu ; la ligne de base est à 25.
const lettres = [
  { x: 0, traits: ['M0 9V17A8 8 0 0 0 16 17', 'M16 9V25'] },
  { x: 24.5, traits: ['M0 9V25', 'M0 17A8 8 0 0 1 16 17V25'] },
  { x: 49, traits: ['M0 9V25'], point: [0, 2.5] },
  { x: 57.5, traits: ['M4 3V19Q4 25 10 25H12', 'M0 9H11'] },
  { x: 77, traits: ['M0 9V25', 'M0 18C0 12 4 9 9 9'] },
  { x: 94.5, traits: ['M16 9V25', 'M16 17A8 8 0 1 0 0 17A8 8 0 1 0 16 17'] },
  { x: 119, traits: ['M0 9V25', 'M0 17A8 8 0 0 1 16 17V25'] },
  { x: 143.5, traits: ['M0 17H16A8 8 0 1 0 13.66 22.66'] },
  { x: 168, traits: ['M4 3V19Q4 25 10 25H12', 'M0 9H11'] },
];

let rang = 0;
const formes = lettres.flatMap(({ x, traits, point }) => [
  ...traits.map((d) => ({ d, x, rang: rang++ })),
  ...(point ? [{ point, x, rang: rang++ }] : []),
]);
</script>

<template>
  <svg
      class="logo-nom"
      viewBox="-3 -1 185 30"
      aria-hidden="true"
      focusable="false"
      :style="{ '--formes': formes.length - 1 }"
  >
    <template v-for="forme in formes" :key="forme.rang">
      <circle
          v-if="forme.point"
          class="logo-nom-point"
          :cx="forme.point[0] + forme.x"
          :cy="forme.point[1]"
          r="2.75"
          :style="{ '--rang': forme.rang }"
      />
      <path
          v-else
          class="logo-nom-trait"
          :d="forme.d"
          :transform="`translate(${forme.x} 0)`"
          pathLength="1"
          :style="{ '--rang': forme.rang }"
      />
    </template>
  </svg>
</template>
