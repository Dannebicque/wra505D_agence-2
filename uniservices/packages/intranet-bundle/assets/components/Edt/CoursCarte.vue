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

const LIBELLES_TYPE = {cm: 'CM', td: 'TD', tp: 'TP', sae: 'SAÉ', evaluation: 'Éval.'};

const type = computed(() => typeSeance(props.cours));
const libelleType = computed(() => LIBELLES_TYPE[type.value] ?? props.cours.type ?? 'Cours');
const densite = computed(() => densiteCarte(props.hauteur));

const heure = (date) => date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
const horaire = computed(() => `${heure(props.cours.start)} – ${heure(props.cours.end)}`);

// « CM CM » : le groupe de CM porte le nom du type, on ne l'écrit qu'une fois.
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
      :style="{
        '--seance-fond': `var(--p-seance-${type}-background)`,
        '--seance-couleur': `var(--p-seance-${type}-color)`,
        '--seance-pastille': `var(--p-seance-${type}-pill-background)`,
        '--seance-pastille-texte': `var(--p-seance-${type}-pill-color)`,
      }"
  >
    <p v-if="densite === 'ligne'" class="cours-carte__ligne">
      <span class="cours-carte__pastille">{{ libelleType }}</span>
      <strong>{{ cours.codeModule }}</strong>
      <span class="cours-carte__detail"> · {{ heure(cours.start) }}<template v-if="cours.salle"> · {{ cours.salle }}</template></span>
    </p>

    <template v-else-if="densite === 'compacte'">
      <p class="cours-carte__ligne">
        <span class="cours-carte__pastille">{{ libelleType }}</span>
        <strong>{{ cours.codeModule }}</strong> {{ cours.libModule }}
      </p>
      <p class="cours-carte__ligne cours-carte__detail">
        <span v-if="etat" class="cours-carte__etat">{{ etat }}</span>
        {{ [horaire, cours.salle].filter(Boolean).join(' · ') }}
      </p>
    </template>

    <template v-else>
      <div class="cours-carte__haut">
        <span class="cours-carte__pastille">{{ libelleType }}</span>
        <span v-if="groupe" class="cours-carte__groupe">{{ groupe }}</span>
        <span v-if="etat" class="cours-carte__etat">{{ etat }}</span>
      </div>
      <p class="cours-carte__ligne cours-carte__titre">
        <strong>{{ cours.codeModule }}</strong> {{ cours.libModule }}
      </p>
      <p class="cours-carte__ligne cours-carte__horaire">{{ horaire }}</p>
      <p v-if="cours.salle" class="cours-carte__ligne cours-carte__info cours-carte__salle">
        <i class="pi pi-map-marker" aria-hidden="true"></i>{{ cours.salle }}
      </p>
      <p v-if="cours.enseignant" class="cours-carte__ligne cours-carte__info cours-carte__enseignant">
        <i class="pi pi-user" aria-hidden="true"></i>{{ cours.enseignant }}
      </p>
    </template>
  </div>
</template>

<style scoped>
@reference "../../assets/tailwind.css";

.cours-carte {
  @apply h-full flex flex-col overflow-hidden text-left text-color;
  gap: 0.125rem;
  padding: 0.375rem 0.5rem;
  font-size: 0.8125rem;
  line-height: 1.3;
  background: var(--seance-fond);
  border-radius: 12px;
  transition: box-shadow 150ms ease, transform 150ms ease;
  /* La carte connaît sa taille : les lignes facultatives s'effacent quand elle rétrécit. */
  container-type: size;
}

.cours-carte p {
  margin: 0;
}

.cours-carte--ligne {
  @apply justify-center;
  padding-block: 0;
}

.cours-carte__ligne {
  @apply whitespace-nowrap overflow-hidden text-ellipsis;
}

.cours-carte__haut {
  @apply flex items-center gap-1.5 min-w-0;
}

.cours-carte__pastille {
  @apply inline-block font-bold uppercase whitespace-nowrap align-middle mr-1.5;
  padding: 0 0.4375rem;
  border-radius: 999px;
  font-size: 0.6875rem;
  line-height: 1.125rem;
  letter-spacing: 0.02em;
  color: var(--seance-pastille-texte);
  background: var(--seance-pastille);
}

.cours-carte__haut .cours-carte__pastille {
  @apply mr-0;
}

.cours-carte__groupe {
  @apply font-bold truncate;
  font-size: 0.75rem;
  color: var(--seance-couleur);
}

.cours-carte__titre {
  font-size: 0.875rem;
}

.cours-carte__horaire {
  @apply font-semibold;
}

.cours-carte__detail {
  font-variant-numeric: tabular-nums;
}

.cours-carte__info {
  @apply flex items-center gap-1.5;
}

.cours-carte__info i {
  font-size: 0.75rem;
  color: var(--seance-couleur);
}

.cours-carte__etat {
  @apply ml-auto shrink-0 font-bold whitespace-nowrap;
  padding: 0 0.4375rem;
  border-radius: 999px;
  font-size: 0.6875rem;
  line-height: 1.125rem;
  color: var(--p-accent-contrast-color);
  background: var(--p-accent-color);
}

.cours-carte__detail .cours-carte__etat {
  @apply inline-block ml-0 mr-1.5 align-middle;
}

/* Le cours en cours se repère au jaune de la DA, l'accent de l'application. */
.cours-carte--en-cours {
  box-shadow: inset 0 0 0 2px var(--p-accent-color);
}

/* Une carte complète garde le titre et l'horaire, puis la salle, puis l'enseignant, selon la place.
   Hauteurs de la zone de contenu, sans les 12 px de marge intérieure. */
@container (max-height: 94px) {
  .cours-carte__enseignant {
    @apply hidden;
  }
}

@container (max-height: 76px) {
  .cours-carte__salle {
    @apply hidden;
  }
}

/* Dans une colonne partagée par deux cours, le titre passe avant le groupe. */
@container (max-width: 9rem) {
  .cours-carte__groupe {
    @apply hidden;
  }
}
</style>
