<script setup lang="ts">
import type { NuxtError } from '#app'

const props = defineProps<{ error: NuxtError }>()

/*
 * Un seul ecran pour les trois cas de leur application : /404, /500 et /access.
 * En Nuxt il attrape aussi les erreurs reelles du serveur, pas seulement les
 * navigations vers ces adresses.
 */
const cas = computed(() => {
  switch (props.error.statusCode) {
    case 403:
      return {
        titre: 'Acces refuse',
        explication:
          "Vous n'avez pas les droits necessaires pour consulter cette page. Si vous pensez qu'il s'agit d'une erreur, ecrivez au support.",
      }
    case 404:
      return {
        titre: 'Page introuvable',
        explication:
          'Cette adresse ne correspond a aucune page. Elle a peut-etre ete deplacee, ou le lien que vous avez suivi est errone.',
      }
    default:
      return {
        titre: 'Le service est momentanement indisponible',
        explication:
          'Une erreur est survenue de notre cote. Reessayez dans quelques instants, et prevenez le support si cela persiste.',
      }
  }
})

useHead({ title: cas.value.titre })

function revenirAuPortail() {
  /* clearError vide l'erreur avant de naviguer, sinon l'ecran resterait affiche. */
  return clearError({ redirect: '/' })
}
</script>

<template>
  <main class="erreur">
    <p class="code">Erreur {{ error.statusCode }}</p>
    <h1>{{ cas.titre }}</h1>
    <p>{{ cas.explication }}</p>

    <div class="actions">
      <AppBouton @click="revenirAuPortail">Revenir a mon portail</AppBouton>
      <a href="mailto:intranet.iut-troyes@univ-reims.fr">Ecrire au support</a>
    </div>
  </main>
</template>

<style scoped>
.erreur {
  max-width: 40rem;
  margin: 0 auto;
  padding: 4rem 1rem;
}

.code {
  margin: 0;
  color: var(--couleur-texte-attenue);
  font-weight: 500;
}

h1 {
  margin: 0.25rem 0 1rem;
}

p {
  margin: 0 0 1.5rem;
}

.actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 1rem;
}
</style>
