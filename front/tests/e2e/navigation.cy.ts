function attendreHydratation() {
  cy.get('#__nuxt').should((element) => {
    const hote = element[0] as unknown as { __vue_app__?: unknown }
    expect(Boolean(hote.__vue_app__)).to.equal(true)
  })
}

describe('navigation', () => {
  beforeEach(() => {
    cy.request('POST', '/api/login', { username: 'etudiant', password: 'test' })
    cy.visit('/')
    attendreHydratation()
  })

  it('mene du portail aux documents et retour', () => {
    cy.contains('a', 'Mes documents').click()
    cy.location('pathname').should('eq', '/documents')
    cy.contains('h1', 'Documents').should('be.visible')

    cy.contains('a', 'Mon portail').click()
    cy.location('pathname').should('eq', '/')
    cy.contains('h1', 'Portail').should('be.visible')
  })

  it('signale la page courante autrement que par la couleur', () => {
    cy.contains('a', 'Mon portail').should('have.attr', 'aria-current', 'page')
    cy.contains('a', 'Mes documents').should('not.have.attr', 'aria-current')

    cy.contains('a', 'Mes documents').click()

    cy.contains('a', 'Mes documents').should('have.attr', 'aria-current', 'page')
    cy.contains('a', 'Mon portail').should('not.have.attr', 'aria-current')
  })

  it('nomme l etudiant connecte', () => {
    cy.get('header').should('contain', 'Jane Doe')
  })

  it('offre un lien d evitement qui mene au contenu', () => {
    cy.contains('a', 'Aller au contenu').should('have.attr', 'href', '#contenu')
    cy.get('main#contenu').should('exist')
  })

  it('deconnecte et renvoie vers la connexion', () => {
    cy.contains('button', 'Se deconnecter').click()

    cy.location('pathname').should('eq', '/connexion')
    cy.request('/api/auth/me').its('body.authenticated').should('be.false')
  })

  it('n affiche pas la navigation sur la connexion', () => {
    cy.request('POST', '/api/logout')
    cy.visit('/connexion')

    cy.get('header').should('not.exist')
    cy.contains('Se deconnecter').should('not.exist')
  })
})
