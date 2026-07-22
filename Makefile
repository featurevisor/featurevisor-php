.PHONY: install sast test test-base test-openfeature check setup-monorepo update-monorepo test-example-1 test-example-project

install:
	composer install

test:
	composer test

sast:
	composer sast

test-base:
	vendor/bin/phpunit --exclude-group openfeature tests

test-openfeature:
	vendor/bin/phpunit --group openfeature tests

check:
	composer validate --strict
	composer audit --locked --no-interaction
	composer sast
	composer test

setup-monorepo:
	mkdir -p monorepo
	if [ ! -d "monorepo/.git" ]; then \
		git clone git@github.com:featurevisor/featurevisor.git monorepo; \
	else \
		(cd monorepo && git fetch origin main && git checkout main && git pull origin main); \
	fi
	(cd monorepo && make install && make build)

update-monorepo:
	(cd monorepo && git pull origin main)

test-example-1:
	composer test
	./featurevisor test --projectDirectoryPath="../featurevisor/examples/example-1" --onlyFailures --quiet

test-example-project: test-example-1
