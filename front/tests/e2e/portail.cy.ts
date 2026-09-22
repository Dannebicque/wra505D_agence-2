function attendreHydratation() {
  cy.get('#__nuxt').should((element) => {
    const hote = element[0] as unknown as { __vue_app__?: unknown }
    expect(Boolean(hote.__vue_app__)).to.equal(true)
  })
}

describe('portail', () => {
  it('renvoie vers la connexion sans session', () => {
    cy.request('POST', '/api/logout')
    cy.visit('/')

    cy.location('pathname').should('eq', '/connexion')
  })

  describe('une fois connecte', () => {
    beforeEach(() => {
      cy.request('POST', '/api/login', { username: 'etudiant', password: 'test' })
      cy.visit('/')
      attendreHydratation()
    })

    it('accueille l etudiant par son prenom', () => {
      cy.contains('h1', 'Portail').should('be.visible')
      cy.contains('Bonjour, Jane').should('be.visible')
    })

    it('affiche les widgets places, dans l ordre de leur position', () => {
      cy.get('section h3').should('have.length', 3)
      cy.get('section h3').eq(0).should('contain', "Aujourd'hui")
      cy.get('section h3').eq(1).should('contain', 'Progression portfolio')
      cy.get('section h3').eq(2).should('contain', 'Questionnaires en attente')
    })

    it('n affiche pas les widgets disponibles mais non places', () => {
      cy.contains('Documents recents').should('not.exist')
      cy.get('section h3').should('not.contain', 'Notes')
    })

    it('rend la progression lisible sans la couleur', () => {
      cy.get('[role="progressbar"]').should('have.attr', 'aria-valuenow', '62')
      cy.contains('62 %').should('be.visible')
    })
  })
})
