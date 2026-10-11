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
