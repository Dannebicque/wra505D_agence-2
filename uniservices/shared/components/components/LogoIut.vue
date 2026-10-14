<script setup>
// Le dessin de logo_intranet_iut_troyes.svg, en ligne : une image ne lit pas les variables CSS,
// alors que le violet de la DA devient sa nuance 400 en mode sombre.
</script>

<template>
  <svg class="logo-iut" viewBox="0 0 500 500" aria-hidden="true" focusable="false">
    <path
        class="logo-iut-accent"
        d="M441.7,145.4v27.3c0,4.8-3.9,8.7-8.7,8.7h-29.5v84.5c1.9,81.1-133.1,84-137.5,2.4l-0.1-36.4c15.6,0.9,32.7-7.5,44.7-21.3v43.9c0,35.4,48.2,35.4,48.2,0l-0.1-73.1h-26.4c-4.8,0-8.7-3.9-8.7-8.7v-27.3c0-4.8,3.9-8.7,8.7-8.7H433C437.8,136.7,441.7,140.6,441.7,145.4z"
    />
    <path
        class="logo-iut-primaire"
        d="M310.6,206.2v4.9c-12,13.8-29.1,22-44.7,21.1v-14.6c0-35.3-48.1-35.3-48.2,0.1l0.1,48.4c2,81.9-135.6,84-137.6,0V196c6.5,3.8,14.1,6,22.3,6s15.8-2.2,22.4-6v58.6c-0.1,35.4,48.1,35.4,48.2,0l-0.1-48.4C171.1,124.2,308.6,122.1,310.6,206.2z"
    />
    <circle class="logo-iut-primaire" cx="101.8" cy="159.9" r="25" />
    <circle class="logo-iut-accent" cx="241.9" cy="221.8" r="12.8" />
  </svg>
</template>

<style scoped>
.logo-iut-primaire {
  fill: var(--primary-color);
}

.logo-iut-accent {
  fill: var(--accent-color);
}
</style>
