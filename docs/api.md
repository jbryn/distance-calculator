# API reference

The API serves JSON on `http://localhost:8080` when started with `composer start` in `api/`.

Every response has a `status` of `success` (with the result in `data`) or `error` (with a `message`). All distances are in meters, and every point is a `[latitude, longitude]` pair.

## Health Check

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

## Rivers

The API offers a fixed set of rivers whose map data is saved in [`api/data`](../api/data/README.md).

### Search rivers

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

### Get a river

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

### Measure a section

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

### Errors

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
