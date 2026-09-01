<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

// Проверка прав доступа - только для администраторов
global $USER;
if (!$USER->IsAdmin()) {
    LocalRedirect('/');
    die();
}

$APPLICATION->SetTitle("Импорт товаров со старого сайта");

use Bitrix\Main\Loader;
use Bitrix\Iblock\SectionTable;

if (!Loader::includeModule('iblock')) {
    ShowError('Модуль инфоблоков не установлен');
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
    die();
}

$IBLOCK_ID = 11;

// Получаем структуру разделов БЕЗ COUNT_ELEMENTS (чтобы получить все разделы)
$sections = [];
$rsSections = CIBlockSection::GetList(
    ['SORT' => 'ASC', 'NAME' => 'ASC'],
    [
        'IBLOCK_ID' => $IBLOCK_ID,
        'ACTIVE' => 'Y'
    ],
    true,
    ['ID', 'NAME', 'CODE', 'DEPTH_LEVEL', 'LEFT_MARGIN', 'RIGHT_MARGIN', 'IBLOCK_SECTION_ID','ELEMENT_CNT']
);

while ($arSection = $rsSections->GetNext()) {
    // $arSection['ELEMENT_CNT'] = 0; // Временно ставим 0, потом добавим подсчет
    $sections[] = $arSection;
}

// Определяем, для каких разделов уже есть XML-файлы
$sectionXmlMap = [];
$dataDir = $_SERVER['DOCUMENT_ROOT'] . '/local/import/data/';
if (is_dir($dataDir)) {
    foreach (glob($dataDir . 'import_*.xml') as $xmlFilePath) {
        $fileName = basename($xmlFilePath); // import_123.xml
        if (preg_match('/^import_(\d+)\.xml$/', $fileName, $m)) {
            $sectionIdFromFile = (int)$m[1];
            $xmlTotal = null;
            // Пытаемся прочитать total из XML, но не падаем при ошибке
            if (is_readable($xmlFilePath)) {
                $xml = @simplexml_load_file($xmlFilePath);
                if ($xml !== false && isset($xml->section)) {
                    $attrs = $xml->section->attributes();
                    if (isset($attrs['total'])) {
                        $xmlTotal = (int)$attrs['total'];
                    }
                }
            }
            $sectionXmlMap[$sectionIdFromFile] = [
                'HAS_XML' => true,
                'FILE' => '/local/import/data/' . $fileName,
                'TOTAL' => $xmlTotal,
            ];
        }
    }
}


// Формируем дерево разделов с правильной иерархией
function buildSectionTree($sections, $parentId = 0, $level = 0) {
    $tree = [];
    foreach ($sections as $section) {
        // Получаем родительский ID раздела
        $sectionParentId = isset($section['IBLOCK_SECTION_ID']) && $section['IBLOCK_SECTION_ID'] 
            ? (int)$section['IBLOCK_SECTION_ID'] 
            : 0;
        
        // Если родительский ID совпадает с искомым родителем
        if ($sectionParentId == $parentId) {
            $section['LEVEL'] = $level;
            // Используем ELEMENT_CNT из результата запроса
            $section['ELEMENT_COUNT'] = isset($section['ELEMENT_CNT']) ? (int)$section['ELEMENT_CNT'] : 0;
            // Признак наличия XML-файла
            global $sectionXmlMap;
            $sectionId = (int)$section['ID'];
            $section['HAS_XML'] = isset($sectionXmlMap[$sectionId]['HAS_XML']) ? $sectionXmlMap[$sectionId]['HAS_XML'] : false;
            $section['XML_FILE'] = isset($sectionXmlMap[$sectionId]['FILE']) ? $sectionXmlMap[$sectionId]['FILE'] : '';
            $section['XML_TOTAL'] = isset($sectionXmlMap[$sectionId]['TOTAL']) ? (int)$sectionXmlMap[$sectionId]['TOTAL'] : null;
            // Рекурсивно получаем дочерние разделы
            $section['CHILDREN'] = buildSectionTree($sections, (int)$section['ID'], $level + 1);
            $tree[] = $section;
        }
    }
    return $tree;
}

$sectionTree = buildSectionTree($sections);

