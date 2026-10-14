<script setup>
import {computed} from 'vue';
import {useToast} from 'primevue/usetoast';
import {initiales} from '@helpers/profil.js';
import {PhotoUser} from '@components';

const props = defineProps({
  etudiant: {type: Object, required: true},
  // L'année en cours lue par scolariteEnCours ; null tant que la scolarité se charge ou manque.
  enCours: {type: Object, default: null},
  chargementScolarite: {type: Boolean, default: false},
});

const toast = useToast();

// L'API renvoie noimage.png quand l'étudiant n'a pas de photo : les initiales la remplacent.
const aUnePhoto = computed(() => Boolean(props.etudiant.photoName) && props.etudiant.photoName !== 'noimage.png');

const copierMail = () => {
  navigator.clipboard.writeText(props.etudiant.mailUniv)
      .then(() => toast.add({severity: 'success', summary: 'Succès', detail: 'Adresse email copiée', life: 5000}))
      .catch(() => toast.add({severity: 'error', summary: 'Erreur', detail: 'Échec de la copie', life: 5000}));
};
</script>

<template>
  <section class="carte-etudiant" aria-labelledby="profil-nom">
    <span class="carte-etudiant__cercle carte-etudiant__cercle--accent" aria-hidden="true"></span>
    <span class="carte-etudiant__cercle carte-etudiant__cercle--clair" aria-hidden="true"></span>
    <span class="carte-etudiant__cercle carte-etudiant__cercle--sombre" aria-hidden="true"></span>

    <p class="carte-etudiant__surtitre">
      Carte étudiante<template v-if="enCours?.anneeUniversitaire"> · {{ enCours.anneeUniversitaire }}</template>
    </p>

    <div class="carte-etudiant__identite">
      <span class="carte-etudiant__avatar">
        <PhotoUser v-if="aUnePhoto" :user-photo="etudiant.photoName" alt="" class="carte-etudiant__photo"/>
        <span v-else aria-hidden="true">{{ initiales(etudiant.prenom, etudiant.nom) }}</span>
      </span>
      <div class="carte-etudiant__nom-bloc">
        <h2 id="profil-nom" class="carte-etudiant__nom">
          <span class="carte-etudiant__prenom">{{ etudiant.prenom }}</span>
          <span class="carte-etudiant__nom-famille">{{ etudiant.nom }}</span>
        </h2>
        <Skeleton v-if="chargementScolarite" width="7rem" height="1.25rem" class="opacity-40" aria-hidden="true"/>
        <p v-else-if="enCours?.formation" class="carte-etudiant__formation">
          {{ enCours.formation }}<template v-if="enCours.semestre"> · {{ enCours.semestre }}</template>
        </p>
      </div>
    </div>

    <ul v-if="enCours?.groupes.length" class="carte-etudiant__groupes" aria-label="Mes groupes">
      <li v-for="groupe in enCours.groupes" :key="groupe.type" class="carte-etudiant__groupe">
        <span class="carte-etudiant__groupe-type">{{ groupe.type }}</span>
        <span v-if="groupe.libelle !== groupe.type">{{ groupe.libelle }}</span>
      </li>
    </ul>

    <div class="carte-etudiant__pied">
      <button type="button" class="carte-etudiant__mail" @click="copierMail">
        <span class="carte-etudiant__mail-icone" aria-hidden="true"><i class="pi pi-copy"></i></span>
        <span class="sr-only">Copier l'adresse </span>
        <span class="carte-etudiant__mail-texte">{{ etudiant.mailUniv }}</span>
      </button>
      <dl class="carte-etudiant__infos">
        <div>
          <dt>Identifiant URCA</dt>
          <dd>{{ etudiant.username }}</dd>
        </div>
      </dl>
    </div>
  </section>
</template>

<style scoped>
/* La carte de l'accueil en carte d'étudiant : le violet de l'IUT en plein, quel que soit le thème
   (texte blanc à 9,95:1, violet 200 à 7,4:1), et les cercles du logo, jamais sous un texte. */
.carte-etudiant {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  padding: 1.25rem 1.375rem 1.375rem;
  border-radius: 18px;
  background: var(--p-primary-500);
  color: #FFFFFF;
  overflow: hidden;
}

.carte-etudiant__cercle {
  position: absolute;
  border-radius: 999px;
  pointer-events: none;
}

.carte-etudiant__cercle--accent {
  right: -2.5rem;
  top: -2.5rem;
  width: 7.5rem;
  height: 7.5rem;
  background: var(--accent-color);
}

.carte-etudiant__cercle--clair {
  right: 5.25rem;
  top: 2.25rem;
  width: 1.25rem;
  height: 1.25rem;
  background: var(--p-primary-300);
}

