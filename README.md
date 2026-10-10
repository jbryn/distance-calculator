# Geographic Distance Calculator

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

3. Create and configure environment variables

```bash
cd api
touch .env
```

Add the following variables to your `.env` file:

```
APP_ENV=development
APP_DEBUG=true
```

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

## Testing

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
