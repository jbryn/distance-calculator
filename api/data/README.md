# River data

The application only offers the rivers listed here, and it answers from these files
alone: no request to the API waits on an outside service.

## `rivers.json` – the catalogue

The rivers on offer, each with the id of its relation in OpenStreetMap and the name
shown to users. Search (`GET /api/rivers?q=`) looks only at this list.

## `overpass/` – saved map data

Two responses from the Overpass API per river, saved by `QueryCache` exactly as the
application caches them. Each file holds the query, the fetch time and the returned
elements. The file name is derived from the query.

- **Course:** the river's relation with the geometry of its ways (`RiverRelation::overpassQuery($id)`).
- **Features:** weirs, dams, locks, rapids, waterfalls, landing places, slipways and road
  bridges on or beside those ways, each reduced to one point (`MapFeature::overpassQuery($id)`).

| River | OSM relation | Course (fetched) | Features (fetched) |
|---|---|---|---|
| Biała (Tarnowska) | 19995496 | `1cd0fad60116ccb77c661e46171de5bf.json` (2026-10-12) | `06864c51e13495cb557a754b30f1490d.json` (2026-10-13) |
| Biebrza | 6378830 | `e38c1bcd79aa98dcb317a34a6da14ffb.json` (2026-10-12) | `628416018c24d9ffc5dc94c191b2c65d.json` (2026-10-13) |
| Bóbr | 1582509 | `95288fc6de3e1b4078a1a60635e4066f.json` (2026-10-12) | `f04eb0b9719bd594f7787757bda0ffa7.json` (2026-10-13) |
| Dunajec | 2822415 | `46211cad45699ea306dee07271890e4e.json` (2026-10-12) | `5e11ee452c72534de3f22ead1f3bc61e.json` (2026-10-13) |
| Gwda | 8974372 | `0afb264cd32de8fdcb1868e8e345ef33.json` (2026-10-12) | `52edeb50a6bbbff7569a723e0011e7df.json` (2026-10-13) |
| Noteć | 6124894 | `a959b07a7d32a3819855cf4dc9bc5b6a.json` (2026-10-12) | `c2a2e3780737e23cb2fc34a986db75fa.json` (2026-10-13) |
| Odra | 387605 | `2e9d8e7ba381eda6473591cd091775db.json` (2026-10-12) | `1d6402c49f9163c90615876e84994fac.json` (2026-10-13) |
| Pasłęka | 7581082 | `febc1b51bc356f3de206453fd8e9a334.json` (2026-10-12) | `0408f2727edf2170221edd3d81c7c1b5.json` (2026-10-13) |
| Raba | 2473086 | `8e8610182c2d721d6bf2eedffc3ca9d3.json` (2026-10-12) | `99dd50225fe55f792fbf8f20bc344d56.json` (2026-10-13) |
| Rega | 12759496 | `e3980336ff99ba80440b8c274db2dccc.json` (2026-10-12) | `658870743e47c857da1812c325cd2bcb.json` (2026-10-13) |
| Warta | 3272339 | `cecdc39139d08e48f994ba912afb0444.json` (2026-10-12) | `ef5c77787904eb671e34dc5c0b6dee05.json` (2026-10-13) |
| Wieprz | 2297716 | `e1d484ff3a657328d0bb9d4e88757808.json` (2026-10-12) | `4777e243a0c7b0186fc404fc896e69b8.json` (2026-10-13) |
| Wisła | 34392 | `1dbace20dcdeea9960c1979ed737f59f.json` (2026-10-12) | `498c4536cb0d731821c5f0980103996b.json` (2026-10-13) |
| Wisłok | 2020696 | `31fa0de1f29e5ebb0d3840c39b48d441.json` (2026-10-12) | `36a56fb6071ef2a5904473c151612bff.json` (2026-10-13) |
| Wisłoka | 7577712 | `236a1f7fdaf50a4f1276fb5d8a58310f.json` (2026-10-12) | `fb65da7807b0a49e8028dce746cdaede.json` (2026-10-13) |

## Adding or refreshing a river

1. For a new river, add its relation id and name to `rivers.json`.
2. Fetch the data:

   ```bash
   cd api
   composer rivers:fetch              # every river in the catalogue
   composer rivers:fetch -- 8974372   # only the given relation ids
   ```

   Each river is fetched, saved here, and then built from the saved files the way the
   API builds it. The command prints the river's length and number of features, or the
   reason it cannot be offered (for example gaps in its course). A river whose fetch
   fails keeps its earlier files. The public Overpass servers are often busy and the
   query for features can run for minutes on a long river; failed queries are retried.
3. Run `composer test`. A refreshed river can change in length and in features, so the
   expected values in `tests/River/RealRiversTest.php` and `tests/Http/RiverApiTest.php`
   may need updating. Commit the changed files.

Map data © OpenStreetMap contributors, available under the
[Open Database License](https://www.openstreetmap.org/copyright).
