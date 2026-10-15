import type { Map as LeafletMap } from 'leaflet'

const byTestId = (id: string) => cy.get(`[data-testid="${id}"]`)

const GWDA = 8974372

/** The IMGW gauging station at Ptusza */
const PTUSZA: [number, number] = [53.3667, 16.7964]
/** Where the Gwda joins the Noteć */
const MOUTH: [number, number] = [53.0551, 16.726]
/** Piła, the town on the lower Gwda */
const PILA: [number, number] = [53.151, 16.7383]

/** The Leaflet map, which the app exposes to tests in development builds */
const leafletMap = () =>
  cy.window().its('__downriverMap') as unknown as Cypress.Chainable<LeafletMap>

/** Screen position of a coordinate, relative to the map's top-left corner */
const pixelOf = (point: [number, number]) =>
  leafletMap().then((map) => map.latLngToContainerPoint(point))

const clickMapAt = (point: [number, number]) => {
  pixelOf(point).then(({ x, y }) => {
    byTestId('river-map').click(x, y)
  })
}

/** Drags a marker (by its title) to a coordinate, with the mouse events Leaflet listens to */
const dragMarker = (title: 'Start' | 'Finish', to: [number, number]) => {
  cy.get(`.leaflet-marker-icon[title="${title}"]`).then(($marker) => {
    const from = $marker[0].getBoundingClientRect()
    const startX = from.left + from.width / 2
    const startY = from.top + from.height / 2

    byTestId('river-map').then(($map) => {
      const box = $map[0].getBoundingClientRect()

      pixelOf(to).then(({ x, y }) => {
        const endX = box.left + x
        const endY = box.top + y

        // Moves go to the map element (Leaflet marks the event target during a drag), and bubble up to the document
        const moveTo = (clientX: number, clientY: number) =>
          byTestId('river-map').trigger('mousemove', { clientX, clientY, force: true })

        cy.wrap($marker).trigger('mousedown', {
          button: 0,
          which: 1,
          clientX: startX,
          clientY: startY
        })
        moveTo((startX + endX) / 2, (startY + endY) / 2)
        moveTo(endX, endY)
        byTestId('river-map').trigger('mouseup', { clientX: endX, clientY: endY, force: true })
      })
    })
  })
}

/**
 * Checks the section length shown. A click lands on a whole pixel, about 200 m apart at
 * this zoom, so the measured length can differ slightly from the one for exact coordinates.
 */
const expectLengthNear = (kilometers: number) => {
  byTestId('section-length').should(($length) => {
    const shown = Number.parseFloat($length.text())
    expect(shown).to.be.closeTo(kilometers, 0.3)
  })
}

const zoomOut = () => {
  cy.get('.leaflet-control-zoom-out').click()
}

describe('Selecting a section', () => {
  beforeEach(() => {
    cy.intercept('GET', 'https://tile.openstreetmap.org/**', { fixture: 'tile.png' })
    cy.viewport(1280, 900)
    cy.visit('/')

    byTestId(`river-option-${GWDA}`).click()
    cy.get('path.river-line').should('exist')
    // Wait for the map to finish zooming to the river before turning coordinates into pixels
    leafletMap().should((map) => {
      expect(map.getZoom()).to.be.greaterThan(7)
    })
  })

  it('guides the user through setting a start and a finish', () => {
    byTestId('section-hint').should('contain.text', 'set where you start')

    clickMapAt(PTUSZA)
    byTestId('section-hint').should('contain.text', 'Click the river again')
    cy.get('.leaflet-marker-icon[title="Start"]').should('be.visible')
    cy.get('.leaflet-marker-icon[title="Finish"]').should('not.exist')
  })

  it('measures a known stretch of the Gwda along the river', () => {
    clickMapAt(PTUSZA)
    byTestId('section-hint').should('contain.text', 'Click the river again')
    clickMapAt(MOUTH)

    // 57.81 km for the exact coordinates in the API's tests; IMGW's official figure is 57.55 km
    expectLengthNear(57.8)
    byTestId('section-length').should('contain.text', ' km')
    cy.get('path.section-line').should('have.length', 1)
    cy.get('.leaflet-marker-icon[title="Finish"]').should('be.visible')
    byTestId('section-upstream').should('not.exist')
  })

  it('moves the finish when its marker is dragged', () => {
    clickMapAt(PTUSZA)
    byTestId('section-hint').should('contain.text', 'Click the river again')
    clickMapAt(MOUTH)
    expectLengthNear(57.8)

    dragMarker('Finish', PILA)

    expectLengthNear(32.5)
  })

  it('swaps the start and the finish, and warns that the trip goes upstream', () => {
    clickMapAt(PTUSZA)
    byTestId('section-hint').should('contain.text', 'Click the river again')
    clickMapAt(MOUTH)
    expectLengthNear(57.8)
    byTestId('section-length')
      .invoke('text')
      .then((before) => {
        byTestId('section-swap').click()

        byTestId('section-upstream').should('contain.text', 'against the current')
        byTestId('section-length').should('have.text', before)
      })
  })

  it('clears the selection', () => {
    clickMapAt(PTUSZA)
    byTestId('section-hint').should('contain.text', 'Click the river again')
    clickMapAt(MOUTH)
    byTestId('section-length').should('be.visible')

    byTestId('section-clear').click()

    byTestId('section-hint').should('contain.text', 'set where you start')
    cy.get('path.section-line').should('not.exist')
    cy.get('.leaflet-marker-icon').should('not.exist')
  })

  it('starts a new selection on a third click', () => {
    clickMapAt(PTUSZA)
    byTestId('section-hint').should('contain.text', 'Click the river again')
    clickMapAt(MOUTH)
    byTestId('section-length').should('be.visible')

    clickMapAt(PILA)

    byTestId('section-hint').should('contain.text', 'Click the river again')
    cy.get('path.section-line').should('not.exist')
    cy.get('.leaflet-marker-icon[title="Finish"]').should('not.exist')
  })

  it('refuses a click far from the river and keeps what was selected', () => {
    clickMapAt(PTUSZA)
    byTestId('section-hint').should('contain.text', 'Click the river again')

    // 24.5 km east of the Gwda
    zoomOut()
    leafletMap().should((map) => {
      expect(map.getBounds().contains([53.3, 17.2])).to.equal(true)
    })
    clickMapAt([53.3, 17.2])

    byTestId('section-error').should('contain.text', 'km from the river Gwda')
    byTestId('section-hint').should('contain.text', 'Click the river again')
    cy.get('.leaflet-marker-icon[title="Finish"]').should('not.exist')
  })

  it('forgets the selection when another river is chosen', () => {
    clickMapAt(PTUSZA)
    byTestId('section-hint').should('contain.text', 'Click the river again')

    byTestId('change-river').click()
    byTestId('river-option-34392').click()

    byTestId('section-hint').should('contain.text', 'set where you start')
    cy.get('.leaflet-marker-icon').should('not.exist')
  })
})
