<script setup>
import { useVersion } from '@helpers/useVersion.js';

const { version } = useVersion();

</script>

<template>
  <div class="flex flex-wrap justify-between items-center gap-x-4 gap-y-2 border-t pt-6">
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
      <span class="font-bold">UniServices</span>
      <span>Copyright &copy; 2026</span>
      <span><a class="underline touch-target" href="">@David Annebicque</a></span>
      <span>-</span>
      <span><a class="underline touch-target" href="@mailto">@Cyndel Herolt</a></span>
      <span>All rights reserved</span>
      <span>|</span>
      <span>Version {{ version }}</span>
    </div>

    <div>
      <span>Contact : <a href="mailto:intranet.iut-troyes@univ-reims.fr" class="underline touch-target">intranet.iut-troyes@univ-reims.fr</a></span>
    </div>
  </div>
</template>
