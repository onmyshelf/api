# OnMyShelf Collection Manager - API

# Installation
The easiest way to install OnMyShelf is to use the [docker project here](https://github.com/onmyshelf/docker).

If you want to install the API manually, here are the instructions:

## Requirements
- A MariaDB/MySQL database
- A web server with PHP and PHP mysqli module

## Configuration
Copy `config.default.php` to `config.php` then edit it.

## Initialization
Go into the project folder then run:
```bash
php bin/oms install
```

# Upgrade
If you uses the docker project, you have nothing to do.

If you have installed the API manually, go into the project folder then run:
```bash
php bin/oms upgrade
```

# License
This project is licensed under the MIT License. See [LICENSE.md](LICENSE.md) for the full license text.

# Credits
Website: https://onmyshelf.app

Source code: https://github.com/onmyshelf/api

Used in this project:
- PHPMailer: https://github.com/PHPMailer/PHPMailer
- PHP Simple HTML DOM Parser: https://sourceforge.net/projects/simplehtmldom
