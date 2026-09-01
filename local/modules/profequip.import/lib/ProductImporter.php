<?php

namespace ProfEquip\Import;

use CIBlockElement;
use CIBlockSection;
use CIBlockPropertyEnum;
use CCatalogProduct;
use CPrice;
use CUtil;

/**
 * Импорт товаров в инфоблок "Каталог" (ID=11) из CSV-файла.
 *
 * Формат CSV: разделитель ";", кодировка UTF-8 (с BOM или без), первая строка - заголовки.
 * Обязательные колонки: SECTION_CODE, NAME, PRICE.
 * Опциональные "базовые" колонки: CODE, ACTIVE, CURRENCY, QUANTITY, MEASURE,
 * PREVIEW_TEXT, DETAIL_TEXT.
 * Любая другая колонка сопоставляется по CODE со свойством инфоблока (регистр не важен).
 * Для множественных значений свойства использовать разделитель "|" внутри ячейки.
 */
class ProductImporter
{
    public const IBLOCK_ID = 11;
    public const PRICE_TYPE_ID = 1; // BASE_PRICE
    public const DEFAULT_MEASURE = 796; // шт.
    public const DEFAULT_CURRENCY = 'RUB';

    private const CORE_COLUMNS = [
        'SECTION_CODE', 'CODE', 'NAME', 'ACTIVE', 'PRICE', 'CURRENCY',
        'QUANTITY', 'MEASURE', 'PREVIEW_TEXT', 'DETAIL_TEXT',
    ];

    /** @var array<string,array> CODE(upper) => свойство инфоблока */
    private array $propertyMap = [];

    /** @var array<int,int> sectionCode => sectionId, кэш на время импорта */
    private array $sectionCache = [];

    /** @var array<string,int> "linkIblockId:name" => elementId, кэш на время импорта */
    private array $linkElementCache = [];

    public function __construct()
    {
        \CModule::IncludeModule('iblock');
        \CModule::IncludeModule('catalog');
        $this->loadPropertyMap();
    }

    private function loadPropertyMap(): void
    {
        $rs = \CIBlockProperty::GetList(['SORT' => 'ASC'], ['IBLOCK_ID' => self::IBLOCK_ID]);
        while ($prop = $rs->Fetch()) {
            $this->propertyMap[mb_strtoupper($prop['CODE'])] = $prop;
        }
    }

    /**
     * Читает CSV-файл в массив ассоциативных строк (ключи = заголовки колонок).
     */
    public function parseCsv(string $filePath): array
    {
        $raw = file_get_contents($filePath);
        if ($raw === false) {
            throw new \RuntimeException('Не удалось прочитать файл');
        }

        // Снять BOM
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        // Автоопределение кодировки (Excel на Windows часто сохраняет CSV в Windows-1251)
        $encoding = mb_detect_encoding($raw, ['UTF-8', 'Windows-1251'], true);
        if ($encoding !== false && $encoding !== 'UTF-8') {
            $raw = iconv($encoding, 'UTF-8//IGNORE', $raw);
        }

        $tmp = fopen('php://temp', 'r+');
        fwrite($tmp, $raw);
        rewind($tmp);

        $rows = [];
        $header = null;
        while (($data = fgetcsv($tmp, 0, ';')) !== false) {
            if ($data === [null] || $data === false) {
                continue;
            }
            // пропускаем пустые строки
            if (count($data) === 1 && trim((string)$data[0]) === '') {
                continue;
            }
            // пропускаем строки-комментарии, начинающиеся с #
            if (isset($data[0]) && str_starts_with(trim((string)$data[0]), '#')) {
                continue;
            }
            if ($header === null) {
                $header = array_map(static fn($h) => trim((string)$h), $data);
                continue;
            }
            $row = [];
            foreach ($header as $i => $col) {
                $row[$col] = isset($data[$i]) ? trim((string)$data[$i]) : '';
            }
            $rows[] = $row;
        }
        fclose($tmp);

        return $rows;
    }

    /**
     * Находит ID раздела по CODE в пределах инфоблока «Каталог» (на любой глубине).
     */
    private function resolveSection(string $code): ?int
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        if (isset($this->sectionCache[$code])) {
            return $this->sectionCache[$code];
        }

        $rs = CIBlockSection::GetList(
            [],
            ['IBLOCK_ID' => self::IBLOCK_ID, 'CODE' => $code],
            false,
            ['ID']
        );
        $id = null;
        if ($section = $rs->Fetch()) {
            $id = (int)$section['ID'];
        }
        $this->sectionCache[$code] = $id;

