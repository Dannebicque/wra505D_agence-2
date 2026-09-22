import { describe, expect, it } from 'vitest'
import { formaterDate, formaterTaille } from '../../app/utils/format'

describe('formaterTaille', () => {
  it('reste en octets sous le kilo', () => {
    expect(formaterTaille(512)).toBe('512 o')
  })

  it('bascule en kilo-octets', () => {
    expect(formaterTaille(48_000)).toBe('47 ko')
  })

  it('bascule en mega-octets avec une decimale a la francaise', () => {
    expect(formaterTaille(2_400_000)).toBe('2,3 Mo')
  })

  it('ne se casse pas sur un fichier vide', () => {
    expect(formaterTaille(0)).toBe('0 o')
  })
})

describe('formaterDate', () => {
  it('rend une date longue en francais', () => {
    expect(formaterDate('2025-09-22T09:00:00+00:00')).toBe('22 septembre 2025')
  })

  it('ne decale pas la date selon le fuseau de la machine', () => {
    expect(formaterDate('2025-01-01T23:30:00+00:00')).toBe('2 janvier 2025')
  })
})
