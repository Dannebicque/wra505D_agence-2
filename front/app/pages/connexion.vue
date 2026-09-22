<script setup lang="ts">
const api = useApi()

useHead({ title: 'Connexion' })

const identifiant = ref('')
const motDePasse = ref('')
const erreurGenerale = ref('')
const erreurs = reactive<{ identifiant: string | null; motDePasse: string | null }>({
  identifiant: null,
  motDePasse: null,
})
const enCours = ref(false)

function valider() {
  erreurs.identifiant = identifiant.value ? null : 'Saisissez votre login.'
  erreurs.motDePasse = motDePasse.value ? null : 'Saisissez votre mot de passe.'
  return !erreurs.identifiant && !erreurs.motDePasse
}

async function soumettre() {
  erreurGenerale.value = ''
  if (!valider()) return

  enCours.value = true
  try {
    await api.seConnecter(identifiant.value, motDePasse.value)
    await navigateTo('/')
  } catch (erreur) {
    // L'API repond 401 sur un couple invalide, et rien d'autre n'est exploitable
    // par l'utilisateur : on ne detaille pas les autres codes.
    const statut = (erreur as { statusCode?: number }).statusCode
    erreurGenerale.value =
      statut === 401
        ? 'Login ou mot de passe incorrect.'
        : 'La connexion a echoue. Contactez le support si cela se reproduit.'
  } finally {
    enCours.value = false
  }
}
</script>

<template>
  <main class="page">
    <section class="presentation">
      <h1>Espace etudiant</h1>
      <p>Vos documents, votre emploi du temps et vos notes, en un seul endroit.</p>
    </section>

    <section class="connexion" aria-labelledby="titre-connexion">
      <h2 id="titre-connexion">Connexion</h2>
      <p class="intro">
        Etudiants, personnels de l'Universite et vacataires, connectez-vous avec l'authentification
        de l'Universite.
      </p>

      <form novalidate @submit.prevent="soumettre">
        <AppChampTexte
          v-model="identifiant"
          label="Login"
          autocomplete="username"
          requis
          :erreur="erreurs.identifiant"
        />

        <AppChampTexte
          v-model="motDePasse"
          label="Mot de passe"
          type="password"
          autocomplete="current-password"
          requis
          :erreur="erreurs.motDePasse"
        />

        <AppAlerte v-if="erreurGenerale">{{ erreurGenerale }}</AppAlerte>

        <AppBouton type="submit" pleine-largeur :aria-busy="enCours">
          {{ enCours ? 'Connexion en cours' : 'Se connecter' }}
        </AppBouton>
      </form>

      <p class="support">
        En cas de probleme de connexion, ecrivez au support :
        <a href="mailto:intranet.iut-troyes@univ-reims.fr">intranet.iut-troyes@univ-reims.fr</a>
      </p>
    </section>
  </main>
</template>

<style scoped>
.page {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1fr 1fr;
  align-items: center;
  gap: 2rem;
  padding: 2rem 1rem;
  max-width: 64rem;
  margin: 0 auto;
}

.presentation h1 {
  margin: 0 0 0.5rem;
}

.connexion {
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
  border-radius: var(--rayon);
  padding: 2rem;
}

.connexion h2 {
  margin: 0 0 0.5rem;
}

.intro {
  margin: 0 0 1.5rem;
  color: var(--couleur-texte-attenue);
}

form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.support {
  margin: 1.5rem 0 0;
  color: var(--couleur-texte-attenue);
}

@media (max-width: 48rem) {
  .page {
    grid-template-columns: 1fr;
  }
}
</style>