// Подключаем стили и скрипты
$APPLICATION->SetAdditionalCSS('/local/import/css/style.css');
$APPLICATION->AddHeadScript('/local/import/js/script.js');
?>
<br/>
<br/>
<br/>
<div class="import-container">
    <div class="import-header">
        <h1>Импорт товаров со старого сайта</h1>
        <p>Выберите раздел каталога для импорта товаров. Система соберет все ссылки на товары со страниц выбранного раздела.</p>
    </div>

    <div class="import-tabs">
        <button type="button" class="import-tab-button active" data-tab="tab-import">Импорт</button>
        <button type="button" class="import-tab-button" data-tab="tab-xml">XML по разделам</button>
    </div>

    <div class="import-tab-content active" id="tab-import">
        <form id="importForm" method="POST" action="/local/import/ajax.php">
            <?php echo $debugInfo; ?>
            <div class="section-selector">
                <h2>Выберите раздел:</h2>
                <?php if (empty($sectionTree)): ?>
                    <p style="color: #999;">Разделы не найдены в инфоблоке ID=<?=$IBLOCK_ID?></p>
                    <p style="color: #f00;">Отладка: массив sections содержит <?=count($sections)?> элементов</p>
                <?php else: ?>
                    <select name="section_id" id="sectionSelect" required>
                        <option value="">-- Выберите раздел --</option>
                        <?php
                        function renderSections($sections) {
                            foreach ($sections as $section) {
                                $level = isset($section['LEVEL']) ? (int)$section['LEVEL'] : 0;
                                $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
                                $elementCount = isset($section['ELEMENT_COUNT']) ? (int)$section['ELEMENT_COUNT'] : 0;
                                $hasXml = !empty($section['HAS_XML']);
                                $xmlLabel = $hasXml ? 'XML есть' : 'XML нет';
                                $displayName = $indent . htmlspecialchars($section['NAME']) . ' (' . htmlspecialchars($section['CODE']) . ' - ' . $elementCount . ' товаров, ' . $xmlLabel . ')';
                                ?>
                                <option value="<?=$section['ID']?>" 
                                        data-code="<?=htmlspecialchars($section['CODE'])?>" 
                                        data-name="<?=htmlspecialchars($section['NAME'])?>">
                                    <?=$displayName?>
                                </option>
                                <?php
                                // Рекурсивно выводим дочерние разделы
                                if (!empty($section['CHILDREN'])) {
                                    renderSections($section['CHILDREN']);
                                }
                            }
                        }
                        renderSections($sectionTree);
                        ?>
                    </select>
                <?php endif; ?>
            </div>
            
            <div class="import-actions">
                <button type="submit" class="btn btn-primary" id="importBtn" disabled>
                    Начать импорт
                </button>
                
                <a href="#" class="btn btn-primary disabled" id="openXmlBtn" style="margin-left: 10px;" target="_blank">
                    ОТКРЫТЬ import.xml
                </a>
            </div>
        </form>
    </div>

    <div class="import-tab-content" id="tab-xml">
        <h2>Наличие XML по разделам</h2>
        <?php if (empty($sectionTree)): ?>
            <p style="color: #999;">Разделы не найдены в инфоблоке ID=<?=$IBLOCK_ID?></p>
        <?php else: ?>
            <div class="xml-sections-table-wrapper">
                <table class="xml-sections-table">
                    <thead>
                        <tr>
                            <th>Раздел</th>
                            <th>Код</th>
                            <th>Товаров в разделе</th>
                            <th>XML</th>
                            <th>Импорт товаров</th>
                            <th>Упрощенный импорт</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        function renderXmlRows($sections) {
                            foreach ($sections as $section) {
                                $level = isset($section['LEVEL']) ? (int)$section['LEVEL'] : 0;
                                $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
                                $elementCount = isset($section['ELEMENT_COUNT']) ? (int)$section['ELEMENT_COUNT'] : 0;
                                $hasXml = !empty($section['HAS_XML']);
                                $xmlFile = !empty($section['XML_FILE']) ? $section['XML_FILE'] : '';
                                $xmlTotal = isset($section['XML_TOTAL']) ? (int)$section['XML_TOTAL'] : null;
                                $sectionId = (int)$section['ID'];
                                ?>
                                <tr>
                                    <td><?=$indent . htmlspecialchars($section['NAME'])?></td>
                                    <td><?=htmlspecialchars($section['CODE'])?></td>
                                    <td>
                                        <?php if ($xmlTotal !== null): ?>
                                            <?php
                                            $class = '';
                                            if ($elementCount < $xmlTotal) {
                                                $class = 'xml-total-mismatch';
                                            }
                                            ?>
                                            <span class="<?=$class?>"><?=$elementCount?> из <?=$xmlTotal?></span>
                                        <?php else: ?>
                                            <?=$elementCount?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($hasXml && $xmlFile): ?>
                                            <a href="<?=$xmlFile?>" target="_blank" class="xml-exists">Есть (открыть)</a>
                                        <?php else: ?>
                                            <span class="xml-missing">Нет</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($hasXml): ?>
                                            <button type="button"
                                                    class="btn xml-import-btn"
                                                    data-section-id="<?=$sectionId?>">
                                                Импортировать товары
                                            </button>
                                        <?php else: ?>
                                            <button type="button"
                                                    class="btn xml-import-btn"
                                                    data-section-id="<?=$sectionId?>"
                                                    disabled>
                                                Импортировать товары
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($hasXml): ?>
                                            <button type="button"
                                                    class="btn xml-import-simple-btn"
                                                    data-section-id="<?=$sectionId?>">
                                                Упрощенный импорт
                                            </button>
                                        <?php else: ?>
                                            <button type="button"
                                                    class="btn xml-import-simple-btn"
                                                    data-section-id="<?=$sectionId?>"
                                                    disabled>
                                                Упрощенный импорт
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                                if (!empty($section['CHILDREN'])) {
                                    renderXmlRows($section['CHILDREN']);
                                }
                            }
                        }
                        renderXmlRows($sectionTree);
                        ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="import-common-status">
        <div class="status-message" id="statusMessage"></div>
        <div class="progress-bar" id="progressBar">
            <div class="progress-fill" id="progressFill">0%</div>
        </div>
    </div>
</div>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
