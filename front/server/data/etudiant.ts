import { ressource } from '../utils/hydra'

/*
 * Groupe de serialisation servi a l'etudiant sur sa propre fiche, releve sur l'API
 * reelle. Ni mailPerso, ni telephone, ni adresse : ils sont modifiables mais pas
 * lisibles, l'asymetrie vient de l'API.
 *
 * `applications` vaut ici « UniTranet », la ou le contexte de securite renvoie
 * « intranet » et le catalogue de widgets « intranet ». Trois vocabulaires pour la
 * meme notion : on recopie, on ne rapproche pas.
 */
export const fiche = {
  '@context': '/api/contexts/Etudiant',
  ...ressource('Etudiant', 12, 'etudiants'),
  username: 'etudiant',
  prenom: 'Jane',
  nom: 'Doe',
  display: 'Jane Doe',
  mailUniv: 'etudiant.user@etudiant.univ-reims.fr',
  photoName: 'noimage.png',
  roles: ['ROLE_ETUDIANT'],
  boursier: false,
  annee_sortie: 0,
  applications: ['UniTranet'],
  groupes: [
    { '@id': '/api/structure_groupes/12', libelle: 'CM', type: 'CM' },
    { '@id': '/api/structure_groupes/13', libelle: 'TD2', type: 'TD' },
    { '@id': '/api/structure_groupes/14', libelle: 'TP2B', type: 'TP' },
  ],
  scolarites: [
    {
      '@id': '/api/etudiant_scolarites/40',
      '@type': 'EtudiantScolarite',
      id: 40,
      ordre: 1,
      public: true,
      anneeUniversitaire: '/api/structure_annee_universitaires/1',
      actif: false,
    },
    {
      '@id': '/api/etudiant_scolarites/41',
      '@type': 'EtudiantScolarite',
      id: 41,
      ordre: 2,
      public: true,
      anneeUniversitaire: '/api/structure_annee_universitaires/2',
      actif: true,
    },
  ],
}
