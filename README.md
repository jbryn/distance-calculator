# Geographic Distance Calculator

[![CI](https://github.com/jbryn/distance-calculator/actions/workflows/ci.yml/badge.svg)](https://github.com/jbryn/distance-calculator/actions/workflows/ci.yml)

A tool for planning kayaking trips: pick a section of a river and get the distance along its course, following every bend, rather than the straight line between two points. River geometry comes from OpenStreetMap. Built with a PHP backend (Slim Framework) and a Vue.js frontend.

The project started as a calculator for the straight-line distance between two coordinates and is being rebuilt around rivers. See [Status](#status) for what works today.

## Status

Work is tracked in the [Kayak river tool v1](https://github.com/jbryn/distance-calculator/milestone/1) milestone, one issue per pull request.

**Working now**

- River API: search rivers by name, fetch a river's course and length, and measure the section between two points snapped to the river (see [Rivers](#rivers))
- 15 Polish rivers, served from map data saved in the repository (see [`api/data`](api/data/README.md)); no request depends on an external service
- Distances checked against official river kilometrage from IMGW-PIB on 33 stretches between gauging stations: 30 within the reference tolerance (3%, or 6% on difficult reaches), 21 within 0.5%
- Detection of rivers whose map data has gaps; these are reported and not offered
- Obstacles (weirs, dams, locks, rapids, waterfalls) and access points (put-ins, slipways, bridges) along a river and on any section of it, each with its distance along the river
- The original straight-line calculator, in the API and in the web client

**Planned**

- Map interface: search for a river, click a start and a finish, see the section highlighted
- Trip details: kilometers or miles, estimated paddling time, obstacles on the route
- Shareable links and GPX export
- Docker setup and a public deployment

## Technologies Used

### Backend

- PHP 8.1+
- Slim Framework 4 with PHP-DI
- Guzzle for HTTP requests to the Overpass API (used only when refreshing river data)
- PHPUnit, PHPStan (strictest level) and PHP-CS-Fixer

### Frontend

- Vue.js 3 with TypeScript
- Vite as build tool
- Vitest for unit tests
- Cypress for end-to-end tests
- ESLint and Prettier

### Tooling

- GitHub Actions: tests on PHP 8.1 and 8.4, linting, static analysis and end-to-end tests on every pull request

## Setup and Installation

1. Clone the repository

```bash
git clone https://github.com/jbryn/distance-calculator.git
cd distance-calculator
```

2. Backend setup

```bash
cd api
composer install
```

3. Configure environment variables (optional)

Both parts run with sensible local defaults, so this step is only needed to change them.

```bash
cp api/.env.example api/.env
cp client/.env.example client/.env
```

| File | Variable | Default | Purpose |
|---|---|---|---|
| `api/.env` | `APP_DEBUG` | `false` | Include error details in API error responses |
| `api/.env` | `CORS_ORIGIN` | `http://localhost:5173` | Frontend origin allowed to call the API |
| `api/.env` | `OVERPASS_OFFLINE` | `true` | Answer only from the saved river data, never from the network. Turned off only to fetch or refresh that data (see [`api/data/README.md`](api/data/README.md)) |
| `api/.env` | `OVERPASS_CACHE_DIR` | `api/data/overpass` | Where the river data is saved |
| `api/.env` | `OVERPASS_ENDPOINTS` | two public servers | When not offline: OpenStreetMap (Overpass) servers to query, comma-separated, tried in order |
| `api/.env` | `OVERPASS_CACHE_TTL` | `2592000` (30 days) | When not offline: seconds saved data counts as fresh |
| `client/.env` | `VITE_API_URL` | `http://localhost:8080` | Base URL of the API |

If you run the API or the frontend on a different port, update the matching variable on the other side.

4. Frontend setup

```bash
cd client
npm install
```

## Running the Application

### Backend Development Server

```bash
cd api
php -S localhost:8080 -t public
```

The API will be available at `http://localhost:8080`

### Frontend Development Server

```bash
cd client
npm run dev
```

The frontend application will be available at `http://localhost:5173`

## Code Quality

| Command | Where | What it does |
|---|---|---|
| `composer lint` | `api/` | PHPStan static analysis (strictest level) and a PHP-CS-Fixer style check |
| `composer format` | `api/` | Fixes PHP code style |
| `npm run lint` | `client/` | ESLint for TypeScript and Vue |
| `npm run format` | `client/` | Formats the client with Prettier |

CI runs all the checks on every pull request.

## Testing

### Backend Tests

```bash
cd api
composer test
```

### Frontend Tests

Run the tests:

```bash
cd client
npm run test
```

Run tests with coverage report:

```bash
cd client
npm run test:coverage
```

### End-to-End Tests

Cypress tests drive the real frontend against the real API, so both development servers must be running first (see [Running the Application](#running-the-application)).

```bash
cd client
npm run e2e
```

Use `npm run e2e:open` to run them interactively in the Cypress app.

## API Endpoints

### Health Check

**Endpoint:** `GET /`

**Test with curl:**

```bash
curl http://localhost:8080/
```

**Response:**

```json
{
  "status": "API is working!"
}
```

### Calculate Distance

**Endpoint:** `POST /api/calculate-distance`

**Example Request Body:**

```json
{
  "lat1": 52.229676,
  "lon1": 21.012229,
  "lat2": 50.06465,
  "lon2": 19.94498
}
```

Latitudes must be between -90 and 90, longitudes between -180 and 180.

**Test with curl:**

```bash
curl -X POST http://localhost:8080/api/calculate-distance \
  -H 'Content-Type: application/json' \
  -d '{"lat1": 52.229676, "lon1": 21.012229, "lat2": 50.06465, "lon2": 19.94498}'
```

**Example Response:**

```json
{
  "status": "success",
  "data": {
    "meters": 251980.37,
    "kilometers": 251.98
  }
}
```

**Example Error Response (HTTP 400):**

```json
{
  "status": "error",
  "message": "Invalid latitude for lat1. Value must be between -90 and 90 degrees"
}
```

### Rivers

The API offers a fixed set of rivers whose map data is saved in [`api/data`](api/data/README.md). All distances are in meters, and every point is a `[latitude, longitude]` pair.

#### Search rivers

**Endpoint:** `GET /api/rivers?q=<text>`

Finds rivers whose name contains the text, ignoring case and Polish letters (`notec` finds Noteć). Without `q`, lists every river.

```bash
curl 'http://localhost:8080/api/rivers?q=wis'
```

```json
{
  "status": "success",
  "data": [
    { "id": 34392, "name": "Wisła" },
    { "id": 2020696, "name": "Wisłok" },
    { "id": 7577712, "name": "Wisłoka" }
  ]
}
```

#### Get a river

**Endpoint:** `GET /api/rivers/{id}`

Returns the course of the river from source to mouth, its total length, and the obstacles and access points along it in downstream order.

```bash
curl http://localhost:8080/api/rivers/8974372
```

```json
{
  "status": "success",
  "data": {
    "id": 8974372,
    "name": "Gwda",
    "length": 142928.6,
    "points": [[53.85058, 16.669742], [53.8505712, 16.6697115], "..."],
    "features": [
      {
        "kind": "bridge",
        "category": "access",
        "name": null,
        "point": [53.8505192, 16.6694025],
        "distanceFromSource": 23.8
      },
      "..."
    ]
  }
}
```

Each feature has:

| Field | Meaning |
|---|---|
| `kind` | `weir`, `dam`, `lock`, `rapids`, `waterfall`, `put_in`, `slipway` or `bridge` |
| `category` | `obstacle` (the first five kinds) or `access` (the last three) |
| `name` | Name from OpenStreetMap, or `null` |
| `point` | Where the feature meets the river |
| `distanceFromSource` | How far along the river that is |

Features come from OpenStreetMap and are only as complete as the map: the absence of an obstacle in the list does not mean the river is clear.

#### Measure a section

**Endpoint:** `GET /api/rivers/{id}/section?from=<latitude>,<longitude>&to=<latitude>,<longitude>`

Snaps both points to the nearest place on the river and returns the stretch between them, following every bend, with the features met on the way.

```bash
curl 'http://localhost:8080/api/rivers/8974372/section?from=53.3667,16.7964&to=53.0551,16.726'
```

```json
{
  "status": "success",
  "data": {
    "river": { "id": 8974372, "name": "Gwda" },
    "length": 57810.7,
    "downstream": true,
    "from": { "point": [53.3665124, 16.7965265], "distanceFromSource": 85111.3, "offset": 22.5 },
    "to": { "point": [53.0548549, 16.7260184], "distanceFromSource": 142922.1, "offset": 27.3 },
    "points": [[53.3665124, 16.7965265], [53.3662421, 16.7954004], "..."],
    "features": [
      {
        "kind": "bridge",
        "category": "access",
        "name": null,
        "point": [53.3646679, 16.7940129],
        "distance": 278.3
      },
      "..."
    ]
  }
}
```

| Field | Meaning |
|---|---|
| `length` | Distance along the river between the two snapped points |
| `downstream` | `false` when `to` lies upstream of `from`; `points` and `features` then run against the current |
| `features` | As for a river, limited to the section and in the order they are met; `distance` is measured from the start of the section |
| `point` | Where the given point landed on the river |
| `distanceFromSource` | How far along the river that is |
| `offset` | How far the given point was from the river |

#### Errors

| Status | When |
|---|---|
| `400` | `from` or `to` is missing, is not `latitude,longitude`, or is off the globe |
| `404` | The river is not one the API offers |
| `422` | A point is more than 2 km from the river |
| `503` | The river is offered but its saved data is missing or unusable |

```json
{
  "status": "error",
  "message": "The 'to' point is 301.0 km from the river Gwda; it must be within 2 km"
}
```
