<?php
/**
 * Общие функции миграций 2026-10-13-catalog-*.php (реструктуризация каталога и стандарт
 * характеристик). Подключается require_once из миграций и скриптов local/deploy/catalog_restructure/.
 *
 * Важное:
 *  - Инфоблок каталога хранит свойства в b_iblock_element_property (VERSION=1). Для списков
 *    VALUE = VALUE_ENUM = VALUE_NUM = ID варианта. Слияние вариантов делаем SQL-ом (тысячи строк,
 *    API по одному товару слишком медленный и пересохраняет элементы целиком).
 *  - Любая смена XML_ID варианта (это сегмент ЧПУ фильтра /f/<свойство>-is-<xml_id>/) пишется в
 *    b_profequip_slug_history — section.php по ней отдаёт 301 со старого адреса на новый.
 */

final class CatalogMigrate
{
    public const HISTORY_TABLE = 'b_profequip_slug_history';

    public static function iblockId(): int
    {
        \CModule::IncludeModule('iblock');
        $id = (int)GetIBlockIDByCode('catalog');
        if (!$id) {
            throw new \RuntimeException('Инфоблок catalog не найден');
        }
        return $id;
    }

    public static function db(): \CDatabase
    {
        global $DB;
        return $DB;
    }

    public static function ensureHistoryTable(): void
    {
        $DB = self::db();
        if (!$DB->Query("SHOW TABLES LIKE '" . self::HISTORY_TABLE . "'")->Fetch()) {
            $DB->Query("CREATE TABLE " . self::HISTORY_TABLE . " (
                ID INT NOT NULL AUTO_INCREMENT,
                PROPERTY_CODE VARCHAR(100) NOT NULL,
                OLD_SLUG VARCHAR(255) NOT NULL,
                NEW_SLUG VARCHAR(255) NOT NULL,
                CREATED_AT DATETIME NOT NULL,
                PRIMARY KEY (ID),
                UNIQUE KEY UX_OLD (PROPERTY_CODE, OLD_SLUG)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            echo "Создана таблица " . self::HISTORY_TABLE . "\n";
        }
    }

    /** Запомнить, что /f/<code>-is-<old>/ теперь /f/<code>-is-<new>/ (или 'num:<n>' для числовых). */
    public static function remember(string $propertyCode, string $oldSlug, string $newSlug): void
    {
        if ($oldSlug === '' || $oldSlug === $newSlug) {
            return;
        }
        self::ensureHistoryTable();
        $DB = self::db();
        $p = $DB->ForSql(mb_strtolower($propertyCode));
        $o = $DB->ForSql(mb_strtolower($oldSlug));
        $n = $DB->ForSql($newSlug);
        $DB->Query("INSERT INTO " . self::HISTORY_TABLE . " (PROPERTY_CODE, OLD_SLUG, NEW_SLUG, CREATED_AT)
            VALUES ('$p', '$o', '$n', NOW()) ON DUPLICATE KEY UPDATE NEW_SLUG = VALUES(NEW_SLUG)");
        // цепочки: всё, что вело на старый слаг, теперь ведёт на новый
        $DB->Query("UPDATE " . self::HISTORY_TABLE . " SET NEW_SLUG = '$n' WHERE PROPERTY_CODE = '$p' AND NEW_SLUG = '$o'");
        // слаг вернулся к прежнему значению — запись «сам на себя» не нужна
        $DB->Query("DELETE FROM " . self::HISTORY_TABLE . " WHERE PROPERTY_CODE = '$p' AND OLD_SLUG = NEW_SLUG");
    }

    public static function property(string $code): ?array
    {
        $p = \CIBlockProperty::GetList([], ['IBLOCK_ID' => self::iblockId(), 'CODE' => $code])->Fetch();
        return $p ?: null;
    }

    /** @return array<int, array{ID:int, VALUE:string, XML_ID:string, SORT:int}> */
    public static function enums(int $propertyId): array
    {
        $out = [];
        $rs = self::db()->Query('SELECT ID, VALUE, XML_ID, SORT FROM b_iblock_property_enum WHERE PROPERTY_ID = ' . $propertyId . ' ORDER BY ID');
        while ($r = $rs->Fetch()) {
            $out[(int)$r['ID']] = ['ID' => (int)$r['ID'], 'VALUE' => (string)$r['VALUE'], 'XML_ID' => (string)$r['XML_ID'], 'SORT' => (int)$r['SORT']];
        }
        return $out;
    }

    /** Перевести все товары с варианта $fromId на $toId и удалить $fromId (история слага сохраняется). */
    public static function mergeEnum(array $prop, int $fromId, int $toId): int
    {
        if ($fromId === $toId) {
            return 0;
        }
        $DB = self::db();
        $pid = (int)$prop['ID'];
        $enums = self::enums($pid);
        if (!isset($enums[$fromId], $enums[$toId])) {
            return 0;
        }
        // товары, у которых уже есть целевое значение — просто удаляем строку со старым
        $DB->Query("DELETE v FROM b_iblock_element_property v
            JOIN b_iblock_element_property t ON t.IBLOCK_ELEMENT_ID = v.IBLOCK_ELEMENT_ID AND t.IBLOCK_PROPERTY_ID = $pid AND t.VALUE_ENUM = $toId
            WHERE v.IBLOCK_PROPERTY_ID = $pid AND v.VALUE_ENUM = $fromId");
        $DB->Query("UPDATE b_iblock_element_property SET VALUE = '$toId', VALUE_ENUM = $toId, VALUE_NUM = $toId
            WHERE IBLOCK_PROPERTY_ID = $pid AND VALUE_ENUM = $fromId");
        $moved = (int)\Bitrix\Main\Application::getConnection()->getAffectedRowsCount();
        self::remember($prop['CODE'], $enums[$fromId]['XML_ID'], $enums[$toId]['XML_ID']);
        \CIBlockPropertyEnum::Delete($fromId);
        return $moved;
    }

    /** Найти вариант по точному тексту или создать. */
    public static function enumIdFor(array $prop, string $value, ?int $sort = null): int
    {
        foreach (self::enums((int)$prop['ID']) as $e) {
            if ($e['VALUE'] === $value) {
                return $e['ID'];
            }
        }
        $id = (int)(new \CIBlockPropertyEnum())->Add([
            'PROPERTY_ID' => (int)$prop['ID'],
            'VALUE' => $value,
            'XML_ID' => self::uniqueSlug((int)$prop['ID'], \CatalogStandard::slug($value)),
            'SORT' => $sort ?? 500,
        ]);
        if (!$id) {
            throw new \RuntimeException("{$prop['CODE']}: не создан вариант «{$value}»");
        }
        return $id;
    }

    public static function uniqueSlug(int $propertyId, string $slug, int $exceptEnumId = 0): string
    {
        $DB = self::db();
        $candidate = $slug;
        for ($i = 2; ; $i++) {
            $c = $DB->ForSql($candidate);
            $busy = $DB->Query("SELECT ID FROM b_iblock_property_enum WHERE PROPERTY_ID = $propertyId AND XML_ID = '$c' AND ID <> $exceptEnumId")->Fetch();
            if (!$busy) {
                return $candidate;
            }
            $candidate = $slug . '-' . $i;
        }
    }

    /** Сменить текст и/или XML_ID варианта, с записью старого слага в историю. */
    public static function updateEnum(array $prop, int $enumId, array $fields): void
    {
        $enums = self::enums((int)$prop['ID']);
        if (!isset($enums[$enumId])) {
            return;
        }
        $old = $enums[$enumId];
        if (isset($fields['XML_ID'])) {
            $fields['XML_ID'] = self::uniqueSlug((int)$prop['ID'], $fields['XML_ID'], $enumId);
        }
        $DB = self::db();
        $sets = [];
        foreach ($fields as $k => $v) {
            $sets[] = $k . " = '" . $DB->ForSql((string)$v) . "'";
        }
        $DB->Query('UPDATE b_iblock_property_enum SET ' . implode(', ', $sets) . ' WHERE ID = ' . $enumId);
        if (isset($fields['XML_ID'])) {
            self::remember($prop['CODE'], $old['XML_ID'], $fields['XML_ID']);
        }
    }

    public static function section(string $code): ?array
    {
        $s = \CIBlockSection::GetList([], ['IBLOCK_ID' => self::iblockId(), 'CODE' => $code], false,
            ['ID', 'CODE', 'NAME', 'IBLOCK_SECTION_ID', 'LEFT_MARGIN', 'RIGHT_MARGIN', 'DEPTH_LEVEL', 'ACTIVE', 'PICTURE', 'SORT'])->Fetch();
        return $s ?: null;
    }

    public static function elementByCode(string $code): ?array
    {
        $e = \CIBlockElement::GetList([], ['IBLOCK_ID' => self::iblockId(), '=CODE' => $code], false, false, ['ID', 'NAME', 'IBLOCK_SECTION_ID'])->Fetch();
        return $e ?: null;
    }

    /** CODE разделов, к которым привязан товар. @return array<int,string> ID => CODE */
    public static function elementSections(int $elementId): array
    {
        $out = [];
        $rs = \CIBlockElement::GetElementGroups($elementId, true, ['ID', 'CODE']);
        while ($s = $rs->Fetch()) {
            $out[(int)$s['ID']] = (string)$s['CODE'];
        }
        return $out;
    }

    /** Значения свойства товаров пачкой: element_id => [value...] (для списков — текст варианта). */
    public static function values(string $code, ?array $elementIds = null): array
    {
        $prop = self::property($code);
        if (!$prop) {
            return [];
        }
        $DB = self::db();
        $pid = (int)$prop['ID'];
        $where = $elementIds !== null ? ' AND v.IBLOCK_ELEMENT_ID IN (' . (implode(',', array_map('intval', $elementIds)) ?: '0') . ')' : '';
        $sql = $prop['PROPERTY_TYPE'] === 'L'
            ? "SELECT v.IBLOCK_ELEMENT_ID E, en.VALUE V FROM b_iblock_element_property v JOIN b_iblock_property_enum en ON en.ID = v.VALUE_ENUM WHERE v.IBLOCK_PROPERTY_ID = $pid$where"
            : "SELECT v.IBLOCK_ELEMENT_ID E, v.VALUE V FROM b_iblock_element_property v WHERE v.IBLOCK_PROPERTY_ID = $pid$where";
        $out = [];
        $rs = $DB->Query($sql);
        while ($r = $rs->Fetch()) {
            $out[(int)$r['E']][] = (string)$r['V'];
        }
        return $out;
    }

    public static function clearCache(): void
    {
        \CIBlock::clearIblockTagCache(self::iblockId());
        BXClearCache(true, '/s1/bitrix/catalog.section/');
        BXClearCache(true, '/s1/bitrix/catalog.smart.filter/');
        BXClearCache(true, '/s1/bitrix/catalog.section.list/');
        BXClearCache(true, '/s1/bitrix/catalog.element/');
        BXClearCache(true, '/s1/bitrix/news.list/');
        $GLOBALS['CACHE_MANAGER']->CleanAll();
    }
}
