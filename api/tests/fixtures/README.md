# Test fixtures

## `overpass/` – saved river data

Responses from the Overpass API, saved by `QueryCache` exactly as the application caches
them. Tests read them through the same offline client the application uses, so no test
touches the network. Each file holds the query, the fetch time and the returned elements.

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
| Soła | 545358 | 2026-10-12 | `ead704d9fc70c4bef5c66f3b13fb7ce3.json` |
| Warta | 3272339 | 2026-10-12 | `cecdc39139d08e48f994ba912afb0444.json` |
| Wieprz | 2297716 | 2026-10-12 | `e1d484ff3a657328d0bb9d4e88757808.json` |
| Wisła | 34392 | 2026-10-12 | `1dbace20dcdeea9960c1979ed737f59f.json` |
| Wisłok | 2020696 | 2026-10-12 | `31fa0de1f29e5ebb0d3840c39b48d441.json` |
| Wisłoka | 7577712 | 2026-10-12 | `236a1f7fdaf50a4f1276fb5d8a58310f.json` |

The file name is derived from the query (`RiverRelation::overpassQuery($id)`), so a river
is added or refreshed by running that query once with `OVERPASS_CACHE_DIR` pointing at
this folder. Refreshing a river can change its length; the expected lengths in
`tests/River/RealRiversTest.php` then need updating.

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
