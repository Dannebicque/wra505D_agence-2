function attendreHydratation() {
  cy.get('#__nuxt').should((element) => {
    const hote = element[0] as unknown as { __vue_app__?: unknown }
    expect(Boolean(hote.__vue_app__)).to.equal(true)
  })
}

describe('vue documentaire', () => {
  beforeEach(() => {
    cy.request('POST', '/api/login', { username: 'etudiant', password: 'test' })
    cy.visit('/documents')
    attendreHydratation()
  })

  it('annonce le nombre de documents', () => {
    cy.contains('h1', 'Documents').should('be.visible')
    cy.get('[role="status"]').should('contain', '240 documents trouves')
  })

  it('met les filtres dans l URL pour que la vue se partage', () => {
    cy.get('label').contains('Categorie').parent().find('select').select('Plannings')

    cy.location('search').should('contain', 'categorie=')
    cy.get('[role="status"]').should('contain', 'sur 240')
  })

  it('restitue une vue filtree depuis son URL', () => {
    cy.visit('/documents?categorie=4&tri=titre')
    attendreHydratation()

    cy.get('[role="status"]').should('contain', '30 documents trouves')
    cy.get('label').contains('Trier par').parent().find('select').should('have.value', 'titre')
  })

  it('explique l absence de resultat au lieu de laisser la page vide', () => {
    cy.get('label').contains('Mot cle').parent().find('input').type('zzzzzz')

    cy.get('[role="status"]').should('contain', '0 document trouve')
    cy.contains('Aucun document ne correspond a ces filtres').should('be.visible')
  })

  it('efface les filtres et revient a la liste complete', () => {
    cy.get('label').contains('Mot cle').parent().find('input').type('zzzzzz')
    cy.contains('button', 'Effacer les filtres').click()

    cy.location('search').should('eq', '')
    cy.get('[role="status"]').should('contain', '240 documents trouves')
  })

  it('masque les recemment ajoutes des qu un filtre est actif', () => {
    cy.contains('h2', 'Recemment ajoutes').should('be.visible')

    cy.get('label').contains('Mot cle').parent().find('input').type('convention')

    cy.contains('h2', 'Recemment ajoutes').should('not.exist')
  })
})
