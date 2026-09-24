/**
 * Évaluations publiées du relevé de /api/me/scolarite, de la plus récente à la plus ancienne,
 * chacune avec la matière dont elle dépend.
 */
export function dernieresNotes(releve, nombre = 5) {
    const evaluations = (releve?.semestres ?? []).flatMap(semestre =>
        semestre.ues.flatMap(ue =>
            ue.enseignements.flatMap(enseignement =>
                enseignement.evaluations
                    .filter(evaluation => evaluation.etat === 'publiee')
                    .map(evaluation => ({
                        ...evaluation,
                        matiere: {code: enseignement.code, libelle: enseignement.libelle},
                    }))
            )
        )
    );

    return evaluations
        .sort((a, b) => (b.date ?? '').localeCompare(a.date ?? ''))
        .slice(0, nombre);
}
