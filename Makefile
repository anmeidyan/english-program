DC = docker compose
EXEC = $(DC) exec app

.PHONY: up down build shell migrate fresh test pint stan logs install npm

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

migrate:
	$(EXEC) php artisan migrate

fresh:
	$(EXEC) php artisan migrate:fresh --seed

test:
	$(EXEC) php artisan test --parallel=false

pint:
	$(EXEC) ./vendor/bin/pint

stan:
	$(EXEC) ./vendor/bin/phpstan analyse --memory-limit=1G

logs:
	$(DC) logs -f --tail=100

npm:
	$(DC) run --rm node npm $(filter-out $@,$(MAKECMDGOALS))
