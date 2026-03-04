# WCO Starter Theme

Starter motywu WordPress oparty o:
- Timber + Twig
- ACF Blocks
- SCSS + Webpack
- OOP + PSR-4
- opcjonalne integracje WooCommerce

To README ma służyć jako szybka instrukcja startu. Jeśli wracasz do tego startera po czasie, zacznij od sekcji `Szybki start`.

## Szybki start

W katalogu motywu:

```bash
composer install
npm install
npm run build
```

Potem w WordPressie:
- aktywuj motyw
- upewnij się, że aktywne są `Timber` i `ACF Pro`
- jeśli używasz bloków ACF, wejdź do edytora i sprawdź kategorię `WCO Blocks`

Do pracy developerskiej:

```bash
npm run dev
```

## Wymagania

- WordPress 6+
- PHP 8+
- Composer
- Node.js / npm
- `Timber`
- `ACF Pro`

Ważne:
- bez `Timber` motyw nie ruszy
- bez `ACF Pro` customowe bloki nie będą widoczne w Gutenberg
- jeśli `WooCommerce` nie jest aktywne, motyw nadal działa, ale bez funkcji sklepowych

## Pierwsze uruchomienie projektu

Jeśli tworzysz nowy projekt na bazie startera:

```bash
npm run rename-theme -- "Nazwa Projektu" nazwa-projektu
```

To polecenie:
- zmienia `Theme Name`
- zmienia `Text Domain`
- zmienia slug techniczny motywu
- podmienia namespace PHP i podstawowe identyfikatory
- próbuje zmienić nazwę katalogu motywu

Po rename:

```bash
composer dump-autoload -o
npm run build
```

Opcje pomocnicze:

```bash
npm run rename-theme -- "Nazwa Projektu" nazwa-projektu --dry-run
npm run rename-theme -- "Nazwa Projektu" nazwa-projektu --no-rename-dir
```

## Najważniejsze komendy

```bash
npm run dev
npm run build
npm run rename-theme -- "Nazwa Projektu" nazwa-projektu
npm run create-block -- hero-banner "Hero Banner"
```

## Tworzenie bloków

Nowy blok generujesz komendą:

```bash
npm run create-block -- hero-banner "Hero Banner"
```

Generator tworzy:

```text
views/blocks/hero-banner/block.json
views/blocks/hero-banner/hero-banner.twig
views/blocks/hero-banner/_hero-banner.scss
views/blocks/hero-banner/hero-banner.js
views/blocks/hero-banner/hero-banner.include.php
acf-json/group_block_hero-banner.json
```

Po wygenerowaniu:

```bash
npm run build
```

Ważne:
- blok pojawia się w Gutenberg jako ACF Block
- kategoria bloków to `WCO Blocks`
- style bloku ładują się automatycznie tylko dla danego bloku
- JS bloku ładuje się automatycznie tylko wtedy, gdy blok istnieje w DOM
- field group zapisuje się lokalnie do `acf-json`

Dry run:

```bash
npm run create-block -- hero-banner "Hero Banner" --dry-run
```

## Struktura bloku

Każdy blok trzyma wszystko obok siebie:

```text
views/blocks/nazwa-bloku/
  block.json
  nazwa-bloku.twig
  _nazwa-bloku.scss
  nazwa-bloku.js
  nazwa-bloku.include.php
```

To jest celowy układ:
- `twig` = markup
- `scss` = styl bloku
- `js` = klasa OOP z vanilla JS
- `include.php` = render Timbera
- `block.json` = metadane bloku do rejestracji

## System styli

Starter ma prosty system design tokens w SCSS.

Główne pliki:
- `assets/scss/base/_variables.scss` — kolory, fonty, breakpointy, kontenery, guttery
- `assets/scss/base/_mixins.scss` — mixiny kontenerów i breakpointów
- `assets/scss/base/_reset.scss` — lekki reset globalny
- `assets/scss/base/_typography.scss` — baza typografii
- `assets/scss/_base.scss` — CSS variables i klasy kontenerów

## Kontenery

Dostępne klasy:
- `.container`
- `.container-wide`
- `.container-medium`
- `.container-narrow`

Dostępne mixiny:

```scss
@include container();
@include container(wide);
@include container(medium);
@include container(narrow);
```

Przykład:

```scss
.section {
  @include container(medium);
}
```

## Breakpointy

Dostępne mixiny:

```scss
@include mobile { ... }
@include tablet { ... }
@include laptop { ... }
@include desktop-up { ... }

@include respond-down(mobile) { ... }
@include respond-up(tablet) { ... }
@include respond-between(mobile, laptop) { ... }
```

## Gdzie zmieniać style globalne

Jeśli chcesz zmienić:
- szerokość layoutu
- guttery
- breakpointy
- paletę kolorów
- font bazowy

to zaczynasz od:

```text
assets/scss/base/_variables.scss
```

## Gdzie co jest

Najważniejsze katalogi:

```text
app/
assets/
views/
acf-json/
scripts/
public/
```

Co oznaczają:
- `app/` — PHP, rejestracje, bootstrap, logika motywu
- `assets/` — źródła SCSS i JS
- `views/` — Twig i katalogi bloków
- `acf-json/` — lokalny zapis field groups ACF
- `scripts/` — komendy pomocnicze startera
- `public/` — build z webpacka

## Typowy workflow

1. Zmień nazwę startera komendą `rename-theme`.
2. Uruchom `composer install` i `npm install`.
3. Uruchom `npm run build` albo `npm run dev`.
4. Aktywuj motyw.
5. Sprawdź, czy aktywne są `Timber` i `ACF Pro`.
6. Twórz bloki przez `npm run create-block`.
7. Trzymaj style globalne w tokenach, a blokowe obok bloków.

## Debug

Jeśli coś nie działa:

- brak widoków Twig:
  uruchom `composer install`

- brak bloków w Gutenberg:
  sprawdź, czy aktywne jest `ACF Pro`

- brak nowych assetów:
  uruchom `npm run build`

- problem po rename motywu:
  uruchom `composer dump-autoload -o`

- problem z WooCommerce:
  bez aktywnego WooCommerce funkcje sklepowe są wyłączone, ale motyw powinien działać

## Uwagi

- `public/` jest wynikiem buildu, nie edytuj go ręcznie
- style bloków i JS bloków trzymaj przy blokach
- jeśli dodajesz nowe globalne tokeny, trzymaj je w `assets/scss/base/_variables.scss`
- jeśli dodajesz override dla pluginu, nie wrzucaj tego do resetu, tylko do odpowiedniego partiala komponentu lub vendora
