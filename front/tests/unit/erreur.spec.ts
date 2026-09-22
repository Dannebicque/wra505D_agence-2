import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import PageErreur from '../../app/error.vue'

async function monter(statusCode: number) {
  return mountSuspended(PageErreur, { props: { error: { statusCode } } })
}

describe('page d erreur', () => {
  it('nomme un acces refuse', async () => {
    const composant = await monter(403)

    expect(composant.get('h1').text()).toBe('Acces refuse')
    expect(composant.text()).toContain('Erreur 403')
  })

  it('nomme une page introuvable', async () => {
    const composant = await monter(404)

    expect(composant.get('h1').text()).toBe('Page introuvable')
  })

  it('retombe sur un message de service indisponible pour le reste', async () => {
    const composant = await monter(500)

    expect(composant.get('h1').text()).toBe('Le service est momentanement indisponible')
  })

  it('propose toujours une sortie et un contact', async () => {
    const composant = await monter(404)

    expect(composant.text()).toContain('Revenir a mon portail')
    expect(composant.get('a').attributes('href')).toBe('mailto:intranet.iut-troyes@univ-reims.fr')
  })
})
