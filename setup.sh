#!/bin/bash
# setup.sh - One command setup for Lock Demo

set -e  # Exit on error

echo "Setting up Lock Demo..."

# Check requirements
if ! command -v docker &> /dev/null; then
    echo "Docker is required! Install Docker first."
    exit 1
fi

if ! command -v composer &> /dev/null; then
    echo "Composer not found, installing Sail dependencies via Docker..."
fi

# Install Sail locally
echo "Installing Sail..."
composer require laravel/sail --dev --no-scripts --quiet

# Create required directories
echo "Creating directories..."
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache
touch storage/logs/laravel.log
chmod 666 storage/logs/laravel.log

# Start services
echo "Starting Docker containers..."
./vendor/bin/sail up -d

# Wait for services
echo "Waiting for services to be ready..."
sleep 15

# Setup RabbitMQ user
echo "Setting up RabbitMQ..."
./vendor/bin/sail exec rabbitmq rabbitmqctl wait --timeout 60
./vendor/bin/sail exec rabbitmq rabbitmqctl add_user admin admin 2>/dev/null || true
./vendor/bin/sail exec rabbitmq rabbitmqctl set_user_tags admin administrator
./vendor/bin/sail exec rabbitmq rabbitmqctl set_permissions -p / admin ".*" ".*" ".*"

# Install dependencies
echo "Installing PHP dependencies..."
./vendor/bin/sail composer install --no-interaction --prefer-dist --optimize-autoloader

# Setup Laravel
echo "Configuring Laravel..."
./vendor/bin/sail artisan key:generate --force
./vendor/bin/sail artisan migrate --force

# Clear ALL Laravel cache (CRITICAL for Vite)
echo "Clearing Laravel cache..."
./vendor/bin/sail artisan optimize:clear

# Install & build frontend
echo "Installing frontend dependencies..."
./vendor/bin/sail npm install --silent
./vendor/bin/sail npm run build --silent

# Restart to apply all changes (supervisor will restart workers)
echo "Restarting services..."
./vendor/bin/sail restart

echo ""
echo "Setup complete!"
echo "Open: http://localhost"
echo "RabbitMQ UI: http://localhost:15672 (admin/admin)"
echo "Logs: ./vendor/bin/sail logs -f"
echo ""
echo "To stop: ./vendor/bin/sail down"