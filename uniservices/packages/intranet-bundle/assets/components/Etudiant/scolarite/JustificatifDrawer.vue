<script setup>
import {computed, nextTick, ref, watch} from 'vue';
import {deposerJustificatifService} from '@requests';
import {showSuccess} from '@helpers/toast.js';
import {creneauAbsence, dateHeureLocale, erreurPiece, periodeAbsence, PIECE_JUSTIFICATIVE} from '@/utils/scolarite.js';

// L'absence à justifier ; null ferme le panneau.
const props = defineProps({
  absence: {type: Object, default: null},
});
const emit = defineEmits(['fermer', 'depose']);

const MOTIFS = ['Maladie', 'Rendez-vous médical', 'Convocation officielle', 'Raison familiale'];

const visible = computed({
  get: () => props.absence !== null,
  set: (ouvert) => {
    if (!ouvert) {
      emit('fermer');
    }
  },
});

const debut = ref(null);
const fin = ref(null);
const motif = ref('');
const piece = ref(null);
const erreurs = ref({});
const envoi = ref(false);
const survol = ref(false);
const champPiece = ref(null);

// Chaque ouverture repart de l'absence choisie : la période couvre son créneau, à élargir au besoin.
watch(() => props.absence, (absence) => {
  if (!absence) {
    return;
  }
  ({debut: debut.value, fin: fin.value} = periodeAbsence(absence));
  motif.value = '';
  piece.value = null;
  erreurs.value = {};
});

// Une erreur s'efface dès que son champ change : elle ne reste pas affichée sur une saisie corrigée.
const effacer = (champ) => {
  if (erreurs.value[champ]) {
    erreurs.value = {...erreurs.value, [champ]: null};
  }
};
watch(debut, () => effacer('debut'));
watch(fin, () => effacer('fin'));
watch(motif, () => effacer('motif'));

const choisirPiece = (fichier) => {
  piece.value = fichier ?? null;
  erreurs.value = {...erreurs.value, fichier: piece.value ? erreurPiece(piece.value) : null};
};

const deposerSurZone = (evenement) => {
  survol.value = false;
  choisirPiece(evenement.dataTransfer?.files?.[0]);
};

// La zone de dépôt revient à la place du bouton « Retirer » : le focus la suit.
const retirerPiece = async () => {
  piece.value = null;
  await nextTick();
  champPiece.value?.focus();
};

const taille = (octets) => {
  if (octets < 1024) {
    return 'moins de 1 Ko';
  }
  return octets < 1024 * 1024 ? `${Math.round(octets / 1024)} Ko` : `${(octets / 1024 / 1024).toFixed(1).replace('.', ',')} Mo`;
};

const valider = () => {
  const trouvees = {};
  if (!debut.value) {
    trouvees.debut = 'Indiquez le début de la période.';
  }
  if (!fin.value) {
    trouvees.fin = 'Indiquez la fin de la période.';
  } else if (debut.value && fin.value < debut.value) {
    trouvees.fin = 'La fin doit suivre le début.';
  }
  if (!motif.value.trim()) {
    trouvees.motif = 'Indiquez le motif de l\'absence.';
  }
  const erreurFichier = erreurPiece(piece.value);
  if (erreurFichier) {
    trouvees.fichier = erreurFichier;
  }
  erreurs.value = trouvees;
  return Object.keys(trouvees).length === 0;
};

const envoyer = async () => {
  if (!valider()) {
    return;
  }
  const donnees = new FormData();
  donnees.append('debut', dateHeureLocale(debut.value));
  donnees.append('fin', dateHeureLocale(fin.value));
  donnees.append('motif', motif.value.trim());
  donnees.append('fichier', piece.value);

  envoi.value = true;
  try {
    const reponse = await deposerJustificatifService(donnees);
    showSuccess('Justificatif déposé : il attend la décision du secrétariat.');
    emit('depose', reponse);
  } catch (erreur) {
    erreurs.value = erreur?.response?.data?.erreurs ?? {general: 'Le justificatif n\'a pas pu être déposé. Réessayez dans un instant.'};
  } finally {
    envoi.value = false;
  }
};
</script>

