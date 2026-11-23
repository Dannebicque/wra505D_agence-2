const types = ['CM', 'TD', 'TP', 'Projet'];
export const colonnesPrevisionnel = ['personnel', 'enseignement', ...types.flatMap((type) => [`heures_${type}`, `groupes_${type}`])];

/**
 * Découpe un CSV séparé par des points-virgules, champs entre guillemets compris (guillemet doublé
 * pour un guillemet). Les lignes vides sont ignorées.
 */
export function decouperCsv(texte) {
  const lignes = [];
  let ligne = [];
  let champ = '';
  let entreGuillemets = false;
  const source = texte.replace(/^\uFEFF/, '');
  for (let i = 0; i < source.length; i++) {
    const c = source[i];
    if (entreGuillemets) {
      if (c === '"' && source[i + 1] === '"') { champ += '"'; i++; }
      else if (c === '"') entreGuillemets = false;
      else champ += c;
    } else if (c === '"') entreGuillemets = true;
    else if (c === ';') { ligne.push(champ); champ = ''; }
    else if (c === '\n' || c === '\r') {
      if (c === '\r' && source[i + 1] === '\n') i++;
      ligne.push(champ); champ = '';
      if (ligne.some((valeur) => valeur.trim() !== '')) lignes.push(ligne);
      ligne = [];
    } else champ += c;
  }
  ligne.push(champ);
  if (ligne.some((valeur) => valeur.trim() !== '')) lignes.push(ligne);
  return lignes;
}

export async function lireCsvPrevisionnel(fichier) {
  if (!fichier || fichier.size > 2_000_000) throw new Error('Sélectionnez un fichier CSV de 2 Mo maximum.');
  const [entete, ...lignes] = decouperCsv(await fichier.text());
  const index = (entete ?? []).map((colonne) => String(colonne).trim());
  if (colonnesPrevisionnel.some((colonne) => index.filter((nom) => nom === colonne).length !== 1)) {
    throw new Error('Les colonnes du CSV doivent correspondre au modèle.');
  }
  if (!lignes.length || lignes.length > 2000) throw new Error('Le CSV doit contenir entre 1 et 2 000 lignes.');

  return lignes.map((ligne, position) => {
    const champ = (nom) => String(ligne[index.indexOf(nom)] ?? '').trim();
    const personnel = Number(champ('personnel'));
    const enseignement = champ('enseignement');
    if (!Number.isSafeInteger(personnel) || personnel <= 0 || !enseignement) {
      throw new Error(`Ligne ${position + 2} : personnel ou code d’enseignement invalide.`);
    }

    const heures = {};
    const groupes = {};
    for (const type of types) {
      heures[type] = Number(champ(`heures_${type}`).replace(',', '.'));
      groupes[type] = Number(champ(`groupes_${type}`));
      if (!Number.isFinite(heures[type]) || heures[type] < 0 || !Number.isSafeInteger(groupes[type]) || groupes[type] < 0) {
        throw new Error(`Ligne ${position + 2} : heures ou groupes invalides pour ${type}.`);
      }
    }
    return { personnel, enseignement, heures, groupes };
  });
}
