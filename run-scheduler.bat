@echo off
cd /d "D:\xxxamp\htdocs\Seminario Licoreria\Servidor\Laravel_Licoreria"
"D:\xxxamp\php\php.exe" artisan schedule:run >> "D:\xxxamp\htdocs\Seminario Licoreria\Servidor\Laravel_Licoreria\storage\logs\scheduler.log" 2>&1