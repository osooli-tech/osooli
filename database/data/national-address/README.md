# Saudi regions, cities and districts

Source: Saudi Post / SPL National Address (maps.address.gov.sa), the
government authority for addresses — 13 regions, 4,581 cities, 3,732
districts, Arabic and English names.

Taken from the `data/dist/*.lite.json` files of
[yasseralsamman/saudi-national-address](https://github.com/yasseralsamman/saudi-national-address)
(data published as CC0-1.0), itself a cleaned copy of
[homaily/Saudi-Arabia-Regions-Cities-and-Districts](https://github.com/homaily/Saudi-Arabia-Regions-Cities-and-Districts).
The two agree on every id and name; the underlying SPL snapshot dates from
about 2021–2022, so districts created since then are not in it.

Trimmed to the fields the seeder uses. `id` is SPL's own identifier (the
11-digit district code for districts) and is stored as `national_address_id`,
which is what makes `NationalAddressSeeder` safe to run again.

Load with:

    php artisan db:seed --class=NationalAddressSeeder
