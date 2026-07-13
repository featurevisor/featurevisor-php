.PHONY: install test setup-monorepo update-monorepo test-example-1 test-example-project

install:
	composer install

test:
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
	./featurevisor test --projectDirectoryPath="../featurevisor/examples/example-1" --onlyFailures

test-example-project: test-example-1
