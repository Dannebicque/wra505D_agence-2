<script setup>
import {computed, nextTick, ref} from 'vue';
import {libelleSemestre, semestresParAnnee} from '@/utils/scolarite.js';

// Sur téléphone, le semestre se choisit depuis le bouton flottant de l'agenda, en bas de l'écran,
// sous le pouce : la barre du haut garde ses seuls onglets.
const props = defineProps({
  semestres: {type: Array, required: true},
});
const choisi = defineModel({type: Number, default: null});

const fenetre = ref(null);
const liste = ref(null);
const ouverte = ref(false);

const annees = computed(() => semestresParAnnee(props.semestres));
const semestreChoisi = computed(() => props.semestres.find(semestre => semestre.id === choisi.value) ?? null);

// À l'ouverture, le semestre affiché est sous les yeux et prend le focus.
const auDebut = async () => {
  ouverte.value = true;
  await nextTick();
  liste.value?.querySelector('[aria-current="true"]')?.focus();
};

const choisir = (id) => {
  fenetre.value.hide();
  choisi.value = id;
};
</script>

<template>
  <Button
      icon="pi pi-calendar"
      rounded
      :aria-label="semestreChoisi ? `Semestre affiché : ${libelleSemestre(semestreChoisi)}, en changer` : 'Choisir un semestre'"
      aria-haspopup="dialog"
      :aria-expanded="ouverte"
      class="semestre-flottant"
      @click="fenetre.toggle($event)"
  />

  <Popover ref="fenetre" class="semestre-fenetre" aria-label="Choisir un semestre" @show="auDebut" @hide="ouverte = false">
    <div ref="liste" class="semestre-fenetre__liste">
      <section v-for="annee in annees" :key="annee.annee">
        <h3 class="semestre-fenetre__annee">{{ annee.annee }}</h3>
        <ul>
          <li v-for="semestre in annee.semestres" :key="semestre.id">
            <button
                type="button"
                :class="['semestre-fenetre__choix', {'semestre-fenetre__choix--affiche': semestre.id === choisi}]"
                :aria-current="semestre.id === choisi ? 'true' : undefined"
                @click="choisir(semestre.id)"
            >
              {{ semestre.libelle }}<span v-if="semestre.actif" class="semestre-fenetre__en-cours"> · en cours</span>
              <i v-if="semestre.id === choisi" class="pi pi-check" aria-hidden="true"></i>
            </button>
          </li>
        </ul>
      </section>
    </div>
  </Popover>
</template>

<style scoped>
/* Le bouton flottant de l'agenda : rond violet, icône blanche 9,95:1, en bas à droite. Il n'existe
   que sur téléphone ; au-delà, le semestre se choisit dans la barre de la page. */
.semestre-flottant {
  position: fixed !important;
  right: 1.25rem;
  bottom: 1.25rem;
  z-index: 20;
  width: 3.5rem !important;
  height: 3.5rem !important;
  box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
}

@media (min-width: 640px) {
  .semestre-flottant {
    display: none !important;
  }
}
</style>

<style>
/* La fenêtre est téléportée hors du composant : ses styles ne peuvent pas être scopés. Une simple
   liste : l'année en intertitre, chaque semestre sur sa ligne, une coche sur celui affiché. */
.semestre-fenetre.p-popover {
  border: 0;
  border-radius: 18px;
  background: var(--p-content-background);
  box-shadow: 0 12px 32px -12px rgb(0 0 0 / 0.3);
}

.semestre-fenetre.p-popover::before,
.semestre-fenetre.p-popover::after {
  display: none;
}

.semestre-fenetre .p-popover-content {
  padding: 0.5rem;
}

.semestre-fenetre__liste {
  width: min(16rem, calc(100vw - 2rem));
  max-height: min(24rem, 60vh);
  overflow-y: auto;
  overscroll-behavior: contain;
}

.semestre-fenetre__liste ul {
  margin: 0;
  padding: 0;
  list-style: none;
}

.semestre-fenetre__annee {
  margin: 0.5rem 0.75rem 0.25rem !important;
  font-size: 1rem !important;
  font-weight: 600;
  color: var(--p-text-muted-color);
}

.semestre-fenetre__choix {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  width: 100%;
  min-height: 48px;
  padding: 0 0.75rem;
  border: 0;
  border-radius: 12px;
  background: transparent;
  color: var(--p-text-color);
  font: inherit;
  font-size: 1rem;
  text-align: left;
  cursor: pointer;
}

.semestre-fenetre__choix:hover {
  background: var(--p-content-hover-background);
}

.semestre-fenetre__choix:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: -2px;
}

.semestre-fenetre__choix--affiche {
  font-weight: 700;
  color: var(--p-primary-color);
}

.semestre-fenetre__en-cours {
  margin-right: auto;
  font-weight: 400;
  color: var(--p-text-muted-color);
}
</style>
