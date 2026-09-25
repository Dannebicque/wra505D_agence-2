<script setup>
const contentId = 'contenu-principal';

// Le focus est posé à la main : une ancre relancerait la navigation du routeur et ses gardes.
const goToContent = () => {
  const content = document.getElementById(contentId);
  if (!content) return;
  content.setAttribute('tabindex', '-1');
  content.focus();
  content.addEventListener('blur', () => content.removeAttribute('tabindex'), { once: true });
};
</script>

<template>
  <a :href="`#${contentId}`" class="skip-link" @click.prevent="goToContent">Aller au contenu</a>
</template>

<style scoped>
/* Couleurs de la DA, indépendantes de la primaire du module : texte 5,29:1 sur le fond. */
.skip-link {
  position: fixed;
  top: 0.5rem;
  left: 0.5rem;
  z-index: 1000;
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 0 1rem;
  border: 2px solid var(--accent-contrast-color);
  border-radius: 6px;
  background-color: var(--accent-color);
  color: var(--accent-contrast-color);
  font-weight: 700;
  text-decoration: underline;
  transform: translateY(calc(-100% - 1rem));
}

.skip-link:focus {
  transform: none;
  outline: 2px solid var(--accent-contrast-color);
  outline-offset: 2px;
}

:global(#contenu-principal:focus) {
  outline: none;
}
</style>
