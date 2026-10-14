<script setup>
import {nextTick, ref} from 'vue';
import {useToast} from 'primevue/usetoast';
import {updateEtudiantService} from '@requests';
import {ADRESSES_ETUDIANT, champsModifies, lignesAdresse, normaliserAdresse} from '@helpers/profil.js';
import {BlocAccueil, LigneAccueil, ValidatedInput, validationRules} from '@components';

const props = defineProps({
  etudiant: {type: Object, required: true},
});

const emit = defineEmits(['mis-a-jour']);
const edition = defineModel('edition', {type: Boolean, default: false});

const toast = useToast();

// On modifie une copie : « Annuler » rend la fiche telle qu'elle était, sans rien recharger.
const brouillon = ref(null);
const erreurs = ref({});
const enregistrement = ref(false);

const formulaireValide = () => Object.values(erreurs.value).every((erreur) => erreur === null);

// Le bouton « Modifier » disparaît pendant la saisie : le focus passe au premier champ, puis lui
// revient à la fermeture, pour qu'on ne se retrouve pas en haut de la page au clavier.
const boutonModifier = ref(null);

const ouvrir = async () => {
  const copie = JSON.parse(JSON.stringify(props.etudiant));
  for (const cle of ADRESSES_ETUDIANT) {
    copie[cle] = normaliserAdresse(copie[cle]);
  }
  brouillon.value = copie;
  erreurs.value = {};
  edition.value = true;
  await nextTick();
  document.getElementById('mailPerso')?.focus();
};

const fermer = async () => {
  edition.value = false;
  brouillon.value = null;
  await nextTick();
  boutonModifier.value?.$el?.focus();
};

const surValidation = (champ, resultat) => {
  erreurs.value = {...erreurs.value, [champ]: resultat.isValid ? null : resultat.errorMessage};
};

const enregistrer = async () => {
  if (!formulaireValide()) {
    toast.add({severity: 'error', summary: 'Erreur de validation', detail: 'Veuillez corriger les erreurs dans le formulaire', life: 5000});
    return;
  }
  const modifies = champsModifies(props.etudiant, brouillon.value);
  if (Object.keys(modifies).length === 0) {
    toast.add({severity: 'info', summary: 'Aucune modification', detail: 'Aucune donnée modifiée à enregistrer.', life: 3000});
    fermer();
    return;
  }
  enregistrement.value = true;
  try {
    await updateEtudiantService(props.etudiant.id, modifies);
    emit('mis-a-jour', brouillon.value);
    fermer();
  } catch {
    // apiCall a déjà signalé l'échec ; le formulaire reste ouvert pour ne pas perdre la saisie.
  } finally {
    enregistrement.value = false;
  }
};

const champsAdresse = [
  {cle: 'adresse', libelle: 'Adresse'},
  {cle: 'complement1', libelle: 'Complément d\'adresse'},
  {cle: 'complement2', libelle: 'Complément (suite)'},
  {cle: 'codePostal', libelle: 'Code postal'},
  {cle: 'ville', libelle: 'Ville'},
  {cle: 'pays', libelle: 'Pays'},
];

const adresses = [
  {cle: 'adresseEtudiante', titre: 'Adresse étudiante', prefixe: 'etudiant', icone: 'pi pi-home', obligatoire: true},
  {cle: 'adresseParentale', titre: 'Adresse parentale', prefixe: 'parental', icone: 'pi pi-users', obligatoire: false},
];

// L'adresse étudiante est obligatoire (adresse, code postal, ville), la parentale non ; le code
// postal suit le format français dans les deux cas.
const reglesAdresse = (adresse, champ) => {
  if (champ === 'codePostal') {
    return adresse.obligatoire ? [validationRules.postalCode, validationRules.required] : validationRules.postalCode;
  }
  return adresse.obligatoire && ['adresse', 'ville'].includes(champ) ? ['required'] : null;
};
</script>

