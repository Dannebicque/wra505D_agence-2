<template>
  <div>
    <!--
      Lien d'evitement : il ouvre la page au clavier et permet de sauter la
      navigation, repetee sur chaque ecran.
    -->
    <a class="evitement" href="#contenu">Aller au contenu</a>

    <AppEnTete />

    <main id="contenu" tabindex="-1">
      <slot />
    </main>
  </div>
</template>

<style scoped>
.evitement {
  position: absolute;
  left: -9999px;
  top: 0;
  padding: 0.75rem 1rem;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
  border-radius: 0 0 var(--rayon) 0;
  z-index: 10;
}

.evitement:focus {
  left: 0;
}

main:focus {
  outline: none;
}
</style>
