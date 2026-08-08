$root = $PSScriptRoot

Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$root\arab-contractors-union-api'; php artisan serve --port=8000"
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$root\arab-contractors-union-front'; npm run dev"
