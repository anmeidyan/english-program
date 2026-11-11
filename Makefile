DC = docker compose
EXEC = $(DC) exec app
APP_PORT ?= $(shell grep -E '^APP_PORT=' .env 2>/dev/null | cut -d= -f2 || echo 8080)

.PHONY: setup up down build shell install assets migrate fresh test pint stan e2e vapid logs npm

# First run: build images, start services, install deps, key, migrate + seed, build assets.
setup:
	test -f .env || cp .env.example .env
	$(DC) build
	$(DC) up -d
	$(EXEC) composer install
	$(EXEC) php artisan key:generate --force
	$(EXEC) php artisan storage:link
	$(EXEC) php artisan migrate:fresh --seed
	$(MAKE) assets

up:
	$(DC) up -d

down:
	$(DC) down

build:
	$(DC) build

shell:
	$(EXEC) sh

install:
	$(EXEC) composer install
	$(DC) run --rm node npm install

# Production asset build (needed for the service worker / PWA; `docker compose up` also starts Vite dev on :5173).
assets:
	$(DC) run --rm --no-deps node sh -c "npm install && npm run build"
	$(DC) run --rm --no-deps node rm -f public/hot

migrate:
	$(EXEC) php artisan migrate

fresh:
	$(EXEC) php artisan migrate:fresh --seed

test:
	$(EXEC) php artisan test

pint:
	$(EXEC) ./vendor/bin/pint

stan:
	$(EXEC) ./vendor/bin/phpstan analyse --memory-limit=1G

# Browser smoke test (Playwright in Docker): login → lesson → practice → quiz → result → flashcards.
# Run after `make fresh` + `make assets`; fails on any step, JS console error or HTTP 5xx.
e2e:
	docker run --rm --network host -v $(CURDIR)/tests/e2e:/w -w /w -e E2E_BASE_URL=http://localhost:$(APP_PORT) \
		mcr.microsoft.com/playwright:v1.56.1-noble sh -c "npm i -s --no-audit --no-fund >/dev/null && node smoke.mjs"

# Generates VAPID keys for Web Push into .env (rebuild assets afterwards).
vapid:
	$(EXEC) php artisan webpush:vapid

logs:
	$(DC) logs -f --tail=100

npm:
	$(DC) run --rm node npm $(filter-out $@,$(MAKECMDGOALS))
