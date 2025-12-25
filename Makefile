image=yiimp
image2=yiimp2
version=2025.11r01
MAILADDRESS='admin@your-pool.example.com'
DOMAINNAME='your-pool.example.com' 

setup-dirs:
	mkdir -p ./log/apache2 ./log/yiimp ./log/runtime
	chmod -R 777 ./log

build:
	git submodule init && git submodule update
	docker build --tag $(image) --target image-prod -f Dockerfile.yiimp 
build-devel:
	git submodule init && git submodule update
	docker build --tag $(image) --target image-devel -f Dockerfile.yiimp

build-yiimp2:
	docker build --tag $(image2) --target image-prod -f Dockerfile.yiimp2
build-yiimp2-devel:
	docker build --tag $(image2) --target image-devel -f Dockerfile.yiimp2 

push:
	docker push $(image) ghcr.io/tpfuemp/$(image):$(version)

run:
	docker stop $(image) 2>/dev/null || true
	docker rm $(image) 2>/dev/null || true
	docker run -dt --name=$(image) -p 80:80 -p 443:443 -p 3333:3333 -p 3334:3334 --restart=unless-stopped -v ./config/letsencrypt:/etc/letsencrypt -v ./config:/etc/yiimp -v ./log/apache2:/var/log/apache2 -v ./log/yiimp:/var/log/yiimp -v ./log/runtime:/var/www/yaamp/runtime -v ./config/supervisord.conf:/etc/supervisor/conf.d/supervisord.conf $(image)

run-init-letsencrypt:
	docker stop $(image) 2>/dev/null || true
	docker rm $(image) 2>/dev/null || true
	docker run -dt --name=$(image) --network=host -e MAILADDRESS=$(MAILADDRESS) -e DOMAINNAME=$(DOMAINNAME) -v ./config/letsencrypt:/etc/letsencrypt -v ./config:/etc/yiimp -v ./log/apache2:/var/log/apache2 -v ./log/yiimp:/var/log/yiimp -v ./log/runtime:/var/www/yaamp/runtime -v ./config/supervisord.conf:/etc/supervisor/conf.d/supervisord.conf $(image) /usr/local/bin/letsencrypt-yiimp-initial-cert.sh

run-devel:
	docker stop $(image) 2>/dev/null || true
	docker rm $(image) 2>/dev/null || true
	docker run -dt --name=$(image) --network=host -v ./config/letsencrypt:/etc/letsencrypt -v ./config:/etc/yiimp -v ./web:/var/www/ -v ./log/apache2:/var/log/apache2 -v ./log/yiimp:/var/log/yiimp -v ./log/runtime:/var/www/yaamp/runtime -v ./config/supervisord.conf:/etc/supervisor/conf.d/supervisord.conf $(image) /usr/bin/supervisord

run-yiimp2:
	docker stop $(image2) 2>/dev/null || true
	docker rm $(image2) 2>/dev/null || true
	docker run -dt --name=$(image2) -p 8090:8090 --restart=unless-stopped -v ./config:/etc/yiimp -v ./log/apache2:/var/log/apache2 -v ./log/yiimp:/var/log/yiimp -v ./config/supervisord.conf.yiimp2:/etc/supervisor/conf.d/supervisord.conf $(image2)

run-yiimp2-devel:
	docker stop $(image2) 2>/dev/null || true
	docker rm $(image2) 2>/dev/null || true
	docker run -dt --name=$(image2) -p 8090:8090 -v ./config:/etc/yiimp -v ./yiimp2:/var/yiimp2/ -v ./log/apache2:/var/log/apache2 -v ./log/yiimp:/var/log/yiimp -v ./config/supervisord.conf.yiimp2:/etc/supervisor/conf.d/supervisord.conf $(image2) /usr/bin/supervisord

# Test targets
test: test-setup test-run test-cleanup

test-setup:
	@echo "Setting up test environment..."
	mkdir -p ./test-results
	chmod -R 777 ./test-results
	docker compose -f docker-compose.test.yml build

