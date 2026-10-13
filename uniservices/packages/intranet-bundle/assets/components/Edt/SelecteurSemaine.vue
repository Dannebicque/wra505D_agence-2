<script setup>
import {computed, nextTick, ref} from 'vue';
import {formatDelai} from '@/utils/prochainCours.js';

const props = defineProps({
  // [{lundi: 'AAAA-MM-JJ', semaineFormation, minutes}]
  semaines: {
    type: Array,
    required: true
  },
  // Lundi de la semaine affichée, AAAA-MM-JJ.
  lundiAffiche: {
    type: String,
    default: null
  },
  // Lundi de la semaine en cours, AAAA-MM-JJ.
  lundiCourant: {
    type: String,
    required: true
  }
});

const emit = defineEmits(['choisir']);

const fenetre = ref(null);
const liste = ref(null);
const ouverte = ref(false);

const date = (iso, decalage = 0) => {
  const [a, m, j] = iso.split('-').map(Number);
  return new Date(a, m - 1, j + decalage);
};

const court = (jour) => jour.toLocaleDateString('fr-FR', {day: 'numeric', month: 'short'});

// La barre la plus longue est celle de la semaine la plus chargée de l'année.
const maximum = computed(() => Math.max(1, ...props.semaines.map(semaine => semaine.minutes)));

const mois = computed(() => {
  const groupes = [];
  for (const semaine of props.semaines) {
    const lundi = date(semaine.lundi);
    const libelle = lundi.toLocaleDateString('fr-FR', {month: 'long', year: 'numeric'});
    if (groupes.at(-1)?.libelle !== libelle) {
      groupes.push({libelle, semaines: []});
    }
    groupes.at(-1).semaines.push({
      ...semaine,
      dates: `${court(lundi)} – ${court(date(semaine.lundi, 4))}`,
      charge: semaine.minutes === 0 ? 'Pas de cours' : formatDelai(semaine.minutes),
      part: `${Math.round((semaine.minutes / maximum.value) * 100)}%`,
    });
  }
  return groupes;
});

const basculer = (evenement) => fenetre.value.toggle(evenement);

// À l'ouverture, la semaine affichée est sous les yeux et prend le focus.
const auDebut = async () => {
  ouverte.value = true;
  await nextTick();
  const choisie = liste.value?.querySelector('[aria-current="true"]') ?? liste.value?.querySelector('button');
  choisie?.scrollIntoView({block: 'center'});
  choisie?.focus();
};

const choisir = (lundi) => {
  fenetre.value.hide();
  emit('choisir', lundi);
};
</script>

<template>
  <button
      type="button"
      class="selecteur-semaine__declencheur"
      aria-haspopup="dialog"
      :aria-expanded="ouverte"
      @click="basculer"
  >
    <slot />
    <i class="pi pi-chevron-down" aria-hidden="true"></i>
  </button>

  <Popover ref="fenetre" class="selecteur-semaine" aria-label="Choisir une semaine" @show="auDebut" @hide="ouverte = false">
    <div ref="liste" class="selecteur-semaine__liste">
      <section v-for="groupe in mois" :key="groupe.libelle">
        <h3 class="selecteur-semaine__mois">{{ groupe.libelle }}</h3>
        <ul>
          <li v-for="semaine in groupe.semaines" :key="semaine.lundi">
            <button
                type="button"
                :class="['selecteur-semaine__semaine', {
                  'selecteur-semaine__semaine--affichee': semaine.lundi === lundiAffiche,
                  'selecteur-semaine__semaine--vide': semaine.minutes === 0,
                }]"
                :aria-current="semaine.lundi === lundiAffiche ? 'true' : undefined"
                @click="choisir(semaine.lundi)"
            >
              <span class="selecteur-semaine__dates">
                <span>{{ semaine.dates }}</span>
                <span v-if="semaine.lundi === lundiCourant" class="selecteur-semaine__courante">cette semaine</span>
              </span>
              <span class="selecteur-semaine__jauge" aria-hidden="true">
                <span :style="{ width: semaine.part }"></span>
              </span>
              <span class="selecteur-semaine__charge">{{ semaine.charge }}</span>
            </button>
          </li>
        </ul>
      </section>
    </div>
  </Popover>
</template>

<style scoped>
@reference "../../assets/tailwind.css";

.selecteur-semaine__declencheur {
  @apply inline-flex items-center gap-2 -mx-2 px-2 rounded-full cursor-pointer text-inherit bg-transparent border-0;
  font: inherit;
  min-height: 44px;
  i {
    @apply text-sm text-primary transition-transform;
  }
  &:hover {
    @apply bg-emphasis;
  }
  &[aria-expanded="true"] i {
    @apply rotate-180;
  }
  &:focus-visible {
    outline: 3px solid var(--p-primary-color);
    outline-offset: 2px;
  }
}
</style>

<style>
/* La fenêtre est téléportée hors du composant : ses styles ne peuvent pas être scopés. */
.selecteur-semaine.p-popover {
  border: 0;
  border-radius: 24px;
  box-shadow: 0 16px 40px -12px rgb(0 0 0 / 0.3);
}

.selecteur-semaine .p-popover-content {
  padding: 0.5rem;
}

.selecteur-semaine__liste {
  width: min(24rem, calc(100vw - 2rem));
  max-height: min(26rem, 60vh);
  overflow-y: auto;
  overscroll-behavior: contain;
}

.selecteur-semaine__liste ul {
  margin: 0;
  padding: 0;
  list-style: none;
}

.selecteur-semaine__mois {
  margin: 0.75rem 0.75rem 0.25rem;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--p-text-muted-color);
}

.selecteur-semaine__semaine {
  display: grid;
  grid-template-columns: 7.5rem 1fr 5.5rem;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  min-height: 44px;
  padding: 0.25rem 0.75rem;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--p-text-color);
  font: inherit;
  font-size: 0.875rem;
  text-align: left;
  cursor: pointer;
}

.selecteur-semaine__semaine:hover {
  background: var(--p-content-hover-background);
}

.selecteur-semaine__semaine:focus-visible {
  outline: 3px solid var(--p-primary-color);
  outline-offset: -3px;
}

.selecteur-semaine__semaine--affichee {
  background: color-mix(in srgb, var(--p-primary-color) 12%, transparent);
  font-weight: 600;
}

.selecteur-semaine__dates {
  display: flex;
  flex-direction: column;
  line-height: 1.2;
}

.selecteur-semaine__courante {
  font-size: 0.6875rem;
  font-weight: 700;
  color: var(--p-primary-color);
}

.selecteur-semaine__jauge {
  height: 0.5rem;
  border-radius: 999px;
  background: var(--p-content-hover-background);
  overflow: hidden;
}

.selecteur-semaine__jauge span {
  display: block;
  height: 100%;
  border-radius: 999px;
  background: var(--p-primary-color);
}

.selecteur-semaine__charge {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.selecteur-semaine__semaine--vide .selecteur-semaine__charge {
  color: var(--p-text-muted-color);
  font-size: 0.75rem;
}
</style>