.carte-etudiant__cercle--sombre {
  right: -3rem;
  bottom: -4rem;
  width: 9rem;
  height: 9rem;
  background: var(--p-primary-600);
}

.carte-etudiant__surtitre {
  position: relative;
  margin: 0;
  max-width: calc(100% - 6rem);
  font-size: 0.875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--p-primary-200);
}

.carte-etudiant__identite {
  position: relative;
  display: flex;
  align-items: center;
  gap: 1rem;
}

/* Le jaune n'apparaît qu'ici et dans les cercles : il désigne la personne. Initiales violettes
   sur l'accent : 5,29:1. */
.carte-etudiant__avatar {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 5rem;
  height: 5rem;
  border: 3px solid var(--p-primary-200);
  border-radius: 999px;
  font-size: 1.75rem;
  font-weight: 700;
  background: var(--accent-color);
  color: var(--p-primary-500);
  overflow: hidden;
}

.carte-etudiant__photo {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.carte-etudiant__nom-bloc {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.carte-etudiant__nom {
  display: flex;
  flex-direction: column;
  margin: 0 !important;
  font-size: 1.75rem !important;
  font-weight: 700;
  line-height: 1.15;
  color: inherit;
  overflow-wrap: anywhere;
}

.carte-etudiant__prenom {
  font-size: 1.25rem;
  font-weight: 300;
  text-transform: capitalize;
}

.carte-etudiant__nom-famille {
  text-transform: uppercase;
}

.carte-etudiant__formation {
  margin: 0;
  font-size: 1rem;
  color: var(--p-primary-200);
}

.carte-etudiant__groupes {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

/* Toutes les capsules ont la même hauteur et leur texte au centre, qu'elles portent un type seul
   (CM) ou un type et son groupe (TD AB). */
.carte-etudiant__groupe {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  min-height: 2rem;
  padding: 0 0.875rem;
  border-radius: 999px;
  font-weight: 700;
  line-height: 1;
  background: color-mix(in srgb, #FFFFFF 12%, transparent);
}

.carte-etudiant__groupe-type {
  font-size: 0.8125rem;
  letter-spacing: 0.04em;
  color: var(--p-primary-200);
}

/* Seul dans sa capsule, le type n'a plus à s'effacer devant un nom de groupe. */
.carte-etudiant__groupe-type:only-child {
  font-size: 1rem;
  letter-spacing: 0;
  color: inherit;
}

.carte-etudiant__pied {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-top: auto;
  max-width: calc(100% - 3rem);
}

/* La capsule de l'événement du jour, rendue cliquable : l'adresse se copie d'un geste. */
.carte-etudiant__mail {
  display: inline-flex;
  align-items: center;
  align-self: flex-start;
  gap: 0.5rem;
  max-width: 100%;
  min-height: 44px;
  padding: 0.25rem 0.875rem 0.25rem 0.25rem;
  border-radius: 999px;
  font-size: 0.9375rem;
  color: inherit;
  background: color-mix(in srgb, #FFFFFF 12%, transparent);
  cursor: pointer;
  transition: background-color 150ms ease;
}

.carte-etudiant__mail:hover {
  background: color-mix(in srgb, #FFFFFF 20%, transparent);
}

.carte-etudiant__mail:focus-visible {
  outline: 2px solid var(--accent-color);
  outline-offset: 2px;
}

.carte-etudiant__mail-icone {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 999px;
  font-size: 0.875rem;
  background: var(--accent-color);
  color: var(--p-primary-500);
}

/* L'adresse se lit en entier, quitte à passer à la ligne : c'est elle qu'on vient chercher. */
.carte-etudiant__mail-texte {
  min-width: 0;
  text-align: left;
  overflow-wrap: anywhere;
}

.carte-etudiant__infos {
  margin: 0;
}

.carte-etudiant__infos dt {
  font-size: 0.875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--p-primary-200);
}

.carte-etudiant__infos dd {
  margin: 0.125rem 0 0;
  font-size: 1.125rem;
  font-weight: 600;
}

@media (max-width: 1023px) {
  .carte-etudiant {
    gap: 1rem;
    padding: 1rem;
  }

  .carte-etudiant__avatar {
    width: 4rem;
    height: 4rem;
    font-size: 1.375rem;
  }

  .carte-etudiant__nom {
    font-size: 1.5rem !important;
  }

  .carte-etudiant__cercle--accent {
    width: 5.5rem;
    height: 5.5rem;
    right: -1.75rem;
    top: -1.75rem;
  }

  .carte-etudiant__cercle--clair {
    right: 4.25rem;
    top: 1rem;
    width: 1rem;
    height: 1rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .carte-etudiant__mail {
    transition: none;
  }
}
</style>
