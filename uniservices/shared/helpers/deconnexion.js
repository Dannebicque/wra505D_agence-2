/**
 * La déconnexion du menu du compte, partagée par la barre du haut et le panneau « Plus » du
 * téléphone : le jeton et l'année mémorisée effacés, le cookie expiré, puis retour à l'accueil.
 */
export const seDeconnecter = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('selectedAnneeUniv');
    document.cookie = 'token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    window.location.replace(`${window.location.origin}/?logout=true`);
};
