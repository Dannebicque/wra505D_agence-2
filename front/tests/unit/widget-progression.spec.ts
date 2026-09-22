import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import WidgetProgressionPortfolio from '../../app/components/WidgetProgressionPortfolio.vue'

describe('WidgetProgressionPortfolio', () => {
  it('expose la progression au lecteur d ecran', async () => {
    const composant = await mountSuspended(WidgetProgressionPortfolio, {
      props: { donnees: { validated: 62, target: 100 } },
    })

    const jauge = composant.get('[role="progressbar"]')

    expect(jauge.attributes('aria-valuenow')).toBe('62')
    expect(jauge.attributes('aria-valuemin')).toBe('0')
    expect(jauge.attributes('aria-valuemax')).toBe('100')
  })

  it('donne aussi la valeur en texte, sans dependre de la couleur', async () => {
    const composant = await mountSuspended(WidgetProgressionPortfolio, {
      props: { donnees: { validated: 3, target: 4 } },
    })

    expect(composant.text()).toContain('75 %')
    expect(composant.text()).toContain('3 sur 4')
  })

  it('ne divise pas par zero quand aucun objectif n est fixe', async () => {
    const composant = await mountSuspended(WidgetProgressionPortfolio, {
      props: { donnees: { validated: 0, target: 0 } },
    })

    expect(composant.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('0')
  })
})
