SHELL := /bin/bash

.PHONY: help up down restart build rebuild logs logs-web logs-db ps shell mysql health bootstrap-config php-lint composer cron-% clean nuke

help: ## Show this help
	@grep -E '^[a-zA-Z_%-]+:.*## ' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  \033[1m%-16s\033[0m %s\n", $$1, $$2}'

up: ## Start the stack in the background
	docker compose up -d

down: ## Stop the stack
	docker compose down

restart: down up ## Restart the stack

build: ## Build images (incremental)
	docker compose build

rebuild: ## Force-rebuild images from scratch
	docker compose build --no-cache

logs: ## Tail logs from all services
	docker compose logs -f

logs-web: ## Tail logs from just the web container
	docker compose logs -f web

logs-db: ## Tail logs from just the mysql container
	docker compose logs -f mysql

ps: ## Show service status
	docker compose ps

shell: ## Open a bash shell inside the web container
	docker compose exec web bash

mysql: ## Open a mysql client inside the mysql container
	docker compose exec mysql mysql -umpos -pmpos mpos

health: ## Verify PHP, mysqli, memcached, MySQL conn, memcached conn
	docker compose exec -T web php public/healthcheck.php

bootstrap-config: ## Generate include/config/global.inc.php (idempotent; --force to overwrite)
	docker compose exec -T web php scripts/bootstrap-config.php $(ARGS)

php-lint: ## Syntax-lint every PHP file (sequential; reports each fatal with file + line)
	@docker compose exec -T web bash -c '\
		set +e; \
		fails=0; fail_list=""; \
		while IFS= read -r f; do \
			out=$$(php -n -l "$$f" 2>&1); rc=$$?; \
			if [ $$rc -ne 0 ]; then \
				fails=$$((fails+1)); fail_list="$$fail_list\n  $$f"; \
				echo "FAIL ($$rc): $$f"; \
				echo "$$out" | sed "s/^/    /" | tail -3; \
			fi; \
		done < <(find . \( -path ./include/smarty -o -path ./vendor -o -path ./templates/compile -o -path ./templates/cache -o -path ./tests -o -path ./.git \) -prune -o -name "*.php" -print); \
		echo; \
		if [ $$fails -eq 0 ]; then \
			echo "All PHP files passed syntax lint."; \
		else \
			echo "$$fails file(s) failed syntax lint:"; \
			printf "$$fail_list\n"; \
			exit 1; \
		fi'

composer: ## Run composer inside the web container (e.g. make composer ARGS="require monolog/monolog")
	docker compose exec web composer $(ARGS)

cron-%: ## Run a named cronjob (e.g. make cron-statistics, make cron-payouts)
	docker compose exec web php cronjobs/$*.php

clean: ## Stop the stack and remove anonymous volumes
	docker compose down --remove-orphans

nuke: ## Stop the stack and DELETE all data (mysql volume included). Use with care.
	docker compose down -v --remove-orphans
