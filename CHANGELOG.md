# Changelog for OnMyShelf API

# 1.5.2 (2024-10-12)
- Add unit tests

# 1.5.1 (2024-10-12)
- Add first draft for API developers documentation (using OpenAPI)

# 1.5.0 (2024-09-07)
## New features
- New route to get values from a property
- New route to borrow item
## Improvements
- Simplify import routes

# 1.4.0 (2024-08-23)
## New features
- New borrowers management system
- Implement item search
- New route to upgrade modules
## Improvements
- Improve cleanup command
- Renamed import route

# 1.3.2 (2024-08-13)
- Add missing database indexes

# 1.3.1 (2024-08-07)
- New route to check email config
- New route to send a test email

# 1.3.0 (2024-07-29)
## New features
- Support for item quantity
## Bugfixes
- Fixed bug in collection deletion
- Fixed migration procedure

# 1.3.0 RC 1 (2024-07-26)
- Added "borrowable" parameter to collection & item
- Added collection tags
- Added pending loans to item

# 1.3.0 BETA 1 (2024-07-12)
## New features
- Permit users to log in with email address
- Added comics collection template
## Improvements
- Trim values before import
- Various improvements

# 1.2.0 (2024-04-27)
- New export collection route
- New board_game collection template

# 1.2.0 BETA 1 (2024-04-20)
## New features
- New users management routes
- Added email support (using PHPMailer)
- User reset password is sent by email
## Bugfixes
- Fixed bug in storage class when using download method

# 1.1.1 (2024-04-01)
- Fixed bug when setting multiple parameter in a property

# 1.1.0 (2024-03-16)
## New features
- New cleanup oms command
## Improvements
- Major improvements in properties detection
- Manage import search errors
- Minor code refactoring
## Bugfixes
- Creates thumbnails when Import->download() method is used
- Fixed Amazon.com books import
- Numeric properties are now sorted correctly (fixed by MariaDB migration)
- Handle arguments in oms command line

# 1.1.0 RC 2 (2024-02-22)
- Improved books template
- Fixed bug when creating a collection from template
- Fixed bugs in upgrade procedure

# 1.1.0 RC 1 (2024-02-03)
## New features
- New collection templates
- Add limit & offset filters to dump collection items
- Always sort items by name
- New created/updated fields for collections and items
- External modules with git repositories can now be upgraded automatically
- New functions for HTML import modules
- Added support for hidden properties
## Bugfixes
- Prevent multiple properties to have unique tags like isTitle, isCover, ...
- Fix multiple filters to use AND requests and not OR
- Other fixes and code improvements

# 1.0.1 (2024-01-18)
- Item filters are now case insensitive and accepts symbols
- Item filters can manage a range of numbers and ratings

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
