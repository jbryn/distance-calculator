# River data

The application only offers the rivers listed here, and it answers from these files
alone: no request to the API waits on an outside service.

## `rivers.json` – the catalogue

The rivers on offer, each with the id of its relation in OpenStreetMap and the name
shown to users. Search (`GET /api/rivers?q=`) looks only at this list.

## `overpass/` – saved map data

One response from the Overpass API per river, saved by `QueryCache` exactly as the
application caches it. Each file holds the query, the fetch time and the returned
elements. The file name is derived from the query (`RiverRelation::overpassQuery($id)`).

| River | OSM relation | Fetched | File |
|---|---|---|---|
| Biała (Tarnowska) | 19995496 | 2026-10-12 | `1cd0fad60116ccb77c661e46171de5bf.json` |
| Biebrza | 6378830 | 2026-10-12 | `e38c1bcd79aa98dcb317a34a6da14ffb.json` |
| Bóbr | 1582509 | 2026-10-12 | `95288fc6de3e1b4078a1a60635e4066f.json` |
| Dunajec | 2822415 | 2026-10-12 | `46211cad45699ea306dee07271890e4e.json` |
| Gwda | 8974372 | 2026-10-12 | `0afb264cd32de8fdcb1868e8e345ef33.json` |
| Noteć | 6124894 | 2026-10-12 | `a959b07a7d32a3819855cf4dc9bc5b6a.json` |
| Odra | 387605 | 2026-10-12 | `2e9d8e7ba381eda6473591cd091775db.json` |
| Pasłęka | 7581082 | 2026-10-12 | `febc1b51bc356f3de206453fd8e9a334.json` |
| Raba | 2473086 | 2026-10-12 | `8e8610182c2d721d6bf2eedffc3ca9d3.json` |
| Rega | 12759496 | 2026-10-12 | `e3980336ff99ba80440b8c274db2dccc.json` |
| Warta | 3272339 | 2026-10-12 | `cecdc39139d08e48f994ba912afb0444.json` |
| Wieprz | 2297716 | 2026-10-12 | `e1d484ff3a657328d0bb9d4e88757808.json` |
| Wisła | 34392 | 2026-10-12 | `1dbace20dcdeea9960c1979ed737f59f.json` |
| Wisłok | 2020696 | 2026-10-12 | `31fa0de1f29e5ebb0d3840c39b48d441.json` |
| Wisłoka | 7577712 | 2026-10-12 | `236a1f7fdaf50a4f1276fb5d8a58310f.json` |

## Adding or refreshing a river

1. For a new river, add its relation id and name to `rivers.json`.
2. Start the API with the network allowed and saved data treated as out of date:

   ```bash
   cd api
   OVERPASS_OFFLINE=false OVERPASS_CACHE_TTL=1 composer start
   ```

3. Open `http://localhost:8080/api/rivers/<id>`. The response is fetched and saved here.
   The public Overpass servers are often busy; if the request fails, try again.
   A `503` that persists means the river's pieces do not join into one line (see the
   server log for how many gaps), and the river should not be offered until the map
   is fixed.
4. Run `composer test`. A refreshed river can change length, so the expected lengths
   in `tests/River/RealRiversTest.php` may need updating. Commit the changed files.

Map data © OpenStreetMap contributors, available under the
[Open Database License](https://www.openstreetmap.org/copyright).
