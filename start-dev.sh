#!/bin/bash

# HASET Payment Backend - Development Server with ngrok
# This script starts both Laravel and ngrok for mobile app testing

set -e

echo "🚀 HASET Payment Backend - Development Mode"
echo "==========================================="
echo ""

# Check if Laravel server is already running
if lsof -Pi :8001 -sTCP:LISTEN -t >/dev/null 2>&1 ; then
    echo "⚠️  Port 8001 is already in use. Laravel server might be running."
    echo "   Kill it with: kill \$(lsof -t -i:8001)"
    echo ""
    read -p "Continue anyway? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Check if ngrok is installed
if ! command -v ngrok &> /dev/null; then
    echo "❌ ngrok is not installed."
    echo "   Install it from: https://ngrok.com/download"
    exit 1
fi

echo "📋 Starting services..."
echo ""

# Start Laravel in background
echo "1️⃣  Starting Laravel server on port 8001..."
php artisan serve --port=8001 > /dev/null 2>&1 &
LARAVEL_PID=$!
echo "   ✅ Laravel running (PID: $LARAVEL_PID)"
sleep 2

# Start ngrok
echo ""
echo "2️⃣  Starting ngrok tunnel..."
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📱 COPY THE HTTPS URL BELOW AND USE IT IN YOUR ANDROID APP"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Cleanup function
cleanup() {
    echo ""
    echo ""
    echo "🛑 Shutting down..."
    kill $LARAVEL_PID 2>/dev/null || true
    echo "   ✅ Laravel stopped"
    echo ""
    echo "👋 Goodbye!"
    exit 0
}

trap cleanup SIGINT SIGTERM

# Start ngrok (this will block)
ngrok http 8001

# This line will only execute if ngrok exits
cleanup
