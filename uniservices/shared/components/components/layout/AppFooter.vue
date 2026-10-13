<script setup>
import { useVersion } from '@helpers/useVersion.js';

const { version } = useVersion();

const refonte = [
  { nom: 'Lucas Corrieras', site: 'https://www.lucascorrieras.com/' },
  { nom: 'Lou Calmes', site: 'https://lou-calmes.fr/' },
  { nom: 'Jeremy Horde', site: 'https://www.studiojrmh.fr/' },
];
</script>

<template>
  <!-- Des liens pris dans une phrase : WCAG 2.2 (2.5.8) leur demande 24 px, pas les 44 px d'un
       bouton, qui étiraient chaque ligne du pied de page sur téléphone. -->
  <footer class="pied-de-page">
    <p>
      <span class="font-bold">UniServices</span> © 2026 ·
      <a href="">David Annebicque</a> et <a href="@mailto">Cyndel Herolt</a> ·
      refonte de l'espace étudiant par
      <template v-for="(personne, index) in refonte" :key="personne.nom">
        <a :href="personne.site" target="_blank" rel="noopener">{{ personne.nom }}<span class="sr-only"> (nouvel onglet)</span></a>{{ index < refonte.length - 2 ? ', ' : index === refonte.length - 2 ? ' et ' : '' }}
      </template>
      · Version {{ version }}
    </p>
    <p>
      Contact : <a href="mailto:intranet.iut-troyes@univ-reims.fr">intranet.iut-troyes@univ-reims.fr</a>
    </p>
  </footer>
</template>

<style scoped>
@reference "../../../../packages/auth-bundle/assets/assets/tailwind.css";

.pied-de-page {
  @apply flex flex-wrap justify-between items-baseline gap-x-6 gap-y-1 border-t pt-3 text-xs text-muted-color;
  p {
    @apply m-0;
  }
  a {
    @apply inline-block py-1 underline text-color;
  }
}
</style>
