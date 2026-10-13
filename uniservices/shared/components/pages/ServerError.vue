<script setup>
import { ErrorView } from '@components';
import { lastServerErrorDebugMessage } from '@helpers/serverError.js';

const debugMessage = lastServerErrorDebugMessage(window.sessionStorage);
</script>

<template>
  <main id="contenu-principal" class="flex flex-col items-center justify-center w-full h-screen px-4">
    <div class="w-full max-w-4xl">
      <ErrorView :debug-message="debugMessage" />
    </div>
  </main>
</template>