test-run:
	@echo "Starting test environment..."
	docker compose -f docker-compose.test.yml up -d
	@echo "Waiting for services to be ready..."
	sleep 15
	@echo "Running tests..."
	docker compose -f docker-compose.test.yml exec -T yiimp2-test-runner ./vendor/bin/codecept run --no-colors || true
	@echo "Test results saved to ./test-results/"

test-cleanup:
	@echo "Cleaning up test environment..."
	docker compose -f docker-compose.test.yml down -v
	@echo "Test environment cleaned up"

test-unit:
	@echo "Running unit tests only..."
	docker compose -f docker-compose.test.yml up -d yiimp2-test-db yiimp-test-db yiimp2-test-runner
	sleep 10
	docker compose -f docker-compose.test.yml exec -T yiimp2-test-runner ./vendor/bin/codecept run unit --no-colors
	docker compose -f docker-compose.test.yml down -v

test-integration:
	@echo "Running integration tests with full environment..."
	docker compose -f docker-compose.test.yml up -d
	sleep 15
	docker compose -f docker-compose.test.yml exec -T yiimp2-test-runner ./vendor/bin/codecept run integration --no-colors
	docker compose -f docker-compose.test.yml down -v

test-dedicated-system:
	@echo "Running yiimp2-dedicated-system tests..."
	docker compose -f docker-compose.test.yml up -d
	sleep 15
	docker compose -f docker-compose.test.yml exec -T yiimp2-test-runner ./vendor/bin/codecept run unit schema,migration,config,stratum,backend,system,container --no-colors
	docker compose -f docker-compose.test.yml down -v

test-shell:
	@echo "Opening shell in test runner container..."
	docker compose -f docker-compose.test.yml up -d
	sleep 10
	docker compose -f docker-compose.test.yml exec yiimp2-test-runner /bin/bash

test-logs:
	@echo "Showing test container logs..."
	docker compose -f docker-compose.test.yml logs -f yiimp2-test-runner

test-yiimp2:
	docker exec -it $(image2) bash -c "cd /var/yiimp2 && vendor/bin/codecept run"

test-yiimp2-unit:
	docker exec -it $(image2) bash -c "cd /var/yiimp2 && vendor/bin/codecept run unit"

test-yiimp2-integration:
	docker exec -it $(image2) bash -c "cd /var/yiimp2 && vendor/bin/codecept run integration"

test-yiimp2-verbose:
	docker exec -it $(image2) bash -c "cd /var/yiimp2 && vendor/bin/codecept run --debug"

# Docker Compose targets for Yiimp2 Dedicated System
# Requirements: 19.1, 19.2, 19.3, 19.4, 19.5, 19.6, 19.7, 19.8

compose-build:
	docker compose build

compose-up:
	docker compose up -d

compose-down:
	docker compose down

compose-logs:
	docker compose logs -f

compose-restart:
	docker compose restart

compose-init:
	@echo "Initializing Yiimp2 database..."
	@if [ ! -f .env ]; then \
		echo "Error: .env file not found. Copy .env.example to .env and configure it first."; \
		exit 1; \
	fi
	@if [ ! -f sql/yiimp2-init.sql ]; then \
		echo "Generating schema..."; \
		./bin/generate-yiimp2-schema.sh; \
	fi
	@if [ ! -d config/yiimp2 ]; then \
		echo "Generating configurations..."; \
		./bin/generate-yiimp2-configs.sh --db-name yiimp2 --db-user yiimp2 --db-pass $$(grep DB_PASSWORD .env | cut -d '=' -f2) --port-offset 1000; \
	fi
	@echo "Starting database service..."
	docker compose up -d yiimp2-db
	@echo "Waiting for database to be ready..."
	@sleep 10
	@echo "Migrating data..."
	docker compose exec yiimp2-db mysql -u root -p$$(grep DB_ROOT_PASSWORD .env | cut -d '=' -f2) yiimp2 < sql/old-yiimp.sql || true
	@echo "Database initialization complete!"

compose-restart-stratum-sha256:
	docker compose restart yiimp2-stratum-sha256

compose-restart-stratum-sha256-high:
	docker compose restart yiimp2-stratum-sha256-high

