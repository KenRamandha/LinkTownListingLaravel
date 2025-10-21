# Produk API Endpoints

Semua endpoint di bawah berada pada prefix `https://{host}/api` dan dapat diakses tanpa autentikasi karena berada di dalam grup `optional.auth`. Gunakan header `Accept: application/json` pada setiap request.

## 1. GET `/products/home`
- **Tujuan**: Mendapatkan daftar produk terbaru untuk dua kategori penampilan di halaman home (properti terbaru & listing terbaru).
- **Query params**:
  - `property_status` *(opsional, string)* – filter status properti (`Jual`, `Sewa`, dll).
  - `limit` *(opsional, integer 1-20, default 5)* – jumlah item per kategori.
- **Contoh**: `GET /api/products/home?property_status=Jual&limit=5`
- **Respons**: objek dengan `latest_properties` dan `latest_listings`, masing-masing berisi array produk ringkas (judul, harga, gambar, dll).

## 2. GET `/products`
- **Tujuan**: Mendapatkan daftar produk dengan dukungan pencarian, filter, sort, dan paginasi.
- **Query params** (semua opsional kecuali disebutkan):
  - `search` *(string)* – cari berdasar judul, slug, deskripsi, tags, dll.
  - `status` *(string, default `Published`)* – status publikasi (`Published`/`Draft`).
  - `property_status` atau `property_statuses` *(string atau array)* – filter status properti (boleh comma separated).
  - `product_type_id` atau `product_type_ids` *(int/array)* – filter jenis produk.
  - `developer_id`, `project_id`, `user_id` *(string/int)* – filter relasional.
  - `place_id` *(int atau array)* – filter lokasi (dari tabel places).
  - `price_min`, `price_max` *(numeric)* – filter harga.
  - `featured_partner` *(boolean string: `true`/`false`/`1`/`0`)* – filter partner unggulan.
  - `label` *(string)* – filter berdasarkan label.
  - `tags` *(string atau array)* – filter produk yang mengandung tag tertentu.
  - `sort` *(enum)* – `newest`, `oldest`, `price_asc`, `price_desc` (default `newest`).
  - `per_page` *(integer, 1-100, default 12)* – jumlah item per halaman.
  - `page` *(integer >=1)* – memilih halaman.
- **Contoh**: `GET /api/products?property_status=Jual&tags=tanpa%20dp&price_max=3000000000&sort=price_asc&per_page=10`
- **Respons**: objek dengan `items` (array produk ringkas) dan metadata paginasi di `meta.pagination`.

## 3. GET `/products/filters`
- **Tujuan**: Mendapatkan daftar opsi filter yang tersedia (status, label, range harga, daftar type / developer / project / lokasi, dsb).
- **Query params**: tidak ada.
- **Respons**: objek dengan properti:
  - `property_statuses` *(array string)*
  - `labels` *(array string)*
  - `price_range` *(objek dengan `min` & `max`)*
  - `product_types`, `developers`, `projects`, `places` *(array objek `{id, name}`)* – hanya muncul jika tabel terkait tersedia.
  - `tags` *(array string)*

## 4. GET `/products/{slug}`
- **Tujuan**: Mendapatkan detail lengkap suatu produk, termasuk spesifikasi, lokasi, layout, dan daftar image.
- **Path param**:
  - `{slug}` – slug produk; secara otomatis diload berdasarkan kolom `slug`.
- **Contoh**: `GET /api/products/linktown-mazenta`
- **Respons**: objek detail lengkap (deskripsi panjang, meta, hero section, informasi kontak, relasi, dll).

---

### Catatan Umum
- Field gambar (`featured_image_url`, `image_location`, entries di `images` dan `layouts`) sudah dikonversi ke URL publik.
- Field JSON (misal `tags`, `benefits`, `hero_list`, `tenant`, spesifikasi `value`) selalu dikirim sebagai array/object yang sudah didekode.
- Paginasi mengikuti standar Laravel: gunakan `meta.pagination` jika ingin menampilkan total halaman di mobile.
