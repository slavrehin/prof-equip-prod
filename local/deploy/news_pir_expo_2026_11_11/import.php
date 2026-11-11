<?php
/**
 * Новость «ПРОФЭКВИП на ПИР ЭКСПО 2026: собственная прачечная или аутсорсинг?»,
 * Б24 #9683 «Новость на сайт ПЭ» (2026-11-11). Текст — описание задачи, фото — 2 вложения (PNG → JPEG):
 *   pir-expo-2026-1.jpg — экран с темой выступления (обложка),
 *   pir-expo-2026-2.jpg — Виталий Петриков на сцене (в тексте, копируется в /upload/news/pir-expo-2026/).
 *
 * Запуск (локально, на dev и на проде, из корня сайта, от www-data):
 *   php local/deploy/news_pir_expo_2026_11_11/import.php [--dry]
 * Идемпотентен: если новость с этим CODE уже есть, ничего не меняет.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');

$dry = in_array('--dry', $argv, true);
$ib = (int) \CIBlock::GetList([], ['CODE' => 'news', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];
$code = 'profekvip-na-pir-expo-2026-sobstvennaya-prachechnaya-ili-autsorsing';
$activeFrom = '11.11.2026 00:00:00'; // без времени: дата «в будущем» даёт 404 на детальной

// Фото в тексте — в /upload (add-only)
$imgDir = '/upload/news/pir-expo-2026';
$inline = $imgDir . '/vystuplenie-vitaliya-petrikova.jpg';
if (!is_file($_SERVER['DOCUMENT_ROOT'] . $inline) && !$dry) {
    if (!is_dir($_SERVER['DOCUMENT_ROOT'] . $imgDir)) mkdir($_SERVER['DOCUMENT_ROOT'] . $imgDir, 0775, true);
    copy(__DIR__ . '/pir-expo-2026-2.jpg', $_SERVER['DOCUMENT_ROOT'] . $inline) || exit("не скопировалось: $inline\n");
}

$lead = '27 октября в рамках деловой программы ПИР ЭКСПО 2026 состоялось выступление Виталия Петрикова, '
    . 'менеджера проектов группы компаний «ПРОФЭКВИП», на тему «Своя прачечная или аутсорс: считаем, сравниваем, выбираем».';

$text = <<<HTML
<p>27 октября в рамках деловой программы <strong>ПИР ЭКСПО 2026</strong> состоялось выступление Виталия Петрикова, менеджера проектов группы компаний «ПРОФЭКВИП», на тему «Своя прачечная или аутсорс: считаем, сравниваем, выбираем».</p>
<p>Организация прачечных процессов — важная составляющая эффективной работы гостиницы. От выбранной модели зависит не только уровень операционных расходов, но и стабильность обслуживания, качество обработки текстиля и возможность контролировать производственные процессы.</p>
<p>В рамках выступления была рассмотрена одна из актуальных задач гостиничного бизнеса: <strong>что выгоднее — организовать собственную прачечную или передать обработку белья специализированному подрядчику?</strong></p>
<p>Особое внимание было уделено экономическим аспектам выбора, особенностям организации прачечных процессов и факторам, которые необходимо учитывать при оценке каждого решения.</p>
<p><img src="{$inline}" alt="Виталий Петриков, ПРОФЭКВИП, выступает на ПИР ЭКСПО 2026" width="1080" height="810" style="max-width:100%;height:auto;"></p>
<p><strong>Ключевой принцип — индивидуальный подход к каждому объекту.</strong> Оптимальная модель организации прачечной зависит от масштабов гостиницы, объёмов обрабатываемого текстиля, доступных ресурсов и эксплуатационных требований.</p>
<p>Участие в деловой программе ПИР ЭКСПО стало для ПРОФЭКВИП возможностью поделиться профессиональной экспертизой в области прачечного оснащения и подчеркнуть значимость комплексного подхода к организации гостиничной инфраструктуры.</p>
<p>Благодарим организаторов мероприятия и всех участников за интерес к выступлению!</p>
HTML;

$e = \CIBlockElement::GetList([], ['IBLOCK_ID' => $ib, '=CODE' => $code, 'CHECK_PERMISSIONS' => 'N'], false, ['nTopCount' => 1], ['ID'])->Fetch();
if ($e) { echo "$code: уже есть #{$e['ID']}\n"; exit(0); }
if ($dry) { echo "$code: будет создана (инфоблок $ib)\n"; exit(0); }

$cover = __DIR__ . '/pir-expo-2026-1.jpg';
$coverAlt = 'Выступление ПРОФЭКВИП на ПИР ЭКСПО 2026: своя прачечная или аутсорс';
$be = new \CIBlockElement();
$id = $be->Add([
    'IBLOCK_ID' => $ib, 'CODE' => $code, 'ACTIVE' => 'Y', 'SORT' => 500,
    'NAME' => 'ПРОФЭКВИП на ПИР ЭКСПО 2026: собственная прачечная или аутсорсинг?',
    'ACTIVE_FROM' => $activeFrom,
    'PREVIEW_TEXT_TYPE' => 'text', 'PREVIEW_TEXT' => $lead,
    'DETAIL_TEXT_TYPE' => 'html', 'DETAIL_TEXT' => $text,
    'PREVIEW_PICTURE' => \CFile::MakeFileArray($cover), 'DETAIL_PICTURE' => \CFile::MakeFileArray($cover),
    'IPROPERTY_TEMPLATES' => [
        'ELEMENT_META_TITLE' => 'ПРОФЭКВИП на ПИР ЭКСПО 2026: своя прачечная или аутсорсинг для гостиницы',
        'ELEMENT_META_DESCRIPTION' => 'Виталий Петриков (ПРОФЭКВИП) на ПИР ЭКСПО 2026 сравнил собственную прачечную '
            . 'и аутсорсинг для гостиниц: экономика выбора, организация процессов, индивидуальный подход.',
        'ELEMENT_PREVIEW_PICTURE_FILE_ALT' => $coverAlt,
        'ELEMENT_DETAIL_PICTURE_FILE_ALT' => $coverAlt,
    ],
]);
if (!$id) { echo "$code: ошибка {$be->LAST_ERROR}\n"; exit(1); }
echo "$code: создана #$id\n";
\CIBlock::clearIblockTagCache($ib);
BXClearCache(true);
