# D-Street PrestaShop Import Tool

Tento nástroj slouží k importu produktů a aktualizaci skladových zásob z D-Street do PrestaShop e-shopu.

## Instalace

1. Naklonujte tento repozitář:
```bash
git clone <repository-url>
cd dstreet
```

2. Nainstalujte závislosti pomocí Composer:
```bash
composer install
```

3. Vytvořte produkční konfigurační soubor:
```bash
copy defines.cfg.php defines-prod.cfg.php
```

4. Upravte `defines-prod.cfg.php` s vašimi skutečnými přihlašovacími údaji:
   - `PS_SHOP_PATH` - URL adresa vašeho PrestaShop e-shopu
   - `PS_WS_AUTH_KEY` - API klíč z administrace PrestaShop (Nastavení > Webové služby)
   - `MANUFACTURER_ID` - ID výrobce v PrestaShop
   - `DEBUG` - nastavte na `true` pro ladění

## Konfigurace

### Soubory konfigurace

- **defines.cfg.php** - Výchozí konfigurace (pouze ukázka, verzována v gitu)
- **defines-prod.cfg.php** - Produkční konfigurace s vašimi přihlašovacími údaji (NENÍ v gitu, musíte vytvořit)
- **codesConversion.cfg.php** - Mapování kódů velikostí
- **iniSets.cfg.php** - PHP nastavení

⚠️ **Důležité**: Soubor `defines-prod.cfg.php` není verzován v gitu a obsahuje citlivé údaje. Nikdy jej necommitujte!

## Použití

### Import produktů

```bash
php import-dstreets.php
```

Tento skript:
1. Načte produkty z PrestaShop podle ID výrobce
2. Zpracuje XML soubor s aktuálními daty z D-Street
3. Vytvoří XML soubory pro aktualizaci
4. Aktualizuje skladové zásoby a viditelnost produktů

### Odeslání aktualizací do PrestaShop

```bash
php sendXmlFileToAPI.php
```

Tento skript odešle vygenerované XML soubory do PrestaShop API.

## Struktura projektu

```
├── defines.cfg.php              # Výchozí konfigurace (příklad)
├── defines-prod.cfg.php         # Vaše produkční konfigurace (není v gitu)
├── codesConversion.cfg.php      # Mapování kódů
├── iniSets.cfg.php              # PHP nastavení
├── prestashopImportClass.php    # Hlavní třída pro import
├── import-dstreets.php          # Import script
├── sendXmlFileToAPI.php         # Script pro odeslání do API
├── PSWebServiceLibrary.php      # PrestaShop Web Service library
├── composer.json                # Závislosti
└── webservicesxml/              # XML soubory pro aktualizace
    ├── products_notfind.xml
    ├── products_update.xml
    └── stock_availables_update.xml
```

## Jak to funguje

1. Systém automaticky použije `defines-prod.cfg.php` pokud existuje, jinak použije `defines.cfg.php`
2. Import načte produkty z PrestaShop a porovná je s daty z D-Street
3. Vytvoří XML soubory s aktualizacemi skladových zásob a viditelnosti
4. Odešle aktualizace do PrestaShop přes Web Service API

## Požadavky

- PHP 7.4 nebo vyšší
- Composer
- PrestaShop s povolenou Web Service API
- Přístupové údaje k PrestaShop API

## Podpora

Pro hlášení problémů nebo dotazy vytvořte issue v tomto repozitáři.
