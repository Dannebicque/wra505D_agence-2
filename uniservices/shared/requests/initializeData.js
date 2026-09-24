import { useAnneeUnivStore, useUsersStore, useDiplomeStore, useAnneeStore, useEtablissementStore } from '@stores';

export const initializeAppData = async () => {
  try {
    await initEtablissementData();
    await initAnneeUnivData();
  } catch (error) {
    console.error('Error initializing application data:', error);
  }
};

const initEtablissementData = async () => {
  const etablissementStore = useEtablissementStore();
  await etablissementStore.getEtablissement();
}

const initAnneeUnivData = async () => {
  const anneeUnivStore = useAnneeUnivStore();
  await anneeUnivStore.getAllAnneesUniv();

  // Check if a selected annee universitaire exists in localStorage
  const selectedAnneeUnivStr = localStorage.getItem('selectedAnneeUniv');
  const selectedAnneeUniv = selectedAnneeUnivStr ? JSON.parse(selectedAnneeUnivStr) : null;
  // Find the corresponding annee in the list to get the current actif status
  const foundAnnee = selectedAnneeUniv
    ? anneeUnivStore.anneesUniv.find(annee => annee.id === selectedAnneeUniv.id)
    : null;

  if (foundAnnee) {
    // Update the selected annee with the current actif status
    anneeUnivStore.setSelectedAnneeUniv({
      ...selectedAnneeUniv,
      isActif: foundAnnee.actif
    });
  } else {
    // Aucune année mémorisée, ou une année qui n'existe plus (base rechargée) : on prend l'année en cours.
    await anneeUnivStore.getCurrentAnneeUniv();

    // If we have a current annee, set it as selected
    if (anneeUnivStore.anneeUniv) {
      anneeUnivStore.setSelectedAnneeUniv(anneeUnivStore.anneeUniv);
    } else if (anneeUnivStore.anneesUniv.length > 0) {
      // Fallback to the first annee in the list if no current annee is found
      const sortedAnnees = [...anneeUnivStore.anneesUniv].sort((a, b) => b.libelle.localeCompare(a.libelle));
      anneeUnivStore.setSelectedAnneeUniv(sortedAnnees[0]);
    }
  }
};
