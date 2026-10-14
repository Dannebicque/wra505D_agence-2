const CHAMPS_ADRESSE = ['adresse', 'complement1', 'complement2', 'ville', 'codePostal', 'pays'];

export const ADRESSES_ETUDIANT = ['adresseEtudiante', 'adresseParentale'];

// Le formulaire lie chaque champ d'adresse : une adresse absente ou incomplète reçoit des chaînes
// vides, pour que le champ ne parte pas de null.
export function normaliserAdresse(adresse) {
    return Object.fromEntries(CHAMPS_ADRESSE.map((champ) => [champ, adresse?.[champ] ?? '']));
}

// null, undefined et "" se valent : un champ jamais rempli puis laissé vide n'est pas une
// modification.
const valeurNormalisee = (valeur) => (valeur === null || valeur === undefined ? '' : valeur);

function adresseModifiee(avant, apres) {
    const champs = new Set([...Object.keys(avant ?? {}), ...Object.keys(apres ?? {})]);
    const modifiee = {};
    for (const champ of champs) {
        const valeur = (apres ?? {})[champ];
        if (JSON.stringify(valeurNormalisee((avant ?? {})[champ])) !== JSON.stringify(valeurNormalisee(valeur))) {
            modifiee[champ] = valeur;
        }
    }
    return modifiee;
}

// Le PATCH ne porte que ce qui a changé : une adresse ne transmet que ses champs modifiés, et les
// métadonnées JSON-LD ne partent jamais.
export function champsModifies(original, courant) {
    if (!original) {
        return {};
    }
    const avant = original;
    const apres = courant ?? {};
    const modifies = {};

    for (const cle of new Set([...Object.keys(avant), ...Object.keys(apres)])) {
        if (cle === '@id' || cle === '@type') {
            continue;
        }
        const a = avant[cle];
        const b = apres[cle];

        if (ADRESSES_ETUDIANT.includes(cle) && b && typeof b === 'object') {
            const adresse = adresseModifiee(a, b);
            if (Object.keys(adresse).length) {
                modifies[cle] = adresse;
            }
        } else if (Array.isArray(a) || Array.isArray(b)) {
            if (JSON.stringify(a ?? []) !== JSON.stringify(b ?? [])) {
                modifies[cle] = b;
            }
        } else if (b && typeof b === 'object') {
            if (JSON.stringify(a) !== JSON.stringify(b)) {
                modifies[cle] = b;
            }
        } else if (a !== b) {
            modifies[cle] = b;
        }
    }

    return modifies;
}

// Une adresse comme sur une enveloppe : une ligne par élément renseigné, le code postal devant la
// ville. Rien de renseigné donne une liste vide.
export function lignesAdresse(adresse) {
    if (!adresse) {
        return [];
    }
    const texte = (valeur) => (valeur === null || valeur === undefined ? '' : String(valeur).trim());
    return [
        texte(adresse.adresse),
        texte(adresse.complement1),
        texte(adresse.complement2),
        [texte(adresse.codePostal), texte(adresse.ville)].filter(Boolean).join(' '),
        texte(adresse.pays),
    ].filter(Boolean);
}

// Les initiales de la carte, quand la photo manque.
export function initiales(prenom, nom) {
    return [prenom, nom]
        .map((partie) => (typeof partie === 'string' ? partie.trim().charAt(0) : ''))
        .join('')
        .toUpperCase();
}

// Ce que la carte dit de l'année en cours : l'inscription active (sinon la plus récente) et son
// semestre actif (sinon le dernier), avec ses groupes dans l'ordre CM, TD, TP du semestre.
export function scolariteEnCours(scolarites) {
    if (!Array.isArray(scolarites) || scolarites.length === 0) {
        return null;
    }
    const scolarite = scolarites.find((sco) => sco.actif) ?? scolarites[0];
    const semestres = scolarite.semestres ?? [];
    const enCours = semestres.find((sem) => sem.semestre?.actif) ?? semestres[semestres.length - 1] ?? null;
    const types = enCours?.semestre?.typesGroupe ?? [];
    const groupes = [...(enCours?.groupes ?? [])].sort((a, b) => types.indexOf(a.type) - types.indexOf(b.type));

    return {
        anneeUniversitaire: scolarite.anneeUniversitaire?.libelle ?? null,
        formation: enCours?.semestre?.annee?.libelle ?? scolarite.annee?.[0]?.libelle ?? null,
        semestre: enCours?.semestre?.libelle ?? null,
        groupes: groupes.map(({ type, libelle }) => ({ type, libelle })),
    };
}

// Le jury tranche par un booléen ; tant qu'il ne l'a pas fait, la décision est en attente.
export function decisionJury(valeur) {
    if (valeur === true) {
        return { texte: 'Validé', ton: 'valide' };
    }
    return valeur === false ? { texte: 'Non validé', ton: 'non-valide' } : { texte: 'En attente', ton: 'attente' };
}

export function moyenneSur20(valeur) {
    if (valeur === null || valeur === undefined || Number.isNaN(Number(valeur))) {
        return null;
    }
    return `${Number(valeur).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} / 20`;
}
