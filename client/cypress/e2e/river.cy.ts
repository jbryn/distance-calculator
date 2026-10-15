const byTestId = (id: string) => cy.get(`[data-testid="${id}"]`)

const GWDA = 8974372

/** Map tiles come from a fixture: the tests must not depend on, or load, OpenStreetMap's servers */
const stubTiles = () => {
  cy.intercept('GET', 'https://tile.openstreetmap.org/**', { fixture: 'tile.png' }).as('tile')
}

describe('Choosing a river', () => {
  beforeEach(() => {
    stubTiles()
    cy.visit('/')
  })

  it('lists the available rivers before anything is typed', () => {
    byTestId('search-results').find('li').should('have.length', 15)
    byTestId('search-results').should('contain.text', 'Gwda').and('contain.text', 'Wisła')
  })

  it('finds a river by name, ignoring Polish letters', () => {
    byTestId('river-query').type('notec')

    byTestId('search-results').find('li').should('have.length', 1).and('have.text', 'Noteć')
  })

  it('says when no river matches', () => {
    byTestId('river-query').type('amazon')

    byTestId('search-empty').should('contain.text', 'No rivers match “amazon”.')
  })

  it('shows the chosen river on the map and its details in the panel', () => {
    byTestId('river-query').type('gwda')
    byTestId(`river-option-${GWDA}`).click()

    byTestId('river-name').should('have.text', 'Gwda')
    byTestId('river-length').should('have.text', '142.9 km')
    byTestId('river-obstacles').should('have.text', '16')
    byTestId('river-access-points').should('have.text', '36')

    cy.get('[data-testid="river-map"] path.river-line').should('have.length', 1)
    cy.wait('@tile')
  })

  it('fits the map to the river', () => {
    byTestId(`river-option-${GWDA}`).click()
    byTestId('river-name').should('be.visible')

    // Retried until the zoom animation has finished
    cy.get('path.river-line').should(($line) => {
      const line = $line[0].getBoundingClientRect()
      const map = Cypress.$('[data-testid="map-area"]')[0].getBoundingClientRect()

      expect(line.top).to.be.at.least(map.top)
      expect(line.bottom).to.be.at.most(map.bottom)
      expect(line.left).to.be.at.least(map.left)
      expect(line.right).to.be.at.most(map.right)
      // Fitted: zoomed in until the river fills a good part of the map in one direction
      expect(Math.max(line.width / map.width, line.height / map.height)).to.be.greaterThan(0.25)
    })
  })

  it('goes back to the list to choose another river', () => {
    byTestId(`river-option-${GWDA}`).click()
    byTestId('river-name').should('be.visible')

    byTestId('change-river').click()
    byTestId('river-query').type('wisłok')
    byTestId('river-option-2020696').click()

    byTestId('river-name').should('have.text', 'Wisłok')
    cy.get('path.river-line').should('have.length', 1)
  })

  it('removes the river from the map when going back', () => {
    byTestId(`river-option-${GWDA}`).click()
    cy.get('path.river-line').should('exist')

    byTestId('change-river').click()

    cy.get('path.river-line').should('not.exist')
  })

  it('keeps the page the size of the window once a river is shown', () => {
    byTestId(`river-option-${GWDA}`).click()
    cy.get('path.river-line').should('exist')

    // The map must keep Leaflet's own classes, which clip it to its box
    cy.get('.leaflet-container').should('exist')
    cy.document().should((document) => {
      expect(document.documentElement.scrollHeight).to.equal(document.documentElement.clientHeight)
    })
  })

  it('credits OpenStreetMap on the map', () => {
    cy.get('.leaflet-control-attribution').should('contain.text', 'OpenStreetMap')
  })
})

describe('Choosing a river when the API fails', () => {
  beforeEach(() => {
    stubTiles()
  })

  it('shows a search error and recovers on retry', () => {
    // Only the first search fails
    cy.intercept(
      { method: 'GET', url: '**/api/rivers?q=*', times: 1 },
      { statusCode: 500, body: { status: 'error', message: 'Internal server error' } }
    )
    cy.visit('/')

    byTestId('search-error').should('contain.text', 'Internal server error')

    byTestId('search-retry').click()

    byTestId('search-results').find('li').should('have.length', 15)
  })

  it('shows why a river cannot be loaded', () => {
    cy.intercept('GET', `**/api/rivers/${GWDA}`, {
      statusCode: 503,
      body: { status: 'error', message: 'The data for this river is unavailable' }
    })
    cy.visit('/')

    byTestId(`river-option-${GWDA}`).click()

    byTestId('river-error').should('contain.text', 'The data for this river is unavailable')
    cy.get('path.river-line').should('not.exist')
  })

  it('shows that the server cannot be reached', () => {
    cy.intercept('GET', '**/api/rivers?q=*', { forceNetworkError: true })
    cy.visit('/')

    byTestId('search-error').should('contain.text', 'Could not reach the server')
  })
})
