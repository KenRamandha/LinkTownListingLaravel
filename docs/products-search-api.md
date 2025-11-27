# 5. GET `/products/search`

- **Tujuan**: Mendapatkan daftar produk untuk halaman pencarian dan list di aplikasi mobile (ProductSearchScreen & ProductListScreen).
- **Path**: `GET /api/products/search`
- **Nama route**: `products.search`
- **Query params** (semua opsional):
  - `q` *(string)* – kata kunci pencarian bebas; digunakan untuk mencari di `title`, `hero_subtitle`, `place_name`, `address`, `city_name`, dan `city_state`.
  - `property_statuses[]` *(array string)* – filter beberapa status properti sekaligus (`Jual`, `Sewa`, `Baru`, dll). Jika tidak dikirim sama sekali, maka semua status akan ditampilkan.
  - `city_ids[]` *(array int)* – filter berdasarkan beberapa ID kota sekaligus (bisa digunakan saat user memilih satu atau lebih kota di UI).
  - `min_price` *(int)* – harga minimum.
  - `max_price` *(int)* – harga maksimum.
  - `product_type_id` *(int)* – filter satu jenis produk (mis. Rumah, Ruko, Apartment) berdasarkan ID di tabel `product_types`.
  - `product_type_ids[]` *(array int)* – filter beberapa jenis produk sekaligus. Jika tidak dikirim, semua tipe yang diizinkan di-search (property & listing) akan ditampilkan.
  - `place_id` *(int)* – filter satu lokasi/branch (berdasarkan ID di tabel `places` – bisa di-mapping ke “Branch Office” pada UI).
  - `place_ids[]` *(array int)* – filter beberapa lokasi/branch sekaligus.
  - `sort` *(enum)* – cara mengurutkan hasil:
    - `relevance` *(default)* – diurutkan berdasarkan produk terbaru (paling sesuai).
    - `newest` – produk paling baru di atas.
    - `oldest` – produk paling lama di atas.
    - `price_asc` – harga terendah ke tertinggi (untuk opsi “Termurah”).
    - `price_desc` – harga tertinggi ke terendah (untuk opsi “Termahal”).
  - `page` *(int, default 1)* – nomor halaman.
  - `per_page` *(int, 1-50, default 12)* – jumlah item per halaman.

## 6. GET `/products/search/filters`

- **Tujuan**: Mendapatkan daftar opsi filter yang digunakan untuk bottom sheet filter di layar pencarian/list produk.
- **Path**: `GET /api/products/search/filters`
- **Query params**: tidak ada.
- **Struktur Respons (`data`)**:
  - `property_statuses` *(array string)* – daftar status properti yang tersedia (`Jual`, `Sewa`, dll).
  - `product_types` *(array object)* – daftar tipe produk yang dapat difilter:
    - `id`, `name`, `slug`, `title`, `color`.
  - `price_range` *(object)* – range harga global dari produk yang dipublikasikan:
    - `min` *(number|null)* – harga terendah (atau `null` jika belum ada).
    - `max` *(number|null)* – harga tertinggi (atau `null` jika belum ada).
  - `cities` *(array object)* – daftar kota untuk section “Lokasi”:
    - `id`, `slug`, `name`, `state`.
  - `places` *(array object)* – daftar tempat/branch (bisa dipakai untuk section “Branch Office”):
    - `id`, `city_id`, `city_name`, `name`, `slug`, `order`.

## Struktur Item Produk

Setiap item di `data.products` memiliki struktur yang mirip dengan item pada endpoint `/products/home`, dengan perbedaan utama pada field foto:

- `product_id` *(int)* – ID produk.
- `id` *(int)* – alias `product_id`.
- `price` *(number)* – harga dalam angka.
- `title` *(string)* – judul produk.
- `property_status` *(string)* – `Jual`, `Sewa`, `Baru`, dll.
- `featured_image_url` *(array string)* – daftar semua URL foto produk (foto unggulan berada di awal list).
- `specification_value` *(array)* – list spesifikasi (LT, LB, KT, KM, dll) seperti di `/products/home`.
- `description` *(string, optional)* – deskripsi singkat.
- `hero_subtitle` *(string, optional)* – subtitle/teks lokasi pendek.
- `product_type_name` *(string, optional)* – nama jenis produk (Rumah, Apartemen, dsb).
- `address` *(string, optional)* – alamat.
- `place_name` *(string, optional)* – nama kawasan/project.
- `city_name` *(string, optional)* – nama kota.
- `city_state` *(string, optional)* – provinsi/region.
- `namawa` *(string, optional)* – nama agen.
- `nowa` *(string/number, optional)* – nomor WhatsApp.
- `photos_count` *(int)* – jumlah total foto produk.

## Contoh Request

```http
GET /api/products/search?q=summarecon&property_statuses[]=Jual&min_price=500000000&max_price=3000000000&page=1&per_page=12
Accept: application/json
```

## Contoh Struktur Respons

```json
{
  "success": true,
  "code": 200,
  "message": "Berhasil memuat daftar produk",
  "data": {
    "products": [
      {
        "product_id": 123,
        "id": 123,
        "price": 1800000000,
        "title": "Rumah Termurah 7x13m Furnished Plus 4 AC",
        "property_status": "Jual",
        "featured_image_url": [
          "https://example.com/storage/products/123-featured.jpg",
          "https://example.com/storage/products/123-2.jpg",
          "https://example.com/storage/products/123-3.jpg"
        ],
        "specification_value": [
          { "key": "LT", "value": "91 m²", "icon": "luas tanah" },
          { "key": "LB", "value": "80 m²", "icon": "luas bangunan" },
          { "key": "KT", "value": "3 Kamar Tidur", "icon": "bed" },
          { "key": "KM", "value": "2 Kamar Mandi", "icon": "bath" }
        ],
        "description": "Rumah baru dibangun siap huni.",
        "hero_subtitle": "Summarecon Crown Gading, Bekasi",
        "product_type_name": "Rumah",
        "address": "Summarecon Crown Gading",
        "place_name": "Summarecon Crown Gading",
        "city_name": "Bekasi",
        "city_state": "Jawa Barat",
        "namawa": "Jimmy Lunardi",
        "nowa": "+6281234567890",
        "photos_count": 12
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 12,
      "total": 320,
      "last_page": 27
    }
  },
  "meta": {
    "filters": {
      "q": "summarecon",
      "property_statuses": ["Jual"],
      "city_ids": null,
      "min_price": 500000000,
      "max_price": 3000000000,
      "per_page": 12
    }
  }
}
```
