import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import WidgetEmploiDuTemps from '../../app/components/WidgetEmploiDuTemps.vue'

describe('WidgetEmploiDuTemps', () => {
  it('liste les cours du jour', async () => {
    const composant = await mountSuspended(WidgetEmploiDuTemps, {
      props: {
        donnees: {
          todayLabel: 'mardi 22 septembre 2026',
          items: [{ heure: '08:00 - 10:00', cours: 'SAE 501' }],
        },
      },
    })

    expect(composant.findAll('li')).toHaveLength(1)
    expect(composant.text()).toContain('08:00 - 10:00')
    expect(composant.text()).toContain('SAE 501')
  })

  it('dit explicitement qu il n y a pas cours plutot que de rester vide', async () => {
    const composant = await mountSuspended(WidgetEmploiDuTemps, {
      props: { donnees: { todayLabel: 'mardi 22 septembre 2026', items: [] } },
    })

    expect(composant.findAll('li')).toHaveLength(0)
    expect(composant.text()).toContain("Aucun cours aujourd'hui.")
  })
})
