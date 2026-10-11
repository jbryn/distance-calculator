const API_URL = '**/api/calculate-distance'

const fillPoint = (pointNumber: 1 | 2, latitude: string, longitude: string) => {
  cy.get(`#lat${pointNumber}`).clear().type(latitude)
  cy.get(`#lng${pointNumber}`).clear().type(longitude)
}

const submitButton = () => cy.get('button[type="submit"]')

describe('Distance calculator', () => {
  beforeEach(() => {
    cy.visit('/')
  })

  describe('calculating distance', () => {
    it('shows the distance between London and Paris', () => {
      fillPoint(1, '51.5074', '-0.1278')
      fillPoint(2, '48.8566', '2.3522')
      submitButton().click()

      cy.get('.result').should('have.text', 'Distance: 343.56 kilometers (343556.06 meters)')
    })

    it('accepts whole-number coordinates', () => {
      fillPoint(1, '52.0000', '21.0000')
      fillPoint(2, '50.0000', '19.0000')
      submitButton().click()

      cy.get('.result').should('have.text', 'Distance: 262.74 kilometers (262739.82 meters)')
    })

    it('shows zero distance for identical points', () => {
      fillPoint(1, '51.5074', '-0.1278')
      fillPoint(2, '51.5074', '-0.1278')
      submitButton().click()

      cy.get('.result').should('have.text', 'Distance: 0.00 kilometers (0.00 meters)')
    })

    it('sends the coordinates to the API as numbers', () => {
      cy.intercept('POST', API_URL).as('calculate')

      fillPoint(1, '51.5074', '-0.1278')
      fillPoint(2, '48.8566', '2.3522')
      submitButton().click()

      cy.wait('@calculate').then(({ request, response }) => {
        expect(request.body).to.deep.equal({
          lat1: 51.5074,
          lon1: -0.1278,
          lat2: 48.8566,
          lon2: 2.3522
        })
        expect(response?.statusCode).to.equal(200)
      })
    })
  })

  describe('validation', () => {
    it('disables the submit button until all fields are valid', () => {
      submitButton().should('be.disabled')

      fillPoint(1, '51.5074', '-0.1278')
      submitButton().should('be.disabled')

      fillPoint(2, '48.8566', '2.3522')
      submitButton().should('be.enabled')
    })

    it('shows an error for a value with too few decimal places', () => {
      cy.get('#lat1').type('51.5').blur()

      cy.contains('.error', 'Must be a decimal number with 4-6 decimal places').should('be.visible')
      submitButton().should('be.disabled')
    })

    it('shows an error for an out-of-range latitude', () => {
      cy.get('#lat1').type('91.0000').blur()

      cy.contains('.error', 'Latitude must be between -90 and 90 degrees').should('be.visible')
    })

    it('shows an error for an out-of-range longitude', () => {
      cy.get('#lng1').type('181.0000').blur()

      cy.contains('.error', 'Longitude must be between -180 and 180 degrees').should('be.visible')
    })

    it('clears the error once the value is corrected', () => {
      cy.get('#lat1').type('51.5').blur()
      cy.contains('.error', 'Must be a decimal number').should('be.visible')

      cy.get('#lat1').clear().type('51.5074').blur()
      cy.contains('.error', 'Must be a decimal number').should('not.exist')
    })
  })

  describe('API failures', () => {
    beforeEach(() => {
      fillPoint(1, '51.5074', '-0.1278')
      fillPoint(2, '48.8566', '2.3522')
    })

    it('shows an error when the API responds with a server error', () => {
      cy.intercept('POST', API_URL, {
        statusCode: 500,
        body: { status: 'error', message: 'Internal server error' }
      })
      submitButton().click()

      cy.contains('.error', 'Failed to calculate distance').should('be.visible')
      cy.get('.result').should('not.exist')
    })

    it('shows an error when the API is unreachable', () => {
      cy.intercept('POST', API_URL, { forceNetworkError: true })
      submitButton().click()

      cy.get('.form-container > .error').should('be.visible')
      cy.get('.result').should('not.exist')
    })

    it('shows a loading state while the request is in flight', () => {
      cy.intercept('POST', API_URL, (req) => {
        req.on('response', (res) => {
          res.setDelay(500)
        })
      })
      submitButton().click()

      submitButton().should('be.disabled').and('contain.text', 'Calculating...')
      cy.get('.result').should('be.visible')
      submitButton().should('be.enabled').and('contain.text', 'Calculate Distance')
    })
  })
})
