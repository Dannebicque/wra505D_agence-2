import { describe, expect, it } from 'vitest';
import { lastServerErrorDebugMessage, rememberServerError, resolveServerErrorRoute } from './serverError.js';

const memoryStorage = () => {
    const values = new Map();
    return {
        getItem: (key) => (values.has(key) ? values.get(key) : null),
        setItem: (key, value) => values.set(key, String(value)),
        removeItem: (key) => values.delete(key),
    };
};

const brokenStorage = {
    getItem: () => { throw new Error('indisponible'); },
    setItem: () => { throw new Error('indisponible'); },
    removeItem: () => { throw new Error('indisponible'); },
};

describe('serverError', () => {
    it('affiche la page d\'erreur juste après l\'erreur', () => {
        const storage = memoryStorage();
        rememberServerError(storage, '/intranet/scolarite?semestre=2', 'HTTP 500');

        expect(resolveServerErrorRoute(storage)).toBe(true);
        expect(lastServerErrorDebugMessage(storage)).toBe('HTTP 500');
    });

    it('renvoie sur la page d\'origine quand on recharge la page d\'erreur', () => {
        const storage = memoryStorage();
        rememberServerError(storage, '/intranet/scolarite?semestre=2', 'HTTP 500');
        resolveServerErrorRoute(storage);

        expect(resolveServerErrorRoute(storage)).toBe('/intranet/scolarite?semestre=2');
    });

    it('renvoie sur l\'accueil quand la page d\'origine est inconnue', () => {
        const storage = memoryStorage();

        expect(resolveServerErrorRoute(storage)).toBe('/');
    });

    it('n\'offre le retour qu\'une fois', () => {
        const storage = memoryStorage();
        rememberServerError(storage, '/documents', 'HTTP 500');
        resolveServerErrorRoute(storage);
        resolveServerErrorRoute(storage);

        expect(resolveServerErrorRoute(storage)).toBe('/');
    });

    it('ne renvoie jamais sur la page d\'erreur elle-même', () => {
        const storage = memoryStorage();
        storage.setItem('serverErrorReturnPath', '/500');

        expect(resolveServerErrorRoute(storage)).toBe('/');
    });

    it('affiche la page d\'erreur sans stockage disponible', () => {
        expect(() => rememberServerError(brokenStorage, '/documents', 'HTTP 500')).not.toThrow();
        expect(resolveServerErrorRoute(brokenStorage)).toBe(true);
        expect(lastServerErrorDebugMessage(brokenStorage)).toBe('');
    });
});
