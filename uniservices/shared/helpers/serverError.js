// La page /500 est atteinte par une navigation complète, comme un rechargement : seul
// sessionStorage permet de distinguer l'erreur qui vient d'arriver d'un rechargement après coup.
const PENDING_KEY = 'serverErrorPending';
const RETURN_PATH_KEY = 'serverErrorReturnPath';
const DEBUG_MESSAGE_KEY = 'lastServerErrorDebugMessage';
const ERROR_PATH = '/500';
const FALLBACK_PATH = '/';

const isErrorPath = (path) => path === ERROR_PATH || path.startsWith(`${ERROR_PATH}/`) || path.startsWith(`${ERROR_PATH}?`);

/**
 * Mémorise l'erreur et la page où elle est survenue, avant la redirection vers /500.
 * returnPath est relatif à la base du routeur (/app).
 */
export const rememberServerError = (storage, returnPath, debugMessage) => {
    try {
        storage.setItem(PENDING_KEY, '1');
        storage.setItem(DEBUG_MESSAGE_KEY, debugMessage);
        if (returnPath && !isErrorPath(returnPath)) {
            storage.setItem(RETURN_PATH_KEY, returnPath);
        }
    } catch (_) {
        // Ne pas bloquer la redirection si le stockage est indisponible
    }
};

/**
 * Garde de la route /500 : affiche la page juste après une erreur, et renvoie sur la page
 * d'origine quand on la recharge. Si l'erreur persiste, cette page y ramènera d'elle-même.
 */
export const resolveServerErrorRoute = (storage) => {
    try {
        if (storage.getItem(PENDING_KEY)) {
            storage.removeItem(PENDING_KEY);
            return true;
        }

        const returnPath = storage.getItem(RETURN_PATH_KEY);
        storage.removeItem(RETURN_PATH_KEY);
        return returnPath && !isErrorPath(returnPath) ? returnPath : FALLBACK_PATH;
    } catch (_) {
        return true;
    }
};

export const lastServerErrorDebugMessage = (storage) => {
    try {
        return storage.getItem(DEBUG_MESSAGE_KEY) || '';
    } catch (_) {
        return '';
    }
};