<template>
  <Drawer
      v-model:visible="visible"
      position="right"
      header="Justifier une absence"
      aria-label="Justifier une absence"
      class="justificatif"
      :style="{ width: 'min(32rem, 100vw)' }"
      :block-scroll="true"
  >
    <form v-if="absence" class="formulaire" novalidate @submit.prevent="envoyer">
      <p class="rappel">
        <span class="rappel__matiere">{{ absence.matiere || 'Cours' }}</span>
        <span>{{ creneauAbsence(absence) }}</span>
      </p>

      <fieldset class="champ">
        <legend class="champ__titre">Période couverte</legend>
        <p class="champ__aide">Élargissez-la si votre justificatif couvre plusieurs créneaux ou plusieurs jours.</p>
        <div class="periode">
          <div class="periode__borne">
            <label for="justificatif-debut">Du</label>
            <DatePicker
                v-model="debut"
                input-id="justificatif-debut"
                show-time
                hour-format="24"
                date-format="dd/mm/yy"
                :invalid="!!erreurs.debut"
                :pt="{ pcInputText: { root: { 'aria-describedby': erreurs.debut ? 'justificatif-debut-erreur' : undefined } } }"
                fluid
            />
            <small v-if="erreurs.debut" id="justificatif-debut-erreur" class="erreur">{{ erreurs.debut }}</small>
          </div>
          <div class="periode__borne">
            <label for="justificatif-fin">Au</label>
            <DatePicker
                v-model="fin"
                input-id="justificatif-fin"
                show-time
                hour-format="24"
                date-format="dd/mm/yy"
                :invalid="!!erreurs.fin"
                :pt="{ pcInputText: { root: { 'aria-describedby': erreurs.fin ? 'justificatif-fin-erreur' : undefined } } }"
                fluid
            />
            <small v-if="erreurs.fin" id="justificatif-fin-erreur" class="erreur">{{ erreurs.fin }}</small>
          </div>
        </div>
      </fieldset>

      <div class="champ">
        <label for="justificatif-motif" class="champ__titre">Motif</label>
        <div class="suggestions" role="group" aria-label="Motifs fréquents">
          <button
              v-for="suggestion in MOTIFS"
              :key="suggestion"
              type="button"
              :class="['suggestion', { 'suggestion--choisie': motif === suggestion }]"
              :aria-pressed="motif === suggestion"
              @click="motif = motif === suggestion ? '' : suggestion"
          >{{ suggestion }}</button>
        </div>
        <Textarea
            id="justificatif-motif"
            v-model="motif"
            rows="3"
            auto-resize
            maxlength="2000"
            :invalid="!!erreurs.motif"
            :aria-describedby="erreurs.motif ? 'justificatif-motif-erreur' : undefined"
            fluid
        />
        <small v-if="erreurs.motif" id="justificatif-motif-erreur" class="erreur">{{ erreurs.motif }}</small>
      </div>

      <div class="champ">
        <span id="justificatif-piece-titre" class="champ__titre">Pièce justificative</span>
        <label
            v-if="!piece"
            for="justificatif-piece"
            :class="['depot', { 'depot--survol': survol, 'depot--erreur': erreurs.fichier }]"
            @dragover.prevent="survol = true"
            @dragleave="survol = false"
            @drop.prevent="deposerSurZone"
        >
          <i class="pi pi-cloud-upload depot__icone" aria-hidden="true"></i>
          <span class="depot__texte">Glissez le fichier ici ou <span class="depot__lien">choisissez-le</span></span>
          <span class="depot__aide">PDF, JPG ou PNG, 5 Mo au plus</span>
          <!-- Dans la zone : son focus clavier la fait ressortir (focus-within). -->
          <input
              id="justificatif-piece"
              ref="champPiece"
              type="file"
              class="sr-only"
              :accept="PIECE_JUSTIFICATIVE.types.join(',')"
              aria-labelledby="justificatif-piece-titre"
              :aria-describedby="erreurs.fichier ? 'justificatif-piece-erreur' : undefined"
              @change="choisirPiece($event.target.files?.[0])"
          />
        </label>
        <div v-if="piece" :class="['piece', { 'piece--erreur': erreurs.fichier }]">
          <i :class="['pi', piece.type === 'application/pdf' ? 'pi-file-pdf' : 'pi-image', 'piece__icone']" aria-hidden="true"></i>
          <span class="piece__texte">
            <span class="piece__nom">{{ piece.name }}</span>
            <span class="piece__taille">{{ taille(piece.size) }}</span>
          </span>
          <Button icon="pi pi-times" text rounded aria-label="Retirer la pièce" class="min-w-[44px]! min-h-[44px]!" @click="retirerPiece"/>
        </div>
        <small v-if="erreurs.fichier" id="justificatif-piece-erreur" class="erreur">{{ erreurs.fichier }}</small>
      </div>

      <p v-if="erreurs.general" class="erreur" role="alert">{{ erreurs.general }}</p>

      <div class="actions">
        <Button type="button" label="Annuler" severity="secondary" text class="min-h-[44px]!" @click="emit('fermer')"/>
        <Button type="submit" label="Déposer le justificatif" icon="pi pi-check" :loading="envoi" class="min-h-[44px]!"/>
      </div>
    </form>
  </Drawer>
