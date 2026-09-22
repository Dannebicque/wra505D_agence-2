describe('ecran de connexion', () => {
  beforeEach(() => {
    cy.request('POST', '/api/logout')
    cy.visit('/connexion')
    // La page est rendue par le serveur avant que Vue ne la reprenne : sans cette
    // attente, un clic immediat tombe sur du HTML sans gestionnaire.
    cy.get('#__nuxt').should((element) => {
      const hote = element[0] as unknown as { __vue_app__?: unknown }
      expect(Boolean(hote.__vue_app__)).to.equal(true)
    })
  })

  it('refuse une soumission vide et nomme ce qui manque', () => {
    cy.contains('button', 'Se connecter').click()

    cy.contains('Saisissez votre login.').should('be.visible')
    cy.contains('Saisissez votre mot de passe.').should('be.visible')
    cy.get('input[type="text"]').first().should('have.attr', 'aria-invalid', 'true')
    cy.location('pathname').should('eq', '/connexion')
  })

  it('connecte et renvoie vers l accueil', () => {
    cy.get('label').contains('Login').parent().find('input').type('etudiant')
    cy.get('label').contains('Mot de passe').parent().find('input').type('test')
    cy.contains('button', 'Se connecter').click()

    cy.location('pathname').should('eq', '/')
    cy.request('/api/auth/me').its('body.authenticated').should('be.true')
  })

  it('se soumet au clavier depuis le champ mot de passe', () => {
    cy.get('label').contains('Login').parent().find('input').type('etudiant')
    cy.get('label').contains('Mot de passe').parent().find('input').type('test{enter}')

    cy.location('pathname').should('eq', '/')
  })

  it('annonce l echec sur un mauvais mot de passe', () => {
    cy.get('label').contains('Login').parent().find('input').type('etudiant')
    cy.get('label').contains('Mot de passe').parent().find('input').type('mauvais')
    cy.contains('button', 'Se connecter').click()

    cy.get('[role="alert"]').should('contain', 'Login ou mot de passe incorrect.')
    cy.location('pathname').should('eq', '/connexion')
  })
})