<template>
  <BlocAccueil titre="Coordonnées" icone="pi pi-address-book">
    <template #actions>
      <Button
          v-if="!edition"
          ref="boutonModifier"
          label="Modifier"
          icon="pi pi-pencil"
          severity="secondary"
          variant="outlined"
          class="coordonnees__modifier"
          aria-label="Modifier mes coordonnées"
          @click="ouvrir"
      />
    </template>

    <p class="coordonnees__note">
      Ces informations ne sont visibles que de vous et de la direction du département. Merci de les
      tenir à jour : elles servent à vous faire parvenir vos relevés de notes.
    </p>

    <ul v-if="!edition" class="coordonnees__liste">
      <LigneAccueil icone="pi pi-envelope">
        <template #titre>Mail personnel</template>
        <template #valeur>
          <span v-if="etudiant.mailPerso">{{ etudiant.mailPerso }}</span>
          <span v-else class="coordonnees__vide">Non renseigné</span>
        </template>
      </LigneAccueil>
      <LigneAccueil icone="pi pi-phone">
        <template #titre>Téléphone</template>
        <template #valeur>
          <span v-if="etudiant.tel1">{{ etudiant.tel1 }}<template v-if="etudiant.tel2"> ou {{ etudiant.tel2 }}</template></span>
          <span v-else class="coordonnees__vide">Non renseigné</span>
        </template>
      </LigneAccueil>
      <LigneAccueil v-if="etudiant.site_perso" icone="pi pi-globe" :href="etudiant.site_perso">
        <template #titre>Site personnel</template>
        <template #detail>{{ etudiant.site_perso }}</template>
        <template #valeur><i class="pi pi-external-link" aria-hidden="true"></i></template>
      </LigneAccueil>
      <LigneAccueil v-else icone="pi pi-globe">
        <template #titre>Site personnel</template>
        <template #valeur><span class="coordonnees__vide">Non renseigné</span></template>
      </LigneAccueil>
      <LigneAccueil v-for="adresse in adresses" :key="adresse.cle" :icone="adresse.icone">
        <template #titre>{{ adresse.titre }}</template>
        <template #valeur>
          <span v-if="lignesAdresse(etudiant[adresse.cle]).length" class="coordonnees__adresse">
            <span v-for="(ligne, index) in lignesAdresse(etudiant[adresse.cle])" :key="index">{{ ligne }}</span>
          </span>
          <span v-else class="coordonnees__vide">Non renseignée</span>
        </template>
      </LigneAccueil>
    </ul>

    <form v-else class="coordonnees__formulaire" novalidate @submit.prevent="enregistrer">
      <p class="coordonnees__note">Les champs marqués d'un * sont obligatoires.</p>

      <fieldset class="coordonnees__groupe">
        <legend>Contact</legend>
        <div class="coordonnees__champs coordonnees__champs--contact">
          <ValidatedInput
              v-model="brouillon.mailPerso"
              name="mailPerso"
              label="Mail personnel"
              :rules="validationRules.email"
              @validation="resultat => surValidation('mailPerso', resultat)"
          />
          <ValidatedInput
              v-model="brouillon.site_perso"
              name="sitePerso"
              label="Site personnel"
              :rules="validationRules.url"
              help-text="Exemple : https://mon-site.com"
              @validation="resultat => surValidation('sitePerso', resultat)"
          />
          <ValidatedInput
              v-model="brouillon.tel1"
              name="tel1"
              label="Téléphone"
              :rules="validationRules.phone"
              @validation="resultat => surValidation('tel1', resultat)"
          />
          <ValidatedInput
              v-model="brouillon.tel2"
              name="tel2"
              label="Second téléphone"
              :rules="validationRules.phone"
              @validation="resultat => surValidation('tel2', resultat)"
          />
        </div>
      </fieldset>

      <fieldset v-for="adresse in adresses" :key="adresse.cle" class="coordonnees__groupe">
        <legend>{{ adresse.titre }}</legend>
        <div class="coordonnees__champs">
          <ValidatedInput
              v-for="champ in champsAdresse"
              :key="champ.cle"
              v-model="brouillon[adresse.cle][champ.cle]"
              :name="`${adresse.prefixe}-${champ.cle}`"
              :label="champ.libelle"
              :rules="reglesAdresse(adresse, champ.cle)"
              @validation="resultat => surValidation(`${adresse.prefixe}-${champ.cle}`, resultat)"
          />
        </div>
      </fieldset>

      <div class="coordonnees__boutons">
        <Button type="button" label="Annuler" severity="secondary" variant="outlined" @click="fermer"/>
        <Button type="submit" label="Enregistrer" icon="pi pi-check" :loading="enregistrement"/>
      </div>
    </form>
  </BlocAccueil>
</template>

<style scoped>
.coordonnees__note {
  margin: 0;
  font-size: 0.9375rem;
  color: var(--p-text-muted-color);
}

.coordonnees__liste {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
}

.coordonnees__vide {
  font-weight: 400;
  color: var(--p-text-muted-color);
}

.coordonnees__adresse {
  display: flex;
  flex-direction: column;
}

.coordonnees__modifier {
  min-height: 44px;
}

.coordonnees__formulaire {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.coordonnees__groupe {
  margin: 0;
  padding: 0;
  border: 0;
}

.coordonnees__groupe legend {
  margin-bottom: 0.5rem;
  font-size: 1rem;
  font-weight: 600;
}

.coordonnees__champs {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 0 1rem;
}

@media (min-width: 768px) {
  .coordonnees__champs {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (min-width: 1280px) {
  .coordonnees__champs {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .coordonnees__champs--contact {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

.coordonnees__boutons {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}

.coordonnees__boutons :deep(.p-button) {
  min-height: 44px;
}
</style>
