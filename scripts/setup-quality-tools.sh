#!/bin/bash

# Shmallergies API - Code Quality Tools Setup
# This script installs and configures all code quality tools

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
API_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo -e "${BLUE}🛠️  Setting up code quality tools for Shmallergies API...${NC}"
echo "========================================================="

# Change to API directory
cd "$API_DIR"

# Function to run a command with status reporting
run_step() {
    local step_name="$1"
    local command="$2"
    
    echo -e "\n${BLUE}$step_name...${NC}"
    
    if eval "$command" > /tmp/setup-output 2>&1; then
        echo -e "${GREEN}✅ $step_name completed${NC}"
        return 0
    else
        echo -e "${RED}❌ $step_name failed${NC}"
        cat /tmp/setup-output
        return 1
    fi
}

# 1. Install/Update Composer dependencies
run_step "Installing Composer dependencies" "composer install --dev"

# 2. Create storage directories for PHPStan
run_step "Creating PHPStan cache directory" "mkdir -p storage/phpstan"

# 3. Install Git hooks
if [ -d ".git" ]; then
    run_step "Installing Git pre-commit hook" "composer install-hooks"
else
    echo -e "${YELLOW}⚠️  Not a Git repository - skipping Git hooks installation${NC}"
fi

# 4. Run initial quality check to verify setup
echo -e "\n${BLUE}🧪 Running initial quality check...${NC}"
if composer quality > /tmp/quality-output 2>&1; then
    echo -e "${GREEN}✅ Initial quality check passed!${NC}"
else
    echo -e "${YELLOW}⚠️  Initial quality check found issues - this is normal for existing code${NC}"
    echo -e "${YELLOW}Run 'composer quality-fix' to automatically fix most issues${NC}"
fi

# 5. Display summary and usage instructions
echo -e "\n========================================================="
echo -e "${GREEN}🎉 Code quality tools setup completed!${NC}"
echo -e "\n${BLUE}Available commands:${NC}"
echo -e "  ${YELLOW}composer lint${NC}        - Check PHP syntax"
echo -e "  ${YELLOW}composer pint${NC}        - Fix code style issues"
echo -e "  ${YELLOW}composer pint-check${NC}  - Check code style without fixing"
echo -e "  ${YELLOW}composer phpstan${NC}     - Run static analysis"
echo -e "  ${YELLOW}composer phpmd${NC}       - Run mess detection"
echo -e "  ${YELLOW}composer test${NC}        - Run unit tests"
echo -e "  ${YELLOW}composer quality${NC}     - Run all quality checks"
echo -e "  ${YELLOW}composer quality-fix${NC} - Run quality checks and fix issues"

echo -e "\n${BLUE}Git integration:${NC}"
echo -e "  Pre-commit hooks are now active and will run quality checks before each commit"
echo -e "  To bypass temporarily: ${YELLOW}git commit --no-verify${NC}"

echo -e "\n${BLUE}Configuration files created:${NC}"
echo -e "  📄 pint.json     - Laravel Pint (code style)"
echo -e "  📄 phpstan.neon  - PHPStan/Larastan (static analysis)"
echo -e "  📄 phpmd.xml     - PHPMD (mess detection)"

echo -e "\n${GREEN}Happy coding! 🚀${NC}"

# Clean up
rm -f /tmp/setup-output /tmp/quality-output 