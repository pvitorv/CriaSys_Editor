# Runtimes embutidos — PHP

Coloque o binário PHP **portable** em cada pasta antes do build:

## Windows (`win/`)
- Baixe: https://windows.php.net/download/
- Escolha: **VS16 x64 Thread Safe** ZIP
- Extraia `php.exe` + `php8.dll` + extensões necessárias para esta pasta
- Extensões mínimas: sqlite3, pdo_sqlite, mbstring, openssl, fileinfo, curl

## Linux (`linux/`)
- Use PHP static build ou extraído do pacote da distro (x64)
- `chmod +x php`

## macOS (`mac/`)
- Baixe PHP para macOS (Homebrew bottle ou static build)
- `chmod +x php`

> Sem PHP embutido, o app tenta usar `php` do PATH do sistema.
