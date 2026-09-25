PHPSTAN = vendor/bin/phpstan
PHPSTAN_CONFIG = phpstan.neon

.PHONY: all check install composer-validate lint-container doctrine-validate phpstan test cs cs-fix

all: check

check: install composer-validate lint-container doctrine-validate phpstan cs test

install:
	composer install --no-progress --no-interaction --prefer-dist

composer-validate:
	composer validate --no-check-publish

lint-container:
	php bin/console lint:container

doctrine-validate:
	php bin/console doctrine:schema:validate --skip-sync

phpstan:
	$(PHPSTAN) analyse -c $(PHPSTAN_CONFIG) --memory-limit=1G

cs:
	vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff

cs-fix:
	vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php

test:
	@if [ -x vendor/bin/phpunit ]; then \
		vendor/bin/phpunit; \
	elif [ -f phpunit.xml ] || [ -f phpunit.xml.dist ] || [ -d tests ]; then \
		echo "PHPUnit tests/configuration exist but phpunit is not installed."; \
		exit 1; \
	else \
		echo "No PHPUnit test suite configured; skipping."; \
	fi
