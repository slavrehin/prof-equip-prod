<?php
/**
 * «Тип» товара (свойство TIP_TOVARA каталога) — сквозной фасет умного фильтра
 * на страницах брендов /brends/<код>/ (Пароконвектоматы, Сковороды, Решетки, Рамки…).
 *
 * Значение выводится из разделов товара: берётся самый глубокий раздел (при
 * равной глубине — основной), его название с поправками из ProductType::RENAME.
 * Исключения: все «Запасные части…» → «Запасные части»; «Аксессуары» → значение
 * «Тип аксессуара» (Решетки, Рамки), иначе «Аксессуары».
 *
 * Порядок значений (SORT варианта списка) задаёт и порядок в фильтре, и порядок
 * товаров на странице бренда (сортировка PROPERTYSORT_TIP_TOVARA): сначала
 * пароконвектоматы, затем остальное оборудование, аксессуары, запчасти.
 *
 * Новым и изменённым товарам значение ставится автоматически (обработчики ниже),
 * только если оно ещё пустое — ручное значение из админки не перетирается.
 * Массовое заполнение: local/deploy/backfill_tip_tovara.php.
 */

class ProductType
{
    public const PROPERTY_CODE = 'TIP_TOVARA';

    /** Разделы-витрины, а не типы товара — пропускаются при выборе раздела. */
    private const SKIP_SECTIONS = ['prachechnoe-oborudovanie-v-nalichii', 'test-import-tmp'];

    /** Код раздела → название типа (если название раздела не годится как тип). */
    private const RENAME = [
        'parokonvektomaty-rational' => 'Пароконвектоматы',
        'elektroskovorody' => 'Сковороды',
        'setevye-oteli-brend-buk' => 'Текстиль для сетевых отелей',
        'kollektsiya-izyskannaya-kuhnya' => 'Поварская форма',
        'kollektsiya-osnovnaya-kuhnya' => 'Поварская форма',
        'kollektsiya-premialnaya-kuhnya' => 'Поварская форма',
    ];

    /** Название типа → SORT варианта; остальные типы — DEFAULT_SORT. */
    private const SORT = [
        'Пароконвектоматы' => 100,
        'Аксессуары' => 800,
        'Решетки' => 810,
        'Рамки' => 820,
        'Запасные части' => 900,
    ];
    private const DEFAULT_SORT = 500;

    /** Тип товара по его разделам, null — определить нельзя (нет разделов). */
    public static function resolve(int $elementId): ?string
    {
        $element = \CIBlockElement::GetList([], ['ID' => $elementId], false, false, ['ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID'])->Fetch();
        if (!$element) {
            return null;
        }
        $mainSectionId = (int)$element['IBLOCK_SECTION_ID'];

        $best = null;
        $isTextile = false;
        $rs = \CIBlockElement::GetElementGroups($elementId, true, ['ID', 'CODE', 'NAME', 'DEPTH_LEVEL', 'LEFT_MARGIN', 'RIGHT_MARGIN']);
        while ($section = $rs->Fetch()) {
            $code = (string)$section['CODE'];
            if (str_starts_with($code, 'zapasnye-chasti')) {
                // Запчасти — по группе детали (Ремни и шкивы, Клапаны и краны…), см. CatalogStandard::PART_GROUPS
                return self::partGroup($element['IBLOCK_ID'], $elementId)[0];
            }
            if (!$isTextile && self::isTextileSection((int)$element['IBLOCK_ID'], (int)$section['LEFT_MARGIN'])) {
                $isTextile = true;
            }
            if ($code === 'aksessuary') {
                $accessoryType = \CIBlockElement::GetProperty($element['IBLOCK_ID'], $elementId, [], ['CODE' => 'TIP_AKSESSUARA'])->Fetch();
                return trim((string)($accessoryType['VALUE_ENUM'] ?? '')) ?: 'Аксессуары';
            }
            if (in_array($code, self::SKIP_SECTIONS, true)) {
                continue;
            }
            $rank = [(int)$section['DEPTH_LEVEL'], (int)$section['ID'] === $mainSectionId ? 1 : 0];
            if ($best === null || $rank > $best['rank']) {
                $best = ['rank' => $rank, 'code' => $code, 'name' => trim((string)$section['NAME'])];
            }
        }

        if ($best === null) {
            return null;
        }
        if ($isTextile) {
            // Текстиль — по виду изделия (Простыни, Брюки, Кители…), иначе по разделу
            $type = self::textileType((int)$element['IBLOCK_ID'], $elementId);
            if ($type !== null) {
                return $type;
            }
        }
        return self::RENAME[$best['code']] ?? $best['name'];
    }

    /** [группа, SORT] запчасти по «Наименованию детали» (TIP_ZAPCHASTI) и названию товара. */
    public static function partGroup(int $iblockId, int $elementId): array
    {
        $element = \CIBlockElement::GetList([], ['ID' => $elementId], false, false, ['ID', 'NAME'])->Fetch();
        $detail = \CIBlockElement::GetProperty($iblockId, $elementId, [], ['CODE' => 'TIP_ZAPCHASTI'])->Fetch();
        return \CatalogStandard::partGroup((string)($detail['VALUE_ENUM'] ?? ''), (string)($element['NAME'] ?? ''));
    }

