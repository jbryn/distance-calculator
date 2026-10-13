# Geographic Distance Calculator

[![CI](https://github.com/jbryn/distance-calculator/actions/workflows/ci.yml/badge.svg)](https://github.com/jbryn/distance-calculator/actions/workflows/ci.yml)

A web application that calculates the distance between two geographic points using coordinates. Built with PHP backend (Slim Framework) and Vue.js frontend.

## Features

- Calculate distance between two geographic points using latitude and longitude
- Display results in both meters and kilometers
- Input validation for coordinate values
- CORS enabled for local development
- Error handling

## Technologies Used

### Backend

- PHP 8.x
- Slim Framework 4
- dotenv for environment variables
- Composer for dependency management

### Frontend

- Vue.js 3
- Vite as build tool
- Vitest for testing
- Cypress for end-to-end testing
- TypeScript

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

Returns the course of the river from source to mouth and its total length.

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
    "points": [[53.85058, 16.669742], [53.8505712, 16.6697115], "..."]
  }
}
```

#### Measure a section

**Endpoint:** `GET /api/rivers/{id}/section?from=<latitude>,<longitude>&to=<latitude>,<longitude>`

Snaps both points to the nearest place on the river and returns the stretch between them, following every bend.

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
    "points": [[53.3665124, 16.7965265], [53.3662421, 16.7954004], "..."]
  }
}
```

| Field | Meaning |
|---|---|
| `length` | Distance along the river between the two snapped points |
| `downstream` | `false` when `to` lies upstream of `from`; `points` then run against the current |
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
