# 5. GET `/products/search`

- **Tujuan**: Mendapatkan daftar produk untuk halaman pencarian dan list di aplikasi mobile (ProductSearchScreen & ProductListScreen).
- **Path**: `GET /api/products/search`
- **Nama route**: `products.search`
- **Query params** (semua opsional):
  - `q` *(string)* – kata kunci pencarian bebas; digunakan untuk mencari di `title`, `hero_subtitle`, `place_name`, `address`, `city_name`, dan `city_state`.
  - `property_status` *(string)* – filter status properti (`Jual`, `Sewa`, `Baru`, dll).
  - `city_id` *(int)* – filter berdasarkan ID kota.
  - `city` *(string)* – filter berdasarkan kota (slug atau nama, misalnya `bekasi`).
  - `min_price` *(int)* – harga minimum.
  - `max_price` *(int)* – harga maksimum.
  - `page` *(int, default 1)* – nomor halaman.
  - `per_page` *(int, 1-50, default 12)* – jumlah item per halaman.

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
GET /api/products/search?q=summarecon&property_status=Jual&city=bekasi&min_price=500000000&max_price=3000000000&page=1&per_page=12
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
      "property_status": "Jual",
      "city": "bekasi",
      "city_id": null,
      "min_price": 500000000,
      "max_price": 3000000000,
      "per_page": 12
    }
  }
}
```
