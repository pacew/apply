@echo off
setlocal
where py >nul 2>nul
if %errorlevel%==0 (
    py -3 "%~dp0pull_live_data.py" %*
) else (
    python "%~dp0pull_live_data.py" %*
)
echo.
pause
