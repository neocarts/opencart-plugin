.PHONY: up down restart reset logs test lint e2e build webhook-replay demo-config themes theme sandbox sandbox-reset sandbox-down

up:            ## start the stand: :8082 (2.3) :8083 (3.0) :8084 (4.1), admin/admin
	docker compose up -d --build

down:
	docker compose down

restart:       ## re-link plugin files after adding new ones: make restart S=oc3
	docker compose restart $(or $(S),oc2 oc3 oc4)

reset:         ## wipe one shop and reinstall it: make reset S=oc3
	docker compose rm -sf $(S)
	docker volume rm -f neocarts-opencart-plugin_$(S)
	docker compose exec -T db mariadb -uroot -proot -e "DROP DATABASE IF EXISTS $(S); CREATE DATABASE $(S) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
	docker compose up -d $(S)

logs:
	docker compose logs -f $(or $(S),oc2 oc3 oc4)

lint:          ## php -l: shared + 2.3/3.0 on 7.1 (their minimum), everything on 8.4 (4.x needs 8.0+)
	docker run --rm -v $(PWD):/app -w /app php:7.1-cli sh -c 'find src/shared src/oc2 src/oc3 -name "*.php" -print0 | xargs -0 -n1 php -l >/dev/null'
	docker run --rm -v $(PWD):/app -w /app php:8.4-cli sh -c 'find src -name "*.php" -print0 | xargs -0 -n1 php -l >/dev/null'

test:          ## PHPUnit for src/shared on PHP 7.1 and 8.3
	docker run --rm -v $(PWD):/app -w /app php:7.1-cli php tests/php/run.php
	docker run --rm -v $(PWD):/app -w /app php:8.3-cli php tests/php/run.php

demo-config:   ## put the demo shop credentials from .env into all three shops
	node tests/e2e/demo-config.js

e2e:           ## Playwright against the running stand: make e2e [P=oc3]
	$(if $(P),E2E_SHOPS=$(P)) npx playwright test $(if $(P),--project=$(P))

# build tools run in the oc4 image (it has ext-zip and sits on the stand's network)
PHP_TOOL = docker compose run --rm --no-deps -T -v $(PWD):/app -w /app --entrypoint php oc4

build:         ## dist/: neocarts-oc2-X.ocmod.zip, neocarts-oc3-X.ocmod.zip, neocarts.ocmod.zip (4.x)
	$(PHP_TOOL) build/build.php

webhook-replay: ## sign a fixture and POST it: make webhook-replay E=order.paid S=oc3
	$(PHP_TOOL) build/webhook-replay.php $(E) $(S)

# third-party themes (docker/themes/themes.php runs inside the shop, read from stdin)
THEMES = docker compose exec -T $(1) php -- $(2) < docker/themes/themes.php

themes:        ## install the demo themes into every shop (idempotent; the active theme stays as it is)
	@for s in $(or $(S),oc2 oc3 oc4); do $(call THEMES,$$s,install all) || exit 1; done

theme:         ## switch one shop's theme: make theme S=oc3 T=ff_frame (T=default to go back; no T = status)
	@$(call THEMES,$(S),$(if $(T),activate $(T),status))

# sandbox: a clean OpenCart 4.1.0.4 apart from the stand (docker/sandbox, docs/ai/docker.md)
SANDBOX = docker compose -f docker/sandbox/compose.yml
SB_EXEC = $(SANDBOX) exec -T web

sandbox:       ## clean 4.1.0.4 on :8090 to install dist/neocarts.ocmod.zip by hand: uk-ua, UAH, BeFree
	$(SANDBOX) up -d --build
	@$(SB_EXEC) sh -c 'i=0; until curl -fso /dev/null http://127.0.0.1/; do i=$$((i+1)); [ $$i -lt 300 ] || exit 1; sleep 1; done'
	@if $(SB_EXEC) test ! -f .sandbox-ready; then \
	  $(SB_EXEC) php < docker/sandbox/setup.php && \
	  $(SB_EXEC) php -- install befree < docker/themes/themes.php && \
	  $(SB_EXEC) php -- activate befree < docker/themes/themes.php && \
	  $(SB_EXEC) touch .sandbox-ready; fi
	@echo "sandbox: http://localhost:8090/  admin: http://localhost:8090/admin/ (admin / admin)"

sandbox-reset: ## wipe the sandbox (its db and files) and build it again, pristine
	$(SANDBOX) down -v
	$(MAKE) sandbox

sandbox-down:
	$(SANDBOX) down
