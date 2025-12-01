#!/bin/bash

# Setup script for Laravel SQLite database
# This script creates the necessary database file if it doesn't exist

echo "Setting up SQLite database..."

# Create database directory if it doesn't exist
if [ ! -d "database" ]; then
    mkdir -p database
    echo "Created database directory"
fi

# Create SQLite database file if it doesn't exist
if [ ! -f "database/database.sqlite" ]; then
    touch database/database.sqlite
    echo "Created database/database.sqlite"
else
    echo "database/database.sqlite already exists"
fi

echo "Database setup complete!"
echo ""
echo "You can now run:"
echo "  php artisan migrate:fresh --seed"
echo "  php artisan serve"