        return $id;
    }

    /**
     * Находит ID значения списочного свойства (enum) по тексту, создаёт при отсутствии.
     */
    private function resolveEnumValue(int $propertyId, string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $rs = CIBlockPropertyEnum::GetList(
            [],
            ['PROPERTY_ID' => $propertyId, 'VALUE' => $value]
        );
        if ($enum = $rs->Fetch()) {
            return (int)$enum['ID'];
        }

        // не нашли точное совпадение - создаём новый вариант списка
        $enumId = CIBlockPropertyEnum::Add([
            'PROPERTY_ID' => $propertyId,
            'VALUE' => $value,
            'DEF' => 'N',
        ]);

        return $enumId ?: null;
    }

    /**
     * Находит элемент по NAME в связанном инфоблоке (например, "Бренды"), создаёт при отсутствии.
     */
    private function resolveElementLink(int $linkIblockId, string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $cacheKey = $linkIblockId . ':' . mb_strtolower($name);
        if (isset($this->linkElementCache[$cacheKey])) {
            return $this->linkElementCache[$cacheKey];
        }

        $rs = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $linkIblockId, 'NAME' => $name],
            false,
            false,
            ['ID']
        );
        if ($el = $rs->Fetch()) {
            $id = (int)$el['ID'];
            $this->linkElementCache[$cacheKey] = $id;
            return $id;
        }

        $ib = new CIBlockElement();
        $newId = $ib->Add([
            'IBLOCK_ID' => $linkIblockId,
            'NAME' => $name,
            'ACTIVE' => 'Y',
            'CODE' => CUtil::translit($name, 'ru', ['max_len' => 100]),
        ]);
        if (!$newId) {
            return null;
        }
        $this->linkElementCache[$cacheKey] = (int)$newId;

        return (int)$newId;
    }

    /**
     * Собирает значения "дополнительных" (не базовых) колонок в массив свойств
     * для CIBlockElement::SetPropertyValuesEx.
     */
    private function buildPropertyValues(array $row): array
    {
        $values = [];
        foreach ($row as $col => $cellValue) {
            $colUpper = mb_strtoupper(trim($col));
            if (in_array($colUpper, self::CORE_COLUMNS, true)) {
                continue;
            }
            if ($cellValue === '') {
                continue;
            }
            if (!isset($this->propertyMap[$colUpper])) {
                continue; // колонка не соответствует ни одному свойству - игнорируем
            }
            $prop = $this->propertyMap[$colUpper];
            $isMultiple = $prop['MULTIPLE'] === 'Y';
            $parts = $isMultiple ? array_map('trim', explode('|', $cellValue)) : [$cellValue];
            $parts = array_filter($parts, static fn($v) => $v !== '');

            $resolved = [];
            foreach ($parts as $part) {
                switch ($prop['PROPERTY_TYPE']) {
                    case 'L':
                        $enumId = $this->resolveEnumValue((int)$prop['ID'], $part);
                        if ($enumId !== null) {
                            $resolved[] = $enumId;
                        }
                        break;
                    case 'E':
                        if ((int)$prop['LINK_IBLOCK_ID'] > 0) {
                            $linkId = $this->resolveElementLink((int)$prop['LINK_IBLOCK_ID'], $part);
                            if ($linkId !== null) {
                                $resolved[] = $linkId;
                            }
                        }
                        break;
                    default:
                        $resolved[] = $part;
                }
            }

            if (empty($resolved)) {
                continue;
            }
            $values[$prop['CODE']] = $isMultiple ? $resolved : $resolved[0];
        }

        return $values;
    }

    /**
     * Импортирует одну строку. Возвращает ['status'=>'created'|'updated'|'error', 'message'=>string, 'id'=>int|null]
     */
    public function importRow(array $row): array
    {
        $name = trim((string)($row['NAME'] ?? ''));
        $sectionCode = trim((string)($row['SECTION_CODE'] ?? ''));
        $priceRaw = trim((string)($row['PRICE'] ?? ''));

        if ($name === '') {
            return ['status' => 'error', 'message' => 'Не заполнено поле NAME', 'id' => null];
        }
        if ($sectionCode === '') {
            return ['status' => 'error', 'message' => 'Не заполнено поле SECTION_CODE', 'id' => null];
        }
        if ($priceRaw === '' || !is_numeric(str_replace(',', '.', $priceRaw))) {
            return ['status' => 'error', 'message' => 'Некорректная цена PRICE: "' . $priceRaw . '"', 'id' => null];
        }
        $price = (float)str_replace(',', '.', $priceRaw);

        $sectionId = $this->resolveSection($sectionCode);
        if ($sectionId === null) {
            return ['status' => 'error', 'message' => 'Раздел с CODE="' . $sectionCode . '" не найден в каталоге', 'id' => null];
        }

        $code = trim((string)($row['CODE'] ?? ''));
        if ($code === '') {
            $code = CUtil::translit($name, 'ru', ['max_len' => 100, 'change_case' => 'L']);
        }

        $active = mb_strtoupper(trim((string)($row['ACTIVE'] ?? 'Y'))) === 'N' ? 'N' : 'Y';
        $currency = trim((string)($row['CURRENCY'] ?? '')) ?: self::DEFAULT_CURRENCY;
        $quantity = is_numeric($row['QUANTITY'] ?? null) ? (float)$row['QUANTITY'] : 0;
        $measure = is_numeric($row['MEASURE'] ?? null) ? (int)$row['MEASURE'] : self::DEFAULT_MEASURE;
        $previewText = (string)($row['PREVIEW_TEXT'] ?? '');
        $detailText = (string)($row['DETAIL_TEXT'] ?? '');

        // существующий товар в этом разделе с таким же CODE - обновляем, иначе создаём новый
        $existingId = null;
        $rsExisting = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => self::IBLOCK_ID, 'CODE' => $code, 'SECTION_ID' => $sectionId],
            false,
            false,
            ['ID']
        );
        if ($existing = $rsExisting->Fetch()) {
            $existingId = (int)$existing['ID'];
        }

        $fields = [
            'IBLOCK_ID' => self::IBLOCK_ID,
            'IBLOCK_SECTION_ID' => $sectionId,
            'NAME' => $name,
            'CODE' => $code,
            'ACTIVE' => $active,
            'PREVIEW_TEXT' => $previewText,
            'DETAIL_TEXT' => $detailText,
        ];

        $ib = new CIBlockElement();
        if ($existingId) {
            if (!$ib->Update($existingId, $fields)) {
                return ['status' => 'error', 'message' => 'Ошибка обновления элемента: ' . $ib->LAST_ERROR, 'id' => null];
            }
            $elementId = $existingId;
            $status = 'updated';
        } else {
            $fields['PROPERTY_VALUES'] = [];
            $elementId = $ib->Add($fields);
            if (!$elementId) {
                return ['status' => 'error', 'message' => 'Ошибка создания элемента: ' . $ib->LAST_ERROR, 'id' => null];
            }
            $status = 'created';
        }

        // Каталог: товар (остаток/ед.изм.)
        $catalogFields = [
            'ID' => $elementId,
            'QUANTITY' => $quantity,
            'MEASURE' => $measure,
        ];
        CCatalogProduct::Add($catalogFields);

        // Цена
        $rsPrice = CPrice::GetList(
            [],
            ['PRODUCT_ID' => $elementId, 'CATALOG_GROUP_ID' => self::PRICE_TYPE_ID]
        );
        $priceFields = [
            'PRODUCT_ID' => $elementId,
            'CATALOG_GROUP_ID' => self::PRICE_TYPE_ID,
            'PRICE' => $price,
            'CURRENCY' => $currency,
        ];
        if ($existingPrice = $rsPrice->Fetch()) {
            CPrice::Update((int)$existingPrice['ID'], $priceFields);
        } else {
            CPrice::Add($priceFields);
        }

        // Дополнительные свойства (динамическое сопоставление колонок с CODE свойств)
        $propValues = $this->buildPropertyValues($row);
        if (!empty($propValues)) {
            CIBlockElement::SetPropertyValuesEx($elementId, self::IBLOCK_ID, $propValues);
        }

        return ['status' => $status, 'message' => 'OK', 'id' => $elementId];
    }

    /**
     * Импортирует весь файл, возвращает сводку и постройчный лог.
     */
    public function importFile(string $filePath): array
    {
        $rows = $this->parseCsv($filePath);

        $summary = ['total' => count($rows), 'created' => 0, 'updated' => 0, 'errors' => 0];
        $log = [];

        foreach ($rows as $i => $row) {
            $result = $this->importRow($row);
            $result['row'] = $i + 2; // +1 заголовок, +1 счёт с единицы
            $result['name'] = $row['NAME'] ?? '';
            $log[] = $result;

            if ($result['status'] === 'created') {
                $summary['created']++;
            } elseif ($result['status'] === 'updated') {
                $summary['updated']++;
            } else {
                $summary['errors']++;
            }
        }

        return ['summary' => $summary, 'log' => $log];
    }
}
