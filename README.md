# 🧩 WCO Starter Theme

Customowy starter motyw WordPressa oparty o:
- **Timber / Twig**
- **ACF Blocks** (definiowane w panelu WP-Admin)
- **SCSS + Webpack** (watch & build)
- **OOP + PSR-4**
- **REST API skeleton**
- Gotowe szablony: `front-page`, `page`, `single`, `archive`, `search`, `404`

---

## 🚀 Instalacja

W katalogu `wp-content/themes`:
```bash
git clone https://github.com/<twoje-repo>/wco-starter.git
cd wco-starter
composer install
npm install
npm run build
```

## 🔁 Zmiana nazwy startera

Jeśli chcesz od razu przemianować starter pod konkretny projekt, uruchom:

```bash
npm run rename-theme -- "Nazwa Projektu" nazwa-projektu
```

To polecenie:
- zmieni `Theme Name`
- zmieni `Text Domain` i slug techniczny
- podmieni namespace PHP, REST namespace i podstawowe identyfikatory
- spróbuje też zmienić nazwę katalogu motywu

Po zakończeniu uruchom jeszcze:

```bash
composer dump-autoload -o
npm run build
```

Opcje pomocnicze:

```bash
npm run rename-theme -- "Nazwa Projektu" nazwa-projektu --dry-run
npm run rename-theme -- "Nazwa Projektu" nazwa-projektu --no-rename-dir
```