</template>

<style scoped>
.formulaire {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.rappel {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0.75rem 1rem;
  border-radius: 14px;
  background: var(--p-seance-evaluation-background);
  color: var(--p-seance-evaluation-color);
}

.rappel__matiere {
  font-weight: 600;
}

.champ {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}

.champ__titre {
  padding: 0;
  font-weight: 600;
}

.champ__aide {
  margin: 0;
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
}

.periode {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
}

.periode__borne {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.suggestions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.suggestion {
  min-height: 44px;
  padding: 0 0.875rem;
  border: 1px solid var(--ui-border-color, var(--p-content-border-color));
  border-radius: 999px;
  background: transparent;
  color: var(--p-text-color);
  cursor: pointer;
  transition: background-color 150ms ease, border-color 150ms ease;
}

.suggestion:hover {
  background: var(--p-content-hover-background);
}

.suggestion--choisie {
  border-color: var(--p-primary-color);
  background: var(--p-seance-cm-background);
  color: var(--p-seance-cm-color);
  font-weight: 600;
}

.suggestion:focus-visible,
.depot:focus-within {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}

/* La zone est le libellé du champ fichier : un clic ou Entrée sur le champ ouvre le sélecteur. */
.depot {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  padding: 1.5rem 1rem;
  border: 2px dashed var(--ui-border-color, var(--p-content-border-color));
  border-radius: 14px;
  text-align: center;
  cursor: pointer;
  transition: background-color 150ms ease, border-color 150ms ease;
}

.depot:hover,
.depot--survol {
  border-color: var(--p-primary-color);
  background: var(--p-seance-cm-background);
}

.depot--erreur {
  border-color: var(--p-seance-evaluation-color);
}

.depot__icone {
  font-size: 1.75rem !important;
  color: var(--p-primary-color);
}

.depot__lien {
  font-weight: 600;
  color: var(--p-primary-color);
  text-decoration: underline;
}

.depot__aide {
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
}

.piece {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 0.5rem 0.5rem 1rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 14px;
}

.piece--erreur {
  border-color: var(--p-seance-evaluation-color);
}

.piece__icone {
  font-size: 1.5rem !important;
  color: var(--p-primary-color);
}

.piece__texte {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.piece__nom {
  overflow: hidden;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.piece__taille {
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
}

.erreur {
  color: var(--p-seance-evaluation-color);
}

.actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

@media (prefers-reduced-motion: reduce) {
  .suggestion,
  .depot {
    transition: none;
  }
}
</style>

