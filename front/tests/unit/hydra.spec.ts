import { describe, expect, it } from 'vitest'
import { collectionHydra, ressource } from '../../server/utils/hydra'

describe('collectionHydra', () => {
  it('rend une enveloppe conforme a API Platform', () => {
    const resultat = collectionHydra('Document', '/api/documents', [{ id: 1 }, { id: 2 }])

    expect(resultat).toEqual({
      '@context': '/api/contexts/Document',
      '@id': '/api/documents',
      '@type': 'Collection',
      totalItems: 2,
      member: [{ id: 1 }, { id: 2 }],
    })
  })

  it('compte zero membre sans se transformer en tableau vide', () => {
    const resultat = collectionHydra('EdtEvent', '/api/edt_events', [])

    expect(resultat.totalItems).toBe(0)
    expect(resultat.member).toEqual([])
  })
})

describe('ressource', () => {
  it("construit l'IRI attendue par les references entre ressources", () => {
    expect(ressource('StructureSemestre', 5, 'structure_semestres')).toEqual({
      '@id': '/api/structure_semestres/5',
      '@type': 'StructureSemestre',
      id: 5,
    })
  })
})
