.PHONY: dev lint test ci hooks

dev:
	php -S localhost:5050 router.php

hooks:
	git config core.hooksPath .githooks
	chmod +x .githooks/pre-push
	@echo "Hooks instalados desde .githooks (pre-push ejecuta 'make ci')."

lint:
	find . -name '*.php' -not -path './vendor/*' -not -path './docs/templatesOthersApp/*' -print0 | xargs -0 -n1 php -l

test:
	./vendor/bin/phpunit

ci: lint test
