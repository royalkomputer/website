#!/bin/bash
echo "[Royal Komputer] Starting local development servers..."
echo ""

echo "1) Frontend (Netlify local) - http://localhost:8080"
php -S localhost:8080 -t frontend &
PID1=$!

echo "2) Backend (Render local) - http://localhost:8081"
php -S localhost:8081 -t backend &
PID2=$!

echo ""
echo "Press Ctrl+C to stop all servers."
trap "kill $PID1 $PID2 2>/dev/null; echo 'Servers stopped.'; exit 0" SIGINT SIGTERM
wait
