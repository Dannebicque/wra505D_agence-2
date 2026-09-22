function attendreHydratation() {
  cy.get('#__nuxt').should((element) => {
    const hote = element[0] as unknown as { __vue_app__?: unknown }
    expect(Boolean(hote.__vue_app__)).to.equal(true)
  })
}

describe('page d erreur', () => {
  beforeEach(() => {
    cy.request('POST', '/api/login', { username: 'etudiant', password: 'test' })
  })

  it('remplace l ecran par defaut de Nuxt sur une adresse inconnue', () => {
    cy.visit('/cette-page-n-existe-pas', { failOnStatusCode: false })
    attendreHydratation()

    cy.contains('h1', 'Page introuvable').should('be.visible')
    cy.contains('Erreur 404').should('be.visible')
  })

  it('ramene au portail', () => {
    cy.visit('/cette-page-n-existe-pas', { failOnStatusCode: false })
    attendreHydratation()

    cy.contains('button', 'Revenir a mon portail').click()

    cy.location('pathname').should('eq', '/')
    cy.contains('h1', 'Portail').should('be.visible')
  })
})
