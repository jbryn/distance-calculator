const API_URL = '**/api/calculate-distance'

const byTestId = (id: string) => cy.get(`[data-testid="${id}"]`)

const latitude = (pointNumber: 1 | 2) => byTestId(`latitude-${pointNumber}`)
const longitude = (pointNumber: 1 | 2) => byTestId(`longitude-${pointNumber}`)

const fillPoint = (pointNumber: 1 | 2, lat: string, lng: string) => {
  latitude(pointNumber).clear().type(lat)
  longitude(pointNumber).clear().type(lng)
}

const submitButton = () => byTestId('calculate-button')
const result = () => byTestId('distance-result')

describe('Distance calculator', () => {
  beforeEach(() => {
    cy.visit('/')
  })

  describe('calculating distance', () => {
    it('shows the distance between London and Paris', () => {
      fillPoint(1, '51.5074', '-0.1278')
      fillPoint(2, '48.8566', '2.3522')
      submitButton().click()

      result()
        .should('be.visible')
        .and('contain.text', 'Distance: 343.56 kilometers (343556.06 meters)')
    })

    it('accepts whole-number coordinates', () => {
      fillPoint(1, '52.0000', '21.0000')
      fillPoint(2, '50.0000', '19.0000')
      submitButton().click()

      result()
        .should('be.visible')
        .and('contain.text', 'Distance: 262.74 kilometers (262739.82 meters)')
    })

    it('shows zero distance for identical points', () => {
      fillPoint(1, '51.5074', '-0.1278')
      fillPoint(2, '51.5074', '-0.1278')
      submitButton().click()

      result().should('be.visible').and('contain.text', 'Distance: 0.00 kilometers (0.00 meters)')
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
      latitude(1).type('51.5').blur()

      byTestId('latitude-error-1')
        .should('be.visible')
        .and('contain.text', 'Must be a decimal number with 4-6 decimal places')
      submitButton().should('be.disabled')
    })

    it('shows an error for an out-of-range latitude', () => {
      latitude(1).type('91.0000').blur()

      byTestId('latitude-error-1')
        .should('be.visible')
        .and('contain.text', 'Latitude must be between -90 and 90 degrees')
    })

    it('shows an error for an out-of-range longitude', () => {
      longitude(1).type('181.0000').blur()

      byTestId('longitude-error-1')
        .should('be.visible')
        .and('contain.text', 'Longitude must be between -180 and 180 degrees')
    })

    it('clears the error once the value is corrected', () => {
      latitude(1).type('51.5').blur()
      byTestId('latitude-error-1').should('be.visible')

      latitude(1).clear().type('51.5074').blur()
      byTestId('latitude-error-1').should('not.exist')
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

      byTestId('form-error')
        .should('be.visible')
        .and('contain.text', 'Failed to calculate distance')
      result().should('not.exist')
    })

    it('shows an error when the API is unreachable', () => {
      cy.intercept('POST', API_URL, { forceNetworkError: true })
      submitButton().click()

      byTestId('form-error').should('be.visible')
      result().should('not.exist')
    })

    it('shows a loading state while the request is in flight', () => {
      cy.intercept('POST', API_URL, (req) => {
        req.on('response', (res) => {
          res.setDelay(500)
        })
      })
      submitButton().click()

      submitButton().should('be.disabled').and('contain.text', 'Calculating...')
      result().should('be.visible')
      submitButton().should('be.enabled').and('contain.text', 'Calculate Distance')
    })
  })
})
