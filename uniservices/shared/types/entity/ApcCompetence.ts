import type { ApiResource } from './JsonLd';
import type { ApcReferentiel } from './ApcReferentiel';
import type { ApcNiveau } from './ApcNiveau';
import type { StructureUe } from './_placeholders';

export interface ApcComposanteEssentielle {
  libelle: string;
  code: string | null;
  ordre: number | null;
}

export interface ApcSituationProfessionnelle {
  libelle: string;
}

export interface ApcCompetenceFields {
  id?: number;
  libelle: string;
  nomCourt?: string | null;
  couleur?: string | null;
  referentiel?: (string | ApcReferentiel | null);
  niveaux?: (string[] | ApcNiveau[]);
  composantesEssentielles: ApcComposanteEssentielle[];
  situationsProfessionnelles: ApcSituationProfessionnelle[];
  ues?: (string[] | StructureUe[]);
}

export type ApcCompetence = ApiResource<ApcCompetenceFields>;
