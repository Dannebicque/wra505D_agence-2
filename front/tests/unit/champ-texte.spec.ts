import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import AppChampTexte from '../../app/components/AppChampTexte.vue'

describe('AppChampTexte', () => {
  it('associe le label au champ', async () => {
    const composant = await mountSuspended(AppChampTexte, {
      props: { label: 'Login', modelValue: '' },
    })

    const champ = composant.get('input')
    const label = composant.get('label')

    expect(label.attributes('for')).toBe(champ.attributes('id'))
  })

  it('signale l erreur au lecteur d ecran', async () => {
    const composant = await mountSuspended(AppChampTexte, {
      props: { label: 'Login', modelValue: '', erreur: 'Saisissez votre login.' },
    })

    const champ = composant.get('input')
    const message = composant.get('.erreur')

    expect(champ.attributes('aria-invalid')).toBe('true')
    expect(champ.attributes('aria-describedby')).toBe(message.attributes('id'))
    expect(message.text()).toBe('Saisissez votre login.')
  })

  it('ne porte ni aria-invalid ni describedby sans erreur', async () => {
    const composant = await mountSuspended(AppChampTexte, {
      props: { label: 'Login', modelValue: '' },
    })

    const champ = composant.get('input')

    expect(champ.attributes('aria-invalid')).toBeUndefined()
    expect(champ.attributes('aria-describedby')).toBeUndefined()
  })

  it('bascule l affichage du mot de passe sans changer son nom accessible', async () => {
    const composant = await mountSuspended(AppChampTexte, {
      props: { label: 'Mot de passe', type: 'password', modelValue: 'secret' },
    })

    const bascule = composant.get('.bascule')
    const libelle = bascule.text()

    expect(composant.get('input').attributes('type')).toBe('password')
    expect(bascule.attributes('aria-pressed')).toBe('false')

    await bascule.trigger('click')

    expect(composant.get('input').attributes('type')).toBe('text')
    expect(bascule.attributes('aria-pressed')).toBe('true')
    expect(bascule.text()).toBe(libelle)
  })
})