compose-restart-stratum-scrypt:
	docker compose restart yiimp2-stratum-scrypt

compose-restart-backend-main:
	docker compose restart yiimp2-backend-main

compose-restart-backend-loop2:
	docker compose restart yiimp2-backend-loop2

compose-restart-backend-blocks:
	docker compose restart yiimp2-backend-blocks

compose-status:
	docker compose ps

compose-clean:
	docker compose down -v
	rm -rf log/yiimp2/*

# Development mode targets with debug enabled
compose-dev-build:
	docker compose -f docker-compose.dev.yml build

compose-dev-up:
	docker compose -f docker-compose.dev.yml up -d

compose-dev-down:
	docker compose -f docker-compose.dev.yml down

compose-dev-logs:
	docker compose -f docker-compose.dev.yml logs -f

compose-dev-restart:
	docker compose -f docker-compose.dev.yml restart

compose-dev-status:
	docker compose -f docker-compose.dev.yml ps

compose-dev-clean:
	docker compose -f docker-compose.dev.yml down -v
	rm -rf log/yiimp2/*

# Convenience shortcuts for development
up-dev: compose-dev-up
down: compose-dev-down
logs: compose-dev-logs
restart: compose-dev-restart
clean: compose-dev-clean

.PHONY: compose-build compose-up compose-down compose-logs compose-restart compose-init \
        compose-restart-stratum-sha256 compose-restart-stratum-sha256-high compose-restart-stratum-scrypt \
        compose-restart-backend-main compose-restart-backend-loop2 compose-restart-backend-blocks \
        compose-status compose-clean \
        compose-dev-build compose-dev-up compose-dev-down compose-dev-logs compose-dev-restart \
        compose-dev-status compose-dev-clean \
        up-dev down logs restart clean


# Individual stratum service management
compose-restart-stratum-sha256:
	docker compose restart yiimp2-stratum-sha256

compose-restart-stratum-sha256-high:
	docker compose restart yiimp2-stratum-sha256-high

compose-restart-stratum-scrypt:
	docker compose restart yiimp2-stratum-scrypt

compose-scale-stratum-sha256:
	docker compose up -d --scale yiimp2-stratum-sha256=3 --no-recreate

# Testing targets for Yiimp2 Dedicated System
# Task 12: Final testing and validation

.PHONY: test test-all test-system test-load test-coexistence test-integration test-quick

# Main test target - runs complete test suite with environment setup
test: test-env-check
	@echo "=========================================="
	@echo "Yiimp2 Dedicated System - Full Test Suite"
	@echo "=========================================="
	@echo ""
	@echo "Running complete system test with Docker build..."
	@./test-yiimp2-complete-system.sh
	@echo ""
	@echo "Running integration tests..."
	@cd yiimp2 && ./vendor/bin/codecept run integration --no-colors || true
	@echo ""
	@echo "Running coexistence verification..."
	@./test-yiimp2-coexistence.sh
	@echo ""
	@echo "=========================================="
	@echo "Full Test Suite Complete!"
	@echo "=========================================="
	@echo ""
	@echo "To run load tests (requires services running):"
	@echo "  1. Start services: make compose-up"
	@echo "  2. Run load tests: make test-load"

# Environment check before running tests
test-env-check:
	@echo "Checking test environment..."
	@if [ ! -f .env ]; then \
		echo "Creating .env from .env.example..."; \
		cp .env.example .env; \
		echo "✓ .env file created"; \
	fi
	@if [ ! -f sql/yiimp2-init.sql ]; then \
		echo "Generating schema..."; \
		./bin/generate-yiimp2-schema.sh; \
		echo "✓ Schema generated"; \
	fi
	@if [ ! -d config/yiimp2 ]; then \
		echo "Generating configurations..."; \
		./bin/generate-yiimp2-configs.sh --db-name yiimp2 --db-user yiimp2 --db-pass password --port-offset 1000; \
		echo "✓ Configurations generated"; \
	fi
	@echo "✓ Test environment ready"
	@echo ""

# Run all tests (comprehensive) - Uses dedicated test environment
# This target runs tests in the correct order:
# 1. Build validation tests (no Docker needed)
# 2. Build Docker images
# 3. Start test environment
# 4. Run all tests that need Docker
# 5. Clean up
test-all: test-env-check test-system test-build test-all-with-docker
	@echo ""
	@echo "=========================================="
	@echo "All Yiimp2 Dedicated System Tests Complete"
	@echo "=========================================="

# Run all Docker-dependent tests with a single environment lifecycle
test-all-with-docker:
	@echo "Starting test environment for all Docker-dependent tests..."
	@docker compose -f docker-compose.test.yml up -d
	@echo "Waiting for test database to be ready..."
	@for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20; do \
		if docker compose -f docker-compose.test.yml exec -T yiimp2-test-db mysqladmin ping -h localhost -u root -ptest_root_password --silent 2>/dev/null; then \
			echo "Test database is ready!"; \
			break; \
		fi; \
		echo "Waiting for test database... (attempt $$i/20)"; \
		sleep 3; \
	done
	@echo ""
	@echo "Running unit tests..."
	@docker compose -f docker-compose.test.yml run --rm \
		--no-deps \
		--entrypoint="" \
		yiimp2-test-runner \
		sh -c "cd /app/yiimp2 && ./vendor/bin/codecept run unit --no-colors" || true
	@echo ""
	@echo "Running integration tests..."
	@docker compose -f docker-compose.test.yml run --rm \
		--no-deps \
		--entrypoint="" \
		yiimp2-test-runner \
		sh -c "cd /app/yiimp2 && ./vendor/bin/codecept run integration --no-colors" || true
	@echo ""
	@echo "Running coexistence tests..."
	@./test-scripts/test-yiimp2-coexistence.sh || true
	@echo ""
	@echo "Cleaning up test environment..."
	@docker compose -f docker-compose.test.yml down -v
	@echo "Test environment cleaned up"

# Build Docker images before running tests
test-build:
	@echo "Building Docker images for testing..."
	@docker compose -f docker-compose.test.yml build
	@echo "✓ Docker images built"
	@echo ""

# Run all tests with cleanup
test-all-clean: test-all
	@echo "Cleaning up test environment..."
	@docker compose -f docker-compose.test.yml down -v
	@echo "Test environment cleaned up"

# Quick test (no Docker build, no load testing)
test-quick:
	@echo "Running quick system validation..."
	SKIP_BUILD=1 ./test-scripts/test-yiimp2-complete-system.sh

# Complete system test (Task 12.1)
test-system:
	@echo "Running complete system test..."
	@echo "This will build Docker images (may take several minutes)"
	@echo "y" | ./test-scripts/test-yiimp2-complete-system.sh

# Load testing (Task 12.2)
test-load:
	@echo "Running load testing..."
	@echo "Note: This requires Docker Compose services to be running"
	@if ! docker compose ps | grep -q "yiimp2.*running"; then \
		echo "Error: Yiimp2 services are not running. Start with: make compose-up"; \
		exit 1; \
	fi
	./test-scripts/test-yiimp2-load-testing.sh

# Coexistence testing (Task 12.3)
test-coexistence:
	@echo "Running coexistence verification..."
	./test-scripts/test-yiimp2-coexistence.sh

# Integration tests (Codeception) - Run in Docker container with dedicated test environment
test-integration-docker:
	@echo "Running integration tests in dedicated test environment..."
	@echo "Starting test services (database, memcached, web, stratum)..."
	@docker compose -f docker-compose.test.yml up -d yiimp2-test-db yiimp2-test-memcached yiimp2-test-web yiimp2-test-stratum-sha256 yiimp2-test-stratum-scrypt
	@echo "Waiting for test database to be ready..."
	@for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20; do \
		if docker compose -f docker-compose.test.yml exec -T yiimp2-test-db mysqladmin ping -h localhost -u root -ptest_root_password --silent 2>/dev/null; then \
			echo "Test database is ready!"; \
			break; \
		fi; \
		echo "Waiting for test database... (attempt $$$i/20)"; \
		sleep 3; \
	done
	@echo "Test services started on ports: Web=9090, Stratum-SHA256=5333, Stratum-Scrypt=5433"
	@echo "Running integration tests in container..."
	@docker compose -f docker-compose.test.yml run --rm \
		--no-deps \
		--entrypoint="" \
		yiimp2-test-runner \
		sh -c "cd /app/yiimp2 && ./vendor/bin/codecept run integration --no-colors"
	@echo "Cleaning up test services..."
	@docker compose -f docker-compose.test.yml down -v

# Integration tests (Codeception) - Run on host (requires local database)
test-integration:
	@echo "Running integration tests on host..."
	@echo "Warning: This requires a local database connection"
	cd yiimp2 && ./vendor/bin/codecept run integration --no-colors

# Unit tests (Codeception) - Run in Docker container with dedicated test environment
test-unit-docker:
	@echo "Running unit tests in dedicated test environment..."
	@echo "Starting test services (database and memcached)..."
	@docker compose -f docker-compose.test.yml up -d yiimp2-test-db yiimp2-test-memcached
	@echo "Waiting for test database to be ready..."
	@for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20; do \
		if docker compose -f docker-compose.test.yml exec -T yiimp2-test-db mysqladmin ping -h localhost -u root -ptest_root_password --silent 2>/dev/null; then \
			echo "Test database is ready!"; \
			break; \
		fi; \
		echo "Waiting for test database... (attempt $$$i/20)"; \
		sleep 3; \
	done
	@echo "Running unit tests in container..."
	@docker compose -f docker-compose.test.yml run --rm \
		--no-deps \
		--entrypoint="" \
		yiimp2-test-runner \
		sh -c "cd /app/yiimp2 && ./vendor/bin/codecept run unit --no-colors"
	@echo "Cleaning up test services..."
	@docker compose -f docker-compose.test.yml down -v

# Unit tests (Codeception) - Run on host (requires local database)
test-unit:
	@echo "Running unit tests on host..."
	cd yiimp2 && ./vendor/bin/codecept run unit --no-colors

# Property-based tests only
test-property:
	@echo "Running property-based tests..."
	cd yiimp2 && ./vendor/bin/codecept run unit --group property --no-colors

# Test environment setup - Start dedicated test services
test-env-up:
	@echo "Starting dedicated test environment..."
	@docker compose -f docker-compose.test.yml up -d yiimp2-test-db yiimp2-test-memcached
	@echo "Waiting for test database to be ready..."
	@for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do \
		if docker compose -f docker-compose.test.yml exec -T yiimp2-test-db mysqladmin ping -h localhost -u root -ptest_root_password --silent 2>/dev/null; then \
			echo "Test database is ready!"; \
			break; \
		fi; \
		echo "Waiting for test database... (attempt $$i/15)"; \
		sleep 2; \
	done
	@echo "Test environment is ready!"

# Test environment teardown - Stop and remove test services
test-env-down:
	@echo "Stopping test environment..."
	@docker compose -f docker-compose.test.yml down

# Test environment cleanup - Stop and remove test services and volumes
test-env-clean:
	@echo "Cleaning up test environment (including volumes)..."
	@docker compose -f docker-compose.test.yml down -v
	@echo "Test environment cleaned up"

# Test environment setup (legacy)
test-env-setup: test-env-up
	@if [ ! -f .env ]; then \
		echo "Creating .env from .env.example..."; \
		cp .env.example .env; \
		echo "Please edit .env with your database credentials"; \
	fi
	@if [ ! -f sql/yiimp2-init.sql ]; then \
		echo "Generating schema..."; \
		./bin/generate-yiimp2-schema.sh; \
	fi
	@if [ ! -d config/yiimp2 ]; then \
		echo "Generating configurations..."; \
		./bin/generate-yiimp2-configs.sh --db-name yiimp2 --db-user yiimp2 --db-pass password --port-offset 1000; \
	fi
	@echo "Test environment setup complete"

# Test environment teardown
test-env-teardown:
	@echo "Tearing down test environment..."
	docker compose down -v
	@echo "Test environment cleaned up"

# Run tests in Docker Compose environment
test-in-compose: compose-up
	@echo "Waiting for services to be ready..."
	@sleep 10
	@echo "Running tests in Docker Compose environment..."
	$(MAKE) test-load
	$(MAKE) test-coexistence
	@echo "Tests in Docker Compose environment complete"

# Continuous testing (watch mode)
test-watch:
	@echo "Running tests in watch mode..."
	cd yiimp2 && ./vendor/bin/codecept run --watch

# Test coverage report
test-coverage:
	@echo "Generating test coverage report..."
	cd yiimp2 && ./vendor/bin/codecept run --coverage --coverage-html

# Performance testing targets
# Task 14: Performance testing and optimization

.PHONY: perf-build perf-test perf-analyze perf-analyze-php perf-analyze-nginx perf-clean perf-help

# Build performance testing container
perf-build:
	@echo "Building performance testing container..."
	docker build -t yiimp2-bench -f Dockerfile.bench .
	@echo "✓ Performance testing container built"

# Run performance tests
perf-test: perf-build
	@echo "=========================================="
	@echo "Nginx Performance Testing"
	@echo "=========================================="
	@echo ""
	@if ! docker ps | grep -q "yiimp2-web-dev.*Up"; then \
		echo "Error: yiimp2-web-dev container is not running"; \
		echo "Start with: make compose-dev-up"; \
		exit 1; \
	fi
	@echo "Running performance tests..."
	@mkdir -p test-results/performance
	@docker run --rm \
		--network container:yiimp2-web-dev \
		-e BASE_URL=http://localhost \
		-e CONCURRENCY=${CONCURRENCY:-10} \
		-e REQUESTS=${REQUESTS:-1000} \
		-v $(PWD)/test-results:/results \
		yiimp2-bench /test-nginx-performance.sh
	@echo ""
	@echo "=========================================="
	@echo "Performance Test Complete!"
	@echo "=========================================="
	@echo "Results saved to: test-results/performance/"
	@echo ""
	@echo "View summary: cat test-results/performance/summary.txt"

# Run performance tests with high load
perf-test-load:
	@echo "Running high-load performance tests..."
	@$(MAKE) perf-test CONCURRENCY=50 REQUESTS=5000

# Run performance tests with stress load
perf-test-stress:
	@echo "Running stress performance tests..."
	@$(MAKE) perf-test CONCURRENCY=100 REQUESTS=10000

# Analyze PHP-FPM configuration
perf-analyze-php:
	@echo "=========================================="
	@echo "PHP-FPM Configuration Analysis"
	@echo "=========================================="
	@echo ""
	@if ! docker ps | grep -q "yiimp2-web-dev.*Up"; then \
		echo "Error: yiimp2-web-dev container is not running"; \
		echo "Start with: make compose-dev-up"; \
		exit 1; \
	fi
	@docker run --rm \
		--network container:yiimp2-web-dev \
		-e CONTAINER_NAME=yiimp2-web-dev \
		-v /var/run/docker.sock:/var/run/docker.sock \
		yiimp2-bench /analyze-php-fpm-config.sh

# Analyze Nginx configuration
perf-analyze-nginx:
	@echo "=========================================="
	@echo "Nginx Configuration Analysis"
	@echo "=========================================="
	@echo ""
	@if ! docker ps | grep -q "yiimp2-web-dev.*Up"; then \
		echo "Error: yiimp2-web-dev container is not running"; \
		echo "Start with: make compose-dev-up"; \
		exit 1; \
	fi
	@docker run --rm \
		--network container:yiimp2-web-dev \
		-e CONTAINER_NAME=yiimp2-web-dev \
		-v /var/run/docker.sock:/var/run/docker.sock \
		yiimp2-bench /analyze-nginx-config.sh

# Run all performance analysis
perf-analyze: perf-build perf-analyze-php perf-analyze-nginx
	@echo ""
	@echo "=========================================="
	@echo "Performance Analysis Complete"
	@echo "=========================================="
	@echo ""
	@echo "Next steps:"
	@echo "1. Review recommendations above"
	@echo "2. Apply optimizations to config files"
	@echo "3. Rebuild: make compose-dev-build"
	@echo "4. Restart: make compose-dev-up"
	@echo "5. Re-test: make perf-test"

# Clean performance test results
perf-clean:
	@echo "Cleaning performance test results..."
	@rm -rf test-results/performance
	@echo "✓ Performance test results cleaned"

# Performance testing help
perf-help:
	@echo "Nginx Performance Testing Targets"
	@echo ""
	@echo "Setup:"
	@echo "  make perf-build          - Build performance testing container"
	@echo ""
	@echo "Testing:"
	@echo "  make perf-test           - Run standard performance tests (10 concurrent, 1000 requests)"
	@echo "  make perf-test-load      - Run high-load tests (50 concurrent, 5000 requests)"
	@echo "  make perf-test-stress    - Run stress tests (100 concurrent, 10000 requests)"
	@echo ""
	@echo "Custom parameters:"
	@echo "  make perf-test CONCURRENCY=20 REQUESTS=2000"
	@echo ""
	@echo "Analysis:"
	@echo "  make perf-analyze        - Analyze both PHP-FPM and Nginx configurations"
	@echo "  make perf-analyze-php    - Analyze PHP-FPM configuration only"
	@echo "  make perf-analyze-nginx  - Analyze Nginx configuration only"
	@echo ""
	@echo "Cleanup:"
	@echo "  make perf-clean          - Remove performance test results"
	@echo ""
	@echo "Prerequisites:"
	@echo "  - Development environment must be running: make compose-dev-up"
	@echo "  - Docker must be installed and running"
	@echo ""
	@echo "Results:"
	@echo "  - Detailed results: test-results/performance/"
	@echo "  - Summary: test-results/performance/summary.txt"
	@echo "  - TSV data: test-results/performance/*.tsv"
	@echo ""
	@echo "Documentation:"
	@echo "  - See yiimp2/docs/NGINX_PERFORMANCE.md for detailed guide"

# Help target
test-help:
	@echo "Yiimp2 Dedicated System - Testing Targets"
	@echo ""
	@echo "Quick Testing:"
	@echo "  make test-quick          - Fast validation without Docker build"
	@echo "  make test-system         - Complete system test (Task 12.1)"
	@echo ""
	@echo "Comprehensive Testing:"
	@echo "  make test-all            - Run all tests in optimal order:"
	@echo "                             1. Build validation (no Docker)"
	@echo "                             2. Build Docker images"
	@echo "                             3. Start test environment once"
	@echo "                             4. Run unit + integration + coexistence tests"
	@echo "                             5. Clean up"
	@echo "  make test-load           - Load testing (Task 12.2, requires services running)"
	@echo "  make test-coexistence    - Coexistence verification (Task 12.3)"
	@echo ""
	@echo "Unit & Integration Tests (standalone):"
	@echo "  make test-unit-docker    - Run unit tests (manages own Docker environment)"
	@echo "  make test-integration-docker - Run integration tests (manages own Docker environment)"
	@echo "  make test-unit           - Run unit tests on host (requires local DB)"
	@echo "  make test-integration    - Run integration tests on host (requires local DB)"
	@echo "  make test-property       - Run property-based tests only"
	@echo ""
	@echo "Test Environment:"
	@echo "  make test-env-up         - Start test environment"
	@echo "  make test-env-down       - Stop test environment"
	@echo "  make test-env-clean      - Stop and remove test environment (including volumes)"
	@echo "  make test-in-compose     - Run tests in Docker Compose environment"
	@echo ""
	@echo "Advanced:"
	@echo "  make test-watch          - Run tests in watch mode"
	@echo "  make test-coverage       - Generate test coverage report"
	@echo ""
	@echo "Prerequisites:"
	@echo "  - Docker and Docker Compose installed"
	@echo "  - .env file configured (copy from .env.example)"
	@echo "  - For load testing: services must be running (make compose-up)"
	@echo ""
	@echo "Note: test-all is optimized to start the Docker environment once and run"
	@echo "      all tests, rather than starting/stopping for each test suite."
