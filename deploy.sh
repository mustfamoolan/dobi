#!/bin/bash

# Deployment script for Laravel application
# This script should be run after git pull on the server

echo "Starting deployment..."

# Navigate to project directory
cd "$(dirname "$0")"

# Remove old storage link if it exists (as directory or broken link)
if [ -d "public/storage" ] && [ ! -L "public/storage" ]; then
    echo "Removing old public/storage directory..."
    rm -rf public/storage
fi

# Remove broken symlink if exists
if [ -L "public/storage" ] && [ ! -e "public/storage" ]; then
    echo "Removing broken symlink..."
    rm -f public/storage
fi

# Create storage link if it doesn't exist
if [ ! -L "public/storage" ]; then
    echo "Creating storage symbolic link..."
    ln -s ../storage/app/public public/storage
    echo "Storage link created successfully!"
else
    echo "Storage link already exists."
fi

# Verify the link
if [ -L "public/storage" ] && [ -e "public/storage" ]; then
    echo "✓ Storage link is working correctly"
else
    echo "✗ Warning: Storage link may not be working correctly"
fi

echo "Deployment completed!"

