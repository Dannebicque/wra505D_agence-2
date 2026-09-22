/*
 * Le contexte de securite est demande par l'en-tete sur chaque page et par le
 * portail. La cle partagee evite un appel par composant.
 */
export function useContexte() {
  return useAsyncData('contexte', () => useApi().contexteSecurite())
}
