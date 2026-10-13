<script setup>
import { onMounted, ref } from 'vue';
import { HeaderComponent, GlobalLoader } from '@components';
import { getMonCahierDeTexteService } from '@requests';
import { formatNote } from '@/utils/scolarite.js';
import { delaiEcheance, formatHeures, jourEcheance } from '@/utils/cahierDeTexte.js';

const cahier = ref(null);
const chargement = ref(true);
const erreur = ref(false);

onMounted(async () => {
  try {
    cahier.value = await getMonCahierDeTexteService();
  } catch {
    erreur.value = true;
  } finally {
    chargement.value = false;
  }
});
</script>

<template>
  <HeaderComponent
      icon="pi pi-book"
      titre="Cahier de texte"
      description="Vos évaluations à venir et le contenu de vos cours"
  />

  <GlobalLoader v-if="chargement" />
  <Message v-else-if="erreur" severity="error">
    Votre cahier de texte n'a pas pu être chargé. Réessayez dans quelques instants.
  </Message>
  <Message v-else-if="!cahier?.anneeUniversitaire" severity="info">
    Aucune inscription n'est enregistrée pour l'année en cours.
  </Message>

  <template v-else>
    <section aria-labelledby="echeances" class="mb-8">
      <h2 id="echeances" class="mb-4 text-2xl font-bold">Échéances à venir</h2>
      <div class="card p-6">
        <p v-if="!cahier.echeances.length">Aucune évaluation à venir.</p>
        <ul v-else class="flex flex-col gap-3">
          <li
              v-for="echeance in cahier.echeances"
              :key="`${echeance.date}-${echeance.code}-${echeance.libelle}`"
              class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 border-b border-surface-200 pb-3 last:border-0 last:pb-0 dark:border-surface-700"
          >
            <span>
              <span class="block font-bold">{{ echeance.libelle }}</span>
              <span class="block">
                {{ echeance.code }} {{ echeance.matiere }}<template v-if="echeance.type"> · {{ echeance.type }}</template><template v-if="echeance.coefficient !== null"> · coef. {{ formatNote(echeance.coefficient) }}</template>
              </span>
            </span>
            <span class="sm:text-right">
              <span class="block font-bold">{{ delaiEcheance(echeance.date) }}</span>
              <time :datetime="echeance.date" class="block first-letter:uppercase">{{ jourEcheance(echeance.date) }}</time>
            </span>
          </li>
        </ul>
      </div>
    </section>

    <section aria-labelledby="plans-de-cours">
      <h2 id="plans-de-cours" class="mb-4 text-2xl font-bold">Plans de cours</h2>
      <Message v-if="!cahier.matieres.length" severity="info">
        Aucun plan de cours n'est encore disponible pour vos matières.
      </Message>
      <div v-else class="card p-6">
        <Accordion multiple>
          <AccordionPanel v-for="matiere in cahier.matieres" :key="matiere.code" :value="matiere.code">
            <AccordionHeader>
              <span class="flex w-full flex-wrap justify-between gap-x-4 gap-y-1 pr-4 text-left">
                <span class="font-medium">{{ matiere.code }} {{ matiere.libelle }}</span>
                <span>{{ matiere.type }}<template v-if="matiere.responsable"> · {{ matiere.responsable }}</template></span>
              </span>
            </AccordionHeader>
            <AccordionContent>
              <div class="flex flex-col gap-5">
                <div v-if="matiere.objectifs">
                  <h3 class="mt-0! mb-1! text-xl! font-bold">Objectifs</h3>
                  <p class="whitespace-pre-line">{{ matiere.objectifs }}</p>
                </div>
                <div v-if="matiere.modalitesEvaluations">
                  <h3 class="mt-0! mb-1! text-xl! font-bold">Évaluation</h3>
                  <p class="whitespace-pre-line">{{ matiere.modalitesEvaluations }}</p>
                </div>
                <div v-if="matiere.livrables">
                  <h3 class="mt-0! mb-1! text-xl! font-bold">Travaux à rendre</h3>
                  <p class="whitespace-pre-line">{{ matiere.livrables }}</p>
                </div>
                <div v-if="matiere.sequences.length || matiere.deroule">
                  <h3 class="mt-0! mb-1! text-xl! font-bold">Programme</h3>
                  <ol v-if="matiere.sequences.length" class="list-decimal pl-6">
                    <li v-for="sequence in matiere.sequences" :key="sequence.ordre">
                      {{ sequence.programme }}<template v-if="sequence.duree"> ({{ sequence.duree }})</template>
                    </li>
                  </ol>
                  <p v-else class="whitespace-pre-line">{{ matiere.deroule }}</p>
                </div>
                <div>
                  <h3 class="mt-0! mb-1! text-xl! font-bold">Séances réalisées</h3>
                  <p v-if="!matiere.seances.length">Aucune séance n'a encore été consignée.</p>
                  <ul v-else class="list-disc pl-6">
                    <li v-for="(seance, index) in matiere.seances" :key="index">
                      {{ seance.contenu }}<template v-if="formatHeures(seance.heures)"> ({{ formatHeures(seance.heures) }})</template>
                    </li>
                  </ul>
                </div>
              </div>
            </AccordionContent>
          </AccordionPanel>
        </Accordion>
      </div>
    </section>
  </template>
</template>
