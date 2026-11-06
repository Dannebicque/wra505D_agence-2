/**
 * Évaluations publiées du relevé de /api/me/scolarite, de la plus récente à la plus ancienne,
 * chacune avec la matière dont elle dépend.
 */
export function dernieresNotes(releve, nombre = 5) {
    // Le relevé couvre toutes les années depuis 2026 : l'accueil ne parle que de l'année en cours.
    // Une matière qui compte dans plusieurs UE y figure plusieurs fois : ses notes ne sortent qu'une fois.
    const evaluations = (releve?.semestres ?? []).filter(semestre => semestre.actif !== false).flatMap(semestre =>
        [...new Map(semestre.ues.flatMap(ue => ue.enseignements).map(enseignement => [enseignement.code, enseignement])).values()]
            .flatMap(enseignement =>
                enseignement.evaluations
                    .filter(evaluation => evaluation.etat === 'publiee')
                    .map(evaluation => ({
                        ...evaluation,
                        matiere: {code: enseignement.code, libelle: enseignement.libelle},
                    }))
            )
    );

    return evaluations
        .sort((a, b) => (b.date ?? '').localeCompare(a.date ?? ''))
        .slice(0, nombre);
}
