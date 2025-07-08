# IMSE_MS2-Irdi-Kuka

This project uses Composer for dependency management. Development tests are written with PHPUnit.

## Running Tests

1. Install Composer dependencies:

```bash
cd src
composer install
```

2. Run the test suite from the project root:

```bash
./src/vendor/bin/phpunit
```

The default configuration loads tests from the `tests/` directory.
