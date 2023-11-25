# Changelog for OnMyShelf API

# 1.0.0 (2023-11-25)
- First stable release

# 1.0.0 RC 5 (2023-08-09)
- Collections are sorted by name
- New `/media/download` route to download a file into media library
- Added a `download` function in `Import` and `Storage` classes to store a file into media library
- Fixed missing values when importing properties with multiple values
- Fixed crash when deleting an item with existing loans
- Fixed bugs in Tellico collection import
- Other fixes and improvements

# 1.0.0 RC 4 (2022-11-26)
- New loans support
- New Amazon Books import module
- Improved HTML import class
- Minor code improvements

# 1.0 RC 3 (2022-08-22)
- Import GCstar collections now downloads pictures into media library
- Default token lifetime is now set to 1 month
- `GET collection/{id}` route returns available values for each filterable property
- Improved database performance
- Minor fixes

# 1.0 RC 2 (2022-07-22)
- Generate thumbnails when storing images
- Major security improvements in passwords and tokens
- New `/config` route to print and edit config (only superadmin)
- New import search route to import items from external sources
- New demo mode
- Migration to PHP PDO for database connector
- Improve collection import from Tellico files
- Various code improvements

# 1.0 RC 1 (2022-05-28)
- Renamed 'fields' to 'properties' for better understanding
- Improved collection sorting and filtering

# 1.0 Beta 1 (2021-10-09)
- Create, edit & delete collections
- Create, edit & delete fields
- Create, edit & delete items
- Import collections from GCstar, Tellico and CSV
