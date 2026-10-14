.PHONY: install lint analyse test test-unit test-integration test-e2e verify db-migrate db-test-reset db-seed db-seed-prod db-seed-dev production-config serve

install:
	composer install

lint:
	@echo "Checking PHP syntax in src/..."
	@find src -name "*.php" -print0 | xargs -0 -r -n1 php -l
	@echo "Checking PHP syntax in bin/..."
	@find bin -name "*.php" -print0 | xargs -0 -r -n1 php -l
	@echo "Checking PHP syntax in public/..."
	@find public -name "*.php" -print0 | xargs -0 -r -n1 php -l
	@echo "Checking PHP syntax in config/..."
	@find config -name "*.php" -print0 | xargs -0 -r -n1 php -l
	@echo "Checking PHP syntax in routes/..."
	@find routes -name "*.php" -print0 | xargs -0 -r -n1 php -l
	@echo "Checking PHP syntax in database/..."
	@find database -name "*.php" -print0 | xargs -0 -r -n1 php -l
	@echo "Checking PHP syntax in views/..."
	@find views -name "*.php" -print0 | xargs -0 -r -n1 php -l
	@echo "Checking PHP syntax in router.php..."
	@php -l router.php

analyse:
	./vendor/bin/phpstan analyse --level=9 --memory-limit=512M src config routes

test: db-test-reset
	./vendor/bin/phpunit --display-deprecations --display-notices --display-warnings

test-unit:
	./vendor/bin/phpunit --testsuite Unit

test-integration: db-test-reset
	./vendor/bin/phpunit --testsuite Integration

test-e2e: db-test-reset
	./vendor/bin/phpunit --testsuite Feature

db-migrate:
	php database/migrate.php

db-test-reset:
	APP_ENV=testing php database/test_reset.php

db-seed: db-seed-dev

db-seed-prod:
	php database/seed.php --env=production

db-seed-dev:
	php database/seed.php --env=development

production-config:
	php bin/verify-production-config.php

serve:
	php -S localhost:8080 router.php

verify: lint analyse test
	@echo "=========================================="
	@echo "VERIFICATION PASSED: ALL CHECKS IN GREEN!"
	@echo "=========================================="
