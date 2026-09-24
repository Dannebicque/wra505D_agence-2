export function ordonnerDepartements(departements, departementEtudiantId) {
    const sien = departements.filter(departement => departement.id === departementEtudiantId);
    const autres = departements.filter(departement => departement.id !== departementEtudiantId);
    return [...sien, ...autres];
}
