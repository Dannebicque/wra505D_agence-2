<script setup>
import { onMounted, ref } from 'vue';
import { HeaderComponent, GlobalLoader } from '@components';
import { getMaScolariteService } from '@requests';
import { creneauAbsence, formatDate, formatNote, justificationAbsence, resultatEvaluation } from '@/utils/scolarite.js';

const releve = ref(null);
const chargement = ref(true);
const erreur = ref(false);

onMounted(async () => {
  try {
    releve.value = await getMaScolariteService();
  } catch {
    erreur.value = true;
  } finally {
    chargement.value = false;
  }
});
</script>

<template>
  <HeaderComponent
      icon="pi pi-graduation-cap"
      titre="Scolarité"
      description="Vos notes, vos moyennes et vos absences de l'année en cours"
  />

  <GlobalLoader v-if="chargement" />
  <Message v-else-if="erreur" severity="error">
    Votre scolarité n'a pas pu être chargée. Réessayez dans quelques instants.
  </Message>
  <Message v-else-if="!releve?.semestres?.length" severity="info">
    Aucune inscription n'est enregistrée pour l'année en cours.
  </Message>

  <template v-else>
    <Message severity="info" class="mb-6">
      Les moyennes sont provisoires : elles sont calculées à partir des notes publiées, et ne deviennent
      définitives qu'après la validation en sous-commission.
    </Message>

    <section
        v-for="semestre in releve.semestres"
        :key="semestre.libelle"
        :aria-labelledby="`semestre-${semestre.libelle}`"
        class="mb-8 flex flex-col gap-6"
    >
      <h2 :id="`semestre-${semestre.libelle}`" class="text-2xl font-bold">
        Semestre {{ semestre.libelle }}, {{ releve.anneeUniversitaire }}
      </h2>

      <div v-for="ue in semestre.ues" :key="ue.numero" class="card p-6">
        <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
          <h3 class="text-xl font-bold">{{ ue.libelle }}</h3>
          <p>
            Moyenne provisoire :
            <strong class="text-lg">{{ formatNote(ue.moyenne) }}</strong><span v-if="ue.moyenne !== null"> / 20</span>
          </p>
        </div>

        <Accordion multiple>
          <AccordionPanel v-for="enseignement in ue.enseignements" :key="enseignement.code" :value="enseignement.code">
            <AccordionHeader>
              <span class="flex w-full flex-wrap justify-between gap-x-4 gap-y-1 pr-4 text-left">
                <span class="font-medium">{{ enseignement.code }} {{ enseignement.libelle }}</span>
                <span>
                  coef. {{ formatNote(enseignement.coefficient) }} ·
                  moyenne {{ formatNote(enseignement.moyenne) }}<span v-if="enseignement.moyenne !== null"> / 20</span>
                </span>
              </span>
            </AccordionHeader>
            <AccordionContent>
              <p v-if="!enseignement.evaluations.length">Aucune évaluation pour l'instant.</p>
              <table v-else class="w-full text-left">
                <caption class="sr-only">Évaluations de {{ enseignement.code }} {{ enseignement.libelle }}</caption>
                <thead>
                  <tr>
                    <th scope="col" class="py-2 pr-4">Évaluation</th>
                    <th scope="col" class="py-2 pr-4">Date</th>
                    <th scope="col" class="py-2 pr-4">Coef.</th>
                    <th scope="col" class="py-2">Résultat</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="evaluation in enseignement.evaluations" :key="`${evaluation.libelle}-${evaluation.date}`">
                    <th scope="row" class="py-2 pr-4 font-normal">{{ evaluation.libelle }}</th>
                    <td class="py-2 pr-4">{{ formatDate(evaluation.date) }}</td>
                    <td class="py-2 pr-4">{{ formatNote(evaluation.coefficient) }}</td>
                    <td class="py-2">
                      <Tag
                          v-if="resultatEvaluation(evaluation).severite"
                          :severity="resultatEvaluation(evaluation).severite"
                          :value="resultatEvaluation(evaluation).texte"
                      />
                      <strong v-else>{{ resultatEvaluation(evaluation).texte }}</strong>
                    </td>
                  </tr>
                </tbody>
              </table>
            </AccordionContent>
          </AccordionPanel>
        </Accordion>
      </div>

      <div class="card p-6">
        <h3 class="mb-2 text-xl font-bold">Absences</h3>
        <p v-if="!semestre.absences.total">Aucune absence ce semestre.</p>
        <template v-else>
          <p class="mb-4">
            {{ semestre.absences.total }} absence{{ semestre.absences.total > 1 ? 's' : '' }} :
            {{ semestre.absences.justifiees }} justifiée{{ semestre.absences.justifiees > 1 ? 's' : '' }},
            {{ semestre.absences.injustifiees }} non justifiée{{ semestre.absences.injustifiees > 1 ? 's' : '' }},
            {{ semestre.absences.enAttente }} en attente de justificatif.
          </p>
          <ul class="flex flex-col gap-3">
            <li
                v-for="absence in semestre.absences.liste"
                :key="absence.debut"
                class="flex flex-wrap items-center justify-between gap-2 border-b border-surface-200 pb-3 last:border-0 dark:border-surface-700"
            >
              <span>
                <span class="block font-medium">{{ absence.matiere || 'Cours' }}</span>
                <span class="block">{{ creneauAbsence(absence) }}</span>
              </span>
              <Tag
                  :severity="justificationAbsence(absence.justification).severite"
                  :value="justificationAbsence(absence.justification).texte"
              />
            </li>
          </ul>
        </template>
      </div>
    </section>
  </template>
</template>
