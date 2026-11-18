.PHONY: dev lint test ci

dev:
	php -S localhost:5050 router.php

lint:
	find . -name '*.php' -not -path './vendor/*' -not -path './docs/templatesOthersApp/*' -print0 | xargs -0 -n1 php -l

test:
	./vendor/bin/phpunit

ci: lint test