    private static function textileType(int $iblockId, int $elementId): ?string
    {
        $element = \CIBlockElement::GetList([], ['ID' => $elementId], false, false, ['ID', 'NAME'])->Fetch();
        $tip = \CIBlockElement::GetProperty($iblockId, $elementId, [], ['CODE' => 'TIP_IZDELIYA'])->Fetch();
        return \CatalogStandard::textileType((string)($element['NAME'] ?? ''), (string)($tip['VALUE_ENUM'] ?? ''));
    }

    /** Раздел (по LEFT_MARGIN) лежит внутри «Профессионального текстиля». */
    private static function isTextileSection(int $iblockId, int $leftMargin): bool
    {
        static $bounds = null;
        if ($bounds === null) {
            $root = \CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'professionalnyj-tekstil'], false, ['LEFT_MARGIN', 'RIGHT_MARGIN'])->Fetch();
            $bounds = $root ? [(int)$root['LEFT_MARGIN'], (int)$root['RIGHT_MARGIN']] : [0, -1];
        }
        return $leftMargin >= $bounds[0] && $leftMargin <= $bounds[1];
    }

    /** SORT нового варианта «Типа»: группы запчастей 901+, текстиль 600, прочее — по таблице. */
    private static function sortFor(string $value): int
    {
        if (isset(self::SORT[$value])) {
            return self::SORT[$value];
        }
        foreach (\CatalogStandard::PART_GROUPS as [$group, $sort]) {
            if ($group === $value) {
                return $sort;
            }
        }
        if ($value === \CatalogStandard::PART_GROUP_FALLBACK[0]) {
            return \CatalogStandard::PART_GROUP_FALLBACK[1];
        }
        foreach (\CatalogStandard::TEXTILE_TYPES as [$type]) {
            if ($type === $value) {
                return \CatalogStandard::TEXTILE_TYPE_SORT;
            }
        }
        return self::DEFAULT_SORT;
    }

    public static function propertyId(int $iblockId): int
    {
        static $ids = [];
        if (!isset($ids[$iblockId])) {
            $prop = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => self::PROPERTY_CODE])->Fetch();
            $ids[$iblockId] = $prop ? (int)$prop['ID'] : 0;
        }
        return $ids[$iblockId];
    }

    /** ID варианта списка по тексту; создаёт вариант с ЧПУ-слагом в XML_ID, если его нет. */
    public static function enumId(int $propertyId, string $value): int
    {
        $existing = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $propertyId, 'VALUE' => $value])->Fetch();
        if ($existing) {
            return (int)$existing['ID'];
        }

        $slug = \CatalogStandard::slug($value);
        $xmlId = $slug;
        for ($i = 2; \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $propertyId, 'XML_ID' => $xmlId])->Fetch(); $i++) {
            $xmlId = $slug . '-' . $i;
        }

        $id = (int)(new \CIBlockPropertyEnum())->Add([
            'PROPERTY_ID' => $propertyId,
            'VALUE' => $value,
            'XML_ID' => $xmlId,
            'SORT' => self::sortFor($value),
            'DEF' => 'N',
        ]);
        if (!$id) {
            throw new \RuntimeException(self::PROPERTY_CODE . ": не создано значение «{$value}»");
        }
        return $id;
    }

    /** Текущее значение (ID варианта) у товара, 0 — пусто. */
    public static function currentEnumId(int $iblockId, int $elementId): int
    {
        $value = \CIBlockElement::GetProperty($iblockId, $elementId, [], ['CODE' => self::PROPERTY_CODE])->Fetch();
        return (int)($value['VALUE'] ?? 0);
    }

    /**
     * Ставит товару вычисленный тип. $overwrite=false — только если пусто.
     * Возвращает поставленное значение или null, если ничего не меняли.
     */
    public static function apply(int $iblockId, int $elementId, bool $overwrite = false): ?string
    {
        $propertyId = self::propertyId($iblockId);
        if (!$propertyId) {
            return null;
        }
        $current = self::currentEnumId($iblockId, $elementId);
        if ($current && !$overwrite) {
            return null;
        }
        $type = self::resolve($elementId);
        if ($type === null) {
            return null;
        }
        $enumId = self::enumId($propertyId, $type);
        if ($enumId === $current) {
            return null;
        }
        \CIBlockElement::SetPropertyValuesEx($elementId, $iblockId, [self::PROPERTY_CODE => $enumId]);
        return $type;
    }

    /** Обработчик OnAfterIBlockElementAdd / OnAfterIBlockElementUpdate. */
    public static function onAfterElementSave(array &$fields): void
    {
        if (empty($fields['RESULT']) || empty($fields['ID'])) {
            return;
        }
        $iblockId = (int)($fields['IBLOCK_ID'] ?? 0);
        if (!$iblockId || $iblockId !== (int)GetIBlockIDByCode('catalog')) {
            return;
        }
        try {
            self::apply($iblockId, (int)$fields['ID']);
        } catch (\Throwable $e) {
            // тип — вспомогательный фасет, сохранение товара из-за него не ломаем
            AddMessage2Log('ProductType: ' . $e->getMessage(), 'profequip');
        }
    }
}

$eventManager = \Bitrix\Main\EventManager::getInstance();
$eventManager->addEventHandlerCompatible('iblock', 'OnAfterIBlockElementAdd', ['ProductType', 'onAfterElementSave']);
$eventManager->addEventHandlerCompatible('iblock', 'OnAfterIBlockElementUpdate', ['ProductType', 'onAfterElementSave']);
