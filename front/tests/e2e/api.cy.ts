const collections = [
  '/api/structure_annee_universitaires',
  '/api/user/etudiant_scolarites',
  '/api/etudiant_scolarite_semestres',
  '/api/structure_semestres',
  '/api/scol_enseignements',
  '/api/etudiant_notes',
  '/api/absence/etudiant_scolarite_semestres',
  '/api/documents',
  '/api/document_categories',
  '/api/edt_events',
]

describe('points d entree de l API', () => {
  it('/api/etablissements repond une ressource unique, pas une collection', () => {
    cy.request('/api/etablissements').then((reponse) => {
      expect(reponse.status).to.eq(200)
      expect(reponse.body['@type']).to.eq('Etablissement')
      expect(reponse.body.libelle).to.be.a('string')
    })
  })

  collections.forEach((chemin) => {
    it(`${chemin} repond une collection Hydra`, () => {
      cy.request(chemin).then((reponse) => {
        expect(reponse.status).to.eq(200)
        expect(reponse.body['@type']).to.eq('Collection')
        expect(reponse.body.member).to.be.an('array')
        // Une page ne contient jamais plus que le total, et peut en contenir moins
        // des que la collection est paginee.
        expect(reponse.body.member.length).to.be.at.most(reponse.body.totalItems)
      })
    })
  })

  it('pagine les documents comme l API reelle', () => {
    cy.request('/api/documents').then((reponse) => {
      expect(reponse.body.totalItems).to.eq(240)
      expect(reponse.body.member).to.have.length(30)
      expect(reponse.body.view['@type']).to.eq('PartialCollectionView')
    })

    cy.request('/api/documents?itemsPerPage=5').its('body.member').should('have.length', 5)

    cy.request('/api/documents?pagination=false').then((reponse) => {
      expect(reponse.body.member).to.have.length(240)
      expect(reponse.body).to.not.have.property('view')
    })
  })

  it('ouvre puis ferme une session comme l API reelle', () => {
    cy.request('/api/auth/me').its('body.authenticated').should('be.false')

    cy.request('POST', '/api/login', { username: 'etudiant', password: 'test' })
      .its('status')
      .should('eq', 204)

    cy.request('/api/auth/me').its('body.username').should('eq', 'etudiant')

    cy.request('POST', '/api/logout').its('status').should('eq', 204)
    cy.request('/api/auth/me').its('body.authenticated').should('be.false')
  })
})
