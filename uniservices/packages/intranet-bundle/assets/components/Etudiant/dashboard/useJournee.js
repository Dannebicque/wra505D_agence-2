import {computed, onMounted, onUnmounted, ref} from 'vue';
import {getMesSemainesService, getMonEmploiDuTempsService} from '@requests';
import {getSituationDuJour, parseEdtDate} from '@/utils/prochainCours.js';
import {chargeDeLaSemaine, premierCoursApres, prochainesVacances} from '@/utils/accueil.js';

const isoDate = (date) => [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
const lundiDe = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate() - ((date.getDay() + 6) % 7));

/**
 * La journée de l'étudiant, lue une fois pour la carte de bienvenue et pour la colonne des cours :
 * deux lectures pourraient se contredire (TB-9).
 */
export function useJournee() {
    const cours = ref(null);
    const semaineFormation = ref(null);
    const suivant = ref(null);
    const semaines = ref([]);
    const hasError = ref(false);
    const maintenant = ref(new Date());
    let horloge = null;

    const lireSemaine = (lundi) => getMonEmploiDuTempsService(isoDate(lundi));

    onMounted(async () => {
        horloge = setInterval(() => {
            maintenant.value = new Date();
        }, 30000);

        // La charge de l'année ne sert qu'aux vacances : sans elle, la journée s'affiche quand même.
        getMesSemainesService()
            .then(reponse => {
                semaines.value = reponse.semaines ?? [];
            })
            .catch(() => {
                semaines.value = [];
            });

        const lundi = lundiDe(maintenant.value);
        try {
            const reponse = await lireSemaine(lundi);
            cours.value = reponse.cours;
            semaineFormation.value = reponse.semaineFormation;
        } catch {
            hasError.value = true;
            return;
        }
        // Le vendredi soir, le prochain cours est dans la semaine suivante : une seconde lecture,
        // seulement dans ce cas.
        suivant.value = premierCoursApres(cours.value, maintenant.value);
        if (!suivant.value) {
            try {
                const lundiSuivant = new Date(lundi);
                lundiSuivant.setDate(lundi.getDate() + 7);
                suivant.value = premierCoursApres((await lireSemaine(lundiSuivant)).cours, maintenant.value);
            } catch {
                suivant.value = null;
            }
        }
    });

    onUnmounted(() => {
        clearInterval(horloge);
    });

    const coursDuJour = computed(() => (cours.value ?? [])
        .map(seance => ({...seance, start: parseEdtDate(seance.debut), end: parseEdtDate(seance.fin), location: seance.salle}))
        .filter(seance => seance.start && isoDate(seance.start) === isoDate(maintenant.value))
        .sort((a, b) => a.start - b.start));

    const situation = computed(() => getSituationDuJour(coursDuJour.value, maintenant.value));

    const semaine = computed(() => chargeDeLaSemaine(cours.value ?? [], lundiDe(maintenant.value)));

    const evaluations = computed(() => (cours.value ?? [])
        .filter(seance => seance.evaluation)
        .map(seance => ({...seance, start: parseEdtDate(seance.debut), end: parseEdtDate(seance.fin)}))
        .filter(seance => seance.start)
        .sort((a, b) => a.start - b.start));

    const vacances = computed(() => prochainesVacances(semaines.value, maintenant.value));

    return {cours, coursDuJour, situation, suivant, semaine, semaines, semaineFormation, vacances, evaluations, hasError, maintenant};
}
