<script setup>
import {computed} from 'vue';
import {densiteCarte, etatCours, typeSeance} from '@/utils/agenda.js';
import {formatDelai} from '@/utils/prochainCours.js';

const props = defineProps({
  cours: {
    type: Object,
    required: true
  },
  // Hauteur de la carte dans la grille, en pixels : elle décide de ce qui tient dedans.
  hauteur: {
    type: Number,
    required: true
  },
  maintenant: {
    type: Date,
    required: true
  }
});

const LIBELLES_TYPE = {cm: 'CM', td: 'TD', tp: 'TP', sae: 'SAÉ', evaluation: 'Évaluation'};

const type = computed(() => typeSeance(props.cours));
const libelleType = computed(() => LIBELLES_TYPE[type.value] ?? props.cours.type ?? 'Cours');
const densite = computed(() => densiteCarte(props.hauteur));

const heure = (date) => date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
const horaire = computed(() => `${heure(props.cours.start)} – ${heure(props.cours.end)}`);

// « CM CM » : le groupe de CM porte le même nom que le type, on ne l'écrit qu'une fois.
const groupe = computed(() => (props.cours.groupe && props.cours.groupe !== props.cours.type ? props.cours.groupe : null));

const etat = computed(() => {
  const {etat: valeur, dans} = etatCours(props.cours, props.maintenant);
  if (valeur === 'en-cours') {
    return 'En cours';
  }
  const memeJour = props.cours.start.toDateString() === props.maintenant.toDateString();
  return valeur === 'a-venir' && memeJour && dans <= 60 ? `Dans ${formatDelai(dans)}` : null;
});
</script>

<template>
  <div
      :class="['cours-carte', `cours-carte--${densite}`, {'cours-carte--en-cours': etat === 'En cours'}]"
      :style="{'--seance-fond': `var(--p-seance-${type}-background)`, '--seance-couleur': `var(--p-seance-${type}-color)`}"
  >
    <template v-if="densite === 'ligne'">
      <p class="cours-carte__ligne">
        <strong>{{ cours.codeModule || libelleType }}</strong>
        <span v-if="cours.salle"> · {{ cours.salle }}</span>
        <span> · {{ heure(cours.start) }}</span>
      </p>
    </template>

    <template v-else-if="densite === 'compacte'">
      <p class="cours-carte__titre cours-carte__tronque">
        <span class="cours-carte__type">{{ libelleType }}</span>
        <strong>{{ cours.codeModule }}</strong> {{ cours.libModule }}
      </p>
      <p class="cours-carte__tronque">{{ [cours.salle, horaire].filter(Boolean).join(' · ') }}</p>
      <span v-if="etat" class="cours-carte__etat">{{ etat }}</span>
    </template>

    <template v-else>
      <p class="cours-carte__titre cours-carte__deux-lignes">
        <strong>{{ cours.codeModule }}</strong> {{ cours.libModule }}
      </p>
      <p class="cours-carte__tronque">
        <span class="cours-carte__type">{{ libelleType }}</span>
        <span v-if="groupe">{{ groupe }}</span>
      </p>
      <p v-if="cours.salle" class="cours-carte__tronque">{{ cours.salle }}</p>
      <p v-if="cours.enseignant" class="cours-carte__tronque">{{ cours.enseignant }}</p>
      <p class="cours-carte__horaire">
        <span>{{ horaire }}</span>
        <span v-if="etat" class="cours-carte__etat">{{ etat }}</span>
      </p>
    </template>
  </div>
</template>

<style scoped>
@reference "../../assets/tailwind.css";

.cours-carte {
  @apply h-full flex flex-col gap-0.5 overflow-hidden text-left text-color;
  padding: 0.25rem 0.5rem 0.25rem 0.625rem;
  font-size: 0.8125rem;
  line-height: 1.25;
  background: var(--seance-fond);
  border: 1px solid var(--seance-couleur);
  border-left-width: 4px;
  border-radius: 6px;
}

.cours-carte p {
  margin: 0;
}

.cours-carte--ligne {
  @apply justify-center;
  padding-block: 0;
}

.cours-carte__ligne,
.cours-carte__tronque {
  @apply whitespace-nowrap overflow-hidden text-ellipsis;
}

.cours-carte__deux-lignes {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.cours-carte__titre {
  font-size: 0.875rem;
}

.cours-carte__type {
  @apply font-bold uppercase mr-1;
  color: var(--seance-couleur);
  font-size: 0.75rem;
  letter-spacing: 0.02em;
}

.cours-carte__horaire {
  @apply mt-auto flex items-center justify-between gap-2 font-medium;
}

.cours-carte__etat {
  @apply font-bold whitespace-nowrap;
  color: var(--seance-couleur);
}

.cours-carte--en-cours {
  box-shadow: 0 0 0 2px var(--seance-couleur);
}
</style>
