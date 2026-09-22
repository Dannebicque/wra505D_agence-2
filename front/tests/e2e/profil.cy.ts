function attendreHydratation() {
  cy.get('#__nuxt').should((element) => {
    const hote = element[0] as unknown as { __vue_app__?: unknown }
    expect(Boolean(hote.__vue_app__)).to.equal(true)
  })
}

describe('profil', () => {
  beforeEach(() => {
    cy.request('POST', '/api/login', { username: 'etudiant', password: 'test' })
    cy.visit('/profil')
    attendreHydratation()
  })

  it('affiche l identite servie par l API', () => {
    cy.contains('h1', 'Mon profil').should('be.visible')
    cy.contains('dt', 'Nom').next('dd').should('contain', 'Jane Doe')
    cy.contains('dt', 'Identifiant').next('dd').should('contain', 'etudiant')
    cy.contains('dt', 'Boursier').next('dd').should('contain', 'Non')
  })

  it('rend l adresse universitaire actionnable', () => {
    cy.contains('a', 'etudiant.user@etudiant.univ-reims.fr').should(
      'have.attr',
      'href',
      'mailto:etudiant.user@etudiant.univ-reims.fr',
    )
  })

  it('affiche l annee active et les groupes, sans repeter le type', () => {
    cy.contains('dt', 'Annee en cours').next('dd').should('contain', '2025/2026')
    cy.contains('dt', 'Mes groupes').next('dd').should('contain', 'CM, TD2 (TD), TP2B (TP)')
  })

  it('dit que les coordonnees ne sont pas modifiables plutot que d offrir un formulaire mort', () => {
    cy.contains('h2', 'Mes coordonnees personnelles').should('be.visible')
    cy.contains("n'est pas encore disponible").should('be.visible')
    cy.get('form').should('not.exist')
  })

  it('est atteignable depuis la navigation', () => {
    cy.visit('/')
    attendreHydratation()
    cy.contains('a', 'Mon profil').click()
    cy.location('pathname').should('eq', '/profil')
  })
})
