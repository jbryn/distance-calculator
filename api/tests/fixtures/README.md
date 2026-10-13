# Test fixtures

Most river tests use the rivers the application ships with, in `api/data/` (see the
README there). This folder holds what only the tests need.

## `overpass/` – a river kept as a bad example

| River | OSM relation | Fetched | File |
|---|---|---|---|
| Soła | 545358 | 2026-10-12 | `ead704d9fc70c4bef5c66f3b13fb7ce3.json` |

The Soła is cut into four disconnected parts in OpenStreetMap. It is not offered by the
application; the tests use it to check that a river with gaps is reported, not measured.

Map data © OpenStreetMap contributors, available under the
[Open Database License](https://www.openstreetmap.org/copyright).

## `imgw-reference-distances.json` – distances to check against

Gauging stations on Polish rivers with their official "kilometre of the river" (distance
from the mouth), and pairs of stations with the distance between them. This is an
independent answer key: the tests measure the same stretches on the OpenStreetMap line
and compare.

Source of the data: Instytut Meteorologii i Gospodarki Wodnej – Państwowy Instytut
Badawczy (IMGW-PIB), public data API `https://danepubliczne.imgw.pl/api/data/hydro`.
The caveats and the tolerance for each pair are described inside the file.
