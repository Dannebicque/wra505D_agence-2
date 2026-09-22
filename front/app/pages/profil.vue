<script setup lang="ts">
const api = useApi()

useHead({ title: 'Mon profil' })

const { data: contexte } = await useContexte()

const { data: fiche } = await useAsyncData('fiche-etudiant', () =>
  contexte.value ? api.ficheEtudiant(contexte.value.user.id) : Promise.resolve(null),
)

const { data: scolarites } = await useAsyncData('scolarites', async () => {
  const { member } = await api.scolarites()
  return member
})

const anneeCourante = computed(
  () => (scolarites.value ?? []).find((scolarite) => scolarite.anneeUniversitaire.actif) ?? null,
)

/* Le type n'est rappele que s'il n'est pas deja le libelle, sinon on lit « CM (CM) ». */
const groupesLisibles = computed(() =>
  (fiche.value?.groupes ?? [])
    .map((groupe) =>
      groupe.libelle === groupe.type ? groupe.libelle : `${groupe.libelle} (${groupe.type})`,
    )
    .join(', '),
)
</script>

<template>
  <div v-if="fiche" class="profil">
    <h1>Mon profil</h1>

    <section aria-labelledby="titre-identite">
      <h2 id="titre-identite">Identite</h2>
      <dl>
        <div>
          <dt>Nom</dt>
          <dd>{{ fiche.display }}</dd>
        </div>
        <div>
          <dt>Identifiant</dt>
          <dd>{{ fiche.username }}</dd>
        </div>
        <div>
          <dt>Adresse universitaire</dt>
          <dd>
            <a :href="`mailto:${fiche.mailUniv}`">{{ fiche.mailUniv }}</a>
          </dd>
        </div>
        <div>
          <dt>Boursier</dt>
          <dd>{{ fiche.boursier ? 'Oui' : 'Non' }}</dd>
        </div>
      </dl>
    </section>

    <section aria-labelledby="titre-scolarite">
      <h2 id="titre-scolarite">Ma scolarite</h2>

      <dl>
        <div v-if="anneeCourante">
          <dt>Annee en cours</dt>
          <dd>{{ anneeCourante.anneeUniversitaire.libelle }}</dd>
        </div>
        <div v-if="groupesLisibles">
          <dt>Mes groupes</dt>
          <dd>{{ groupesLisibles }}</dd>
        </div>
      </dl>

      <p v-if="!anneeCourante && !groupesLisibles">
        Aucune information de scolarite n'est disponible pour le moment.
      </p>
    </section>

    <!--
      L'API accepte que l'etudiant modifie son courriel personnel, ses telephones et
      ses adresses, mais ne les renvoie pas sur sa propre fiche. Un formulaire serait
      en ecriture seule : on n'en met pas tant que la lecture n'est pas ouverte.
    -->
    <section aria-labelledby="titre-coordonnees">
      <h2 id="titre-coordonnees">Mes coordonnees personnelles</h2>
      <p>
        La modification de vos coordonnees personnelles n'est pas encore disponible. Adressez-vous a
        la scolarite pour toute correction.
      </p>
    </section>
  </div>
</template>

<style scoped>
.profil {
  max-width: 48rem;
  margin: 0 auto;
  padding: 2rem 1rem;
}

h1 {
  margin: 0 0 1.5rem;
}

h2 {
  font-size: 1.25rem;
  margin: 2rem 0 0.75rem;
}

dl {
  margin: 0;
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 0.5rem 1.5rem;
}

dl > div {
  display: contents;
}

dt {
  color: var(--couleur-texte-attenue);
}

dd {
  margin: 0;
}

@media (max-width: 36rem) {
  dl {
    grid-template-columns: 1fr;
    gap: 0.25rem;
  }

  dt {
    margin-top: 0.5rem;
  }
}
</style>
