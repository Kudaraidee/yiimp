#!/bin/bash
# Generate secure keys for Yiimp2 deployment
# This script helps generate cryptographically secure random keys

set -e

echo "=========================================="
echo "Yiimp2 Security Key Generator"
echo "=========================================="
echo ""

# Check if openssl is available
if ! command -v openssl &> /dev/null; then
    echo "ERROR: openssl is not installed"
    echo "Please install openssl to generate secure keys"
    exit 1
fi

# Generate cookie validation key
echo "Generating Cookie Validation Key..."
COOKIE_KEY=$(openssl rand -hex 32)
echo "YIIMP_COOKIE_VALIDATION_KEY=$COOKIE_KEY"
echo ""

# Check if .env exists
if [ -f .env ]; then
    echo "WARNING: .env file already exists"
    read -p "Do you want to update the cookie validation key in .env? (y/N) " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        # Check if key already exists in .env
        if grep -q "YIIMP_COOKIE_VALIDATION_KEY=" .env; then
            # Update existing key
            if [[ "$OSTYPE" == "darwin"* ]]; then
                # macOS
                sed -i '' "s/YIIMP_COOKIE_VALIDATION_KEY=.*/YIIMP_COOKIE_VALIDATION_KEY=$COOKIE_KEY/" .env
            else
                # Linux
                sed -i "s/YIIMP_COOKIE_VALIDATION_KEY=.*/YIIMP_COOKIE_VALIDATION_KEY=$COOKIE_KEY/" .env
            fi
            echo "✓ Updated YIIMP_COOKIE_VALIDATION_KEY in .env"
        else
            # Add new key
            echo "" >> .env
            echo "# Cookie Validation Key (Generated: $(date))" >> .env
            echo "YIIMP_COOKIE_VALIDATION_KEY=$COOKIE_KEY" >> .env
            echo "✓ Added YIIMP_COOKIE_VALIDATION_KEY to .env"
        fi
    else
        echo "Skipped updating .env file"
    fi
else
    echo "INFO: .env file not found"
    read -p "Do you want to create .env from .env.example? (Y/n) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Nn]$ ]]; then
        if [ -f .env.example ]; then
            cp .env.example .env
            # Update the cookie key
            if [[ "$OSTYPE" == "darwin"* ]]; then
                # macOS
                sed -i '' "s/YIIMP_COOKIE_VALIDATION_KEY=.*/YIIMP_COOKIE_VALIDATION_KEY=$COOKIE_KEY/" .env
            else
                # Linux
                sed -i "s/YIIMP_COOKIE_VALIDATION_KEY=.*/YIIMP_COOKIE_VALIDATION_KEY=$COOKIE_KEY/" .env
            fi
            echo "✓ Created .env from .env.example"
            echo "✓ Set YIIMP_COOKIE_VALIDATION_KEY"
            echo ""
            echo "IMPORTANT: Please update the following in .env:"
            echo "  - DB_ROOT_PASSWORD"
            echo "  - DB_PASSWORD"
        else
            echo "ERROR: .env.example not found"
            exit 1
        fi
    fi
fi

echo ""
echo "=========================================="
echo "Security Recommendations"
echo "=========================================="
echo ""
echo "1. Never commit .env to version control"
echo "2. Use different keys for dev/staging/production"
echo "3. Store production keys in secure secrets management"
echo "4. Rotate keys periodically (e.g., every 90 days)"
echo "5. Enable HTTPS in production (YIIMP_FORCE_HTTPS=true)"
echo ""
echo "For production deployment:"
echo "  - Set strong database passwords"
echo "  - Enable HTTPS with valid SSL certificate"
echo "  - Set YIIMP_DEBUG=false"
echo "  - Configure appropriate session timeout"
echo ""
echo "Done!"
