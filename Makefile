.PHONY: help build up down restart logs shell-php shell-frontend shell-mongo import-data jwt-keys

# Default target
help:
	@echo "LexEcuador Docker Commands"
	@echo ""
	@echo "  make build          - Build all containers"
	@echo "  make up             - Start all containers"
	@echo "  make down           - Stop all containers"
	@echo "  make restart        - Restart all containers"
	@echo "  make logs           - View logs from all containers"
	@echo "  make logs-php       - View PHP container logs"
	@echo "  make logs-frontend  - View frontend container logs"
	@echo "  make shell-php      - Open shell in PHP container"
	@echo "  make shell-frontend - Open shell in frontend container"
	@echo "  make shell-mongo    - Open MongoDB shell"
	@echo "  make import-data    - Import constitution data"
	@echo "  make jwt-keys       - Generate JWT keypair"
	@echo "  make cache-clear    - Clear Symfony cache"
	@echo ""

# Build containers
build:
	docker compose build

# Start containers
up:
	docker compose up -d

# Start containers with logs
up-logs:
	docker compose up

# Stop containers
down:
	docker compose down

# Restart containers
restart:
	docker compose restart

# View all logs
logs:
	docker compose logs -f

# View PHP logs
logs-php:
	docker compose logs -f php

# View frontend logs
logs-frontend:
	docker compose logs -f frontend

# Shell access
shell-php:
	docker compose exec php bash

shell-frontend:
	docker compose exec frontend sh

shell-mongo:
	docker compose exec mongo mongosh lexecuador_db

# Import constitution data
import-data:
	docker compose exec php php bin/console app:import-constitution

# Generate JWT keys
jwt-keys:
	docker compose exec php php bin/console lexik:jwt:generate-keypair --overwrite

# Clear Symfony cache
cache-clear:
	docker compose exec php php bin/console cache:clear

# Install PHP dependencies
composer-install:
	docker compose exec php composer install

# Install frontend dependencies
npm-install:
	docker compose exec frontend npm install

# Run PHP container only (for API development)
up-api:
	docker compose up -d php mongo

# Run frontend only (assumes API is running elsewhere)
up-frontend:
	docker compose up -d frontend
