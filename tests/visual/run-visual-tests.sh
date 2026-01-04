#!/bin/bash
#
# Visual Regression Test Runner
# Feature: csp-inline-styles-removal
# Validates: Requirements 3.1, 3.2, 3.3
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Default configuration
TEST_BASE_URL="${TEST_BASE_URL:-http://localhost:8090}"
PROJECT="${PROJECT:-desktop-chrome}"
UPDATE_SNAPSHOTS="${UPDATE_SNAPSHOTS:-false}"

echo -e "${GREEN}=== Visual Regression Test Runner ===${NC}"
echo "Base URL: $TEST_BASE_URL"
echo "Project: $PROJECT"
echo ""

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    echo -e "${YELLOW}Installing dependencies...${NC}"
    npm install
fi

# Check if Playwright browsers are installed
if ! npx playwright --version > /dev/null 2>&1; then
    echo -e "${YELLOW}Installing Playwright browsers...${NC}"
    npx playwright install --with-deps
fi

# Build command
CMD="npx playwright test --config=tests/visual/playwright.config.js"

# Add project if specified
if [ "$PROJECT" != "all" ]; then
    CMD="$CMD --project=$PROJECT"
fi

# Add update snapshots flag if requested
if [ "$UPDATE_SNAPSHOTS" = "true" ]; then
    echo -e "${YELLOW}Updating baseline snapshots...${NC}"
    CMD="$CMD --update-snapshots"
fi

# Add specific test file if provided
if [ -n "$1" ]; then
    CMD="$CMD tests/visual/specs/$1"
fi

# Run tests
echo -e "${GREEN}Running visual regression tests...${NC}"
echo "Command: $CMD"
echo ""

export TEST_BASE_URL

if $CMD; then
    echo ""
    echo -e "${GREEN}✓ All visual regression tests passed!${NC}"
    exit 0
else
    echo ""
    echo -e "${RED}✗ Some visual regression tests failed.${NC}"
    echo -e "${YELLOW}View the report: npm run test:visual:report${NC}"
    exit 1
fi
