import type { Document, DocumentEnseignement } from '@types';

export interface GroupeEnseignement {
  enseignement: DocumentEnseignement;
  documentCount: number;
}

export interface ClassementEnseignements {
  matieres: GroupeEnseignement[];
  saes: GroupeEnseignement[];
}

// Tri numérique pour que R1.02 passe avant R1.10.
const comparerCodes = (a: GroupeEnseignement, b: GroupeEnseignement): number =>
  a.enseignement.code.localeCompare(b.enseignement.code, 'fr', { numeric: true });

export const classerParEnseignement = (documents: Document[]): ClassementEnseignements => {
  const groupes = new Map<string, GroupeEnseignement>();

  for (const document of documents) {
    if (!document.enseignement) continue;

    const groupe = groupes.get(document.enseignement.id);
    if (groupe) {
      groupe.documentCount++;
    } else {
      groupes.set(document.enseignement.id, { enseignement: document.enseignement, documentCount: 1 });
    }
  }

  const tous = [...groupes.values()].sort(comparerCodes);

  return {
    matieres: tous.filter(g => g.enseignement.type !== 'sae'),
    saes: tous.filter(g => g.enseignement.type === 'sae')
  };
};

export const libelleEnseignement = (enseignement: DocumentEnseignement): string =>
  `${enseignement.code} ${enseignement.libelle}`.trim();
