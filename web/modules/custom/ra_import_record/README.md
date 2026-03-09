# Ra Import Record

Modul poskytuje odlehčenou custom entitu pro evidenci a správu importovaných dat (Nodes, Taxonomy terms) v Drupalu 11. Minimalizuje databázovou zátěž využitím pevných base fields namísto klasických polí.

## Architektura
Projekt dodržuje D11 standardy:
* PHP 8.4 syntaxe a atributy (žádné anotace).
* Striktní typování u rozhraní i tříd.
* Integrace s `EntityChangedTrait` a `ThirdPartySettings`.
* Moderní zápis Drush příkazů (`drush.services.yml` a atributy).

## Nastavení (UI)
1. Přiděl roli oprávnění **Administer Ra Import Record settings**.
2. Přejdi do nastavení konkrétního typu obsahu nebo slovníku taxonomie.
3. V sekci "Ra Import Record" zaškrtni povolení manuální editace.
4. Redaktoři s oprávněním **Manage import records on entities** nyní uvidí fieldset pro vložení metadat přímo na uzlu/termínu.

## Drush příkazy

Modul obsahuje nástroj pro hromadnou správu a rollback.

### `drush ra-import:delete` (alias: `raid`)
Smaže importované cílové entity (Nody/Termíny), případně pouze vyčistí historii importů.

**Příklady:**
* `drush raid --type=node --bundle=article --method=bulk`
  Smaže všechny automaticky importované články.
* `drush raid --type=taxonomy_term --logs-only`
  Ponechá termíny v Drupalu, ale trvale smaže jejich historii importu z tohoto modulu.

**Dostupné parametry:**
* `--type`: Typ cílové entity (`node`, `taxonomy_term`).
* `--bundle`: Strojový název typu obsahu / slovníku.
* `--method`: Metoda importu (`bulk`, `manual`).
* `--logs-only`: Přepínač pro zachování cílových entit a smazání pouze logů.

## Služba pro vývojáře (API)
Pro logování importů v custom migračních skriptech lze využít Dependency Injection a službu `ra_import_record.manager`:

```php
$importManager = \Drupal::service('ra_import_record.manager');
$importManager->createRecord($node, 'bulk', 'Záznam z XML.');