import { describe, expect, it } from 'vitest';
import type { Category } from '@types';
import { contientCategorie, idsDeLaCategorie } from './categorieUtils';

const categorie = (id: string, children: Category[] = []): Category => ({
  id,
  name: `Catégorie ${id}`,
  children,
  documentCount: 0,
  icon: 'pi pi-folder',
  color: 'bg-blue-500',
});

const arbre = [
  categorie('1', [categorie('2', [categorie('3')])]),
  categorie('4'),
];

describe('contientCategorie', () => {
  it('trouve une catégorie à n\'importe quelle profondeur', () => {
    expect(contientCategorie(arbre, '4')).toBe(true);
    expect(contientCategorie(arbre, '3')).toBe(true);
  });

  it('ne trouve ni une catégorie absente, ni l\'absence de catégorie', () => {
    expect(contientCategorie(arbre, '9')).toBe(false);
    expect(contientCategorie(arbre, null)).toBe(false);
  });

  it('ne cherche que dans les catégories données', () => {
    expect(contientCategorie(arbre[0].children ?? [], '3')).toBe(true);
    expect(contientCategorie(arbre[0].children ?? [], '4')).toBe(false);
  });
});

describe('idsDeLaCategorie', () => {
  it('rassemble la catégorie et toutes ses descendantes', () => {
    expect([...idsDeLaCategorie(arbre, '1')].sort()).toEqual(['1', '2', '3']);
    expect([...idsDeLaCategorie(arbre, '4')]).toEqual(['4']);
  });

  it('garde une catégorie inconnue seule, sans rien y ajouter', () => {
    expect([...idsDeLaCategorie(arbre, '9')]).toEqual(['9']);
  });
});
