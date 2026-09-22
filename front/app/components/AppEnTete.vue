<script setup lang="ts">
const route = useRoute()
const { data: contexte } = await useContexte()

/*
 * Libelles du point de vue de l'etudiant. L'audit relevait que la navigation de
 * l'intranet nomme des objets administratifs, pas ce que l'etudiant cherche.
 */
const liens = [
  { chemin: '/', libelle: 'Mon portail' },
  { chemin: '/documents', libelle: 'Mes documents' },
  { chemin: '/profil', libelle: 'Mon profil' },
]

async function seDeconnecter() {
  await useApi().seDeconnecter()
  await navigateTo('/connexion')
}
</script>

<template>
  <header>
    <p class="marque">Espace etudiant</p>

    <nav aria-label="Navigation principale">
      <ul>
        <li v-for="lien in liens" :key="lien.chemin">
          <!--
            aria-current marque la page en cours : sans lui, la position n'est
            signalee que par la couleur, ce qui ne suffit pas.
          -->
          <NuxtLink
            :to="lien.chemin"
            :aria-current="route.path === lien.chemin ? 'page' : undefined"
          >
            {{ lien.libelle }}
          </NuxtLink>
        </li>
      </ul>
    </nav>

    <div class="compte">
      <span v-if="contexte">{{ contexte.user.prenom }} {{ contexte.user.nom }}</span>
      <AppBouton variante="secondaire" @click="seDeconnecter">Se deconnecter</AppBouton>
    </div>
  </header>
</template>

<style scoped>
header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 1rem 2rem;
  padding: 0.75rem 1rem;
  background: var(--couleur-surface);
  border-bottom: 1px solid var(--couleur-bordure);
}

.marque {
  margin: 0;
  font-weight: 700;
}

nav ul {
  display: flex;
  gap: 0.5rem;
  list-style: none;
  margin: 0;
  padding: 0;
}

nav a {
  display: flex;
  align-items: center;
  min-height: var(--cible-tactile);
  padding: 0 0.75rem;
  border-radius: var(--rayon);
  color: var(--couleur-texte);
  text-decoration: none;
}

nav a:hover {
  text-decoration: underline;
}

/* La page courante est soulignee en plus d'etre coloree. */
nav a[aria-current='page'] {
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
  font-weight: 500;
  text-decoration: underline;
}

.compte {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-left: auto;
}
</style>
