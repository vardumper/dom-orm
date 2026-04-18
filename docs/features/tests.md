# Testing And Coverage

Run the full Pest test suite:

```bash
./vendor/bin/pest
```

Run Pest with a human-friendly output format:

```bash
./vendor/bin/pest --testdox
```

Generate an HTML coverage report (output directory: `build/coverage-html`):

```bash
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage-html build/coverage-html
```

Update `clover.xml` (used by CI and quality tooling):

```bash
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage-clover clover.xml
```

Generate both HTML coverage and a fresh `clover.xml` in one run:

```bash
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage-html build/coverage-html --coverage-clover clover.xml
```