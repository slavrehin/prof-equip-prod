<?php
/**
 * Импорт содержимого лендинга «Прачечная» из data.json (снят export.php на test3) — на прод.
 * Запускать ПОСЛЕ деплоя с миграциями 2026-09-18-landing-direction-structure.php и
 * 2026-09-18-landing-prachechnaya-seed.php: свойства должны уже существовать.
 *
 *   php local/deploy/landing_prachechnaya/import.php [--dry]
 *
 * Перезаписывает целиком: JSON-свойства LAND_*, привязки PROJECTS/BRANDS/LAND_MAT_*,
 * SEO-шаблоны элемента направления и CARD_* у привязанных проектов. Связи ищутся по CODE.
 * Картинки: файлы из files/ копируются в upload/ с тем же путём, на каждую заводится строка
 * b_file (EXTERNAL_ID = landing-sync-<md5 пути>) — при повторном запуске строка переиспользуется.
 * Картинки из upload/iblock не копируются (на проде они уже есть) — только своя строка b_file.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\CModule::IncludeModule('iblock');
global $DB;

const IMAGE_KEYS = ['pic', 'pic_mob', 'featured_pic'];
const FILE_OWNER = 'devuser';
const FILE_GROUP = 'www-data';

$dry = in_array('--dry', $argv, true);
$data = json_decode((string)file_get_contents(__DIR__ . '/data.json'), true);
if (!is_array($data) || empty($data['direction'])) {
    fwrite(STDERR, "data.json не прочитан\n");
    exit(1);
}
$warn = [];

$ibByCode = static function (string $code): int {
    static $cache = [];
    if (!isset($cache[$code])) {
        $cache[$code] = (int)(\CIBlock::GetList([], ['CODE' => $code, 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'] ?? 0);
    }
    return $cache[$code];
};
$elByCode = static function (int $ib, string $code): int {
    return $ib ? (int)(\CIBlockElement::GetList([], ['IBLOCK_ID' => $ib, 'CODE' => $code], false, ['nTopCount' => 1], ['ID'])->Fetch()['ID'] ?? 0) : 0;
};

$ibId = $ibByCode($data['direction']);
$elId = $elByCode($ibId, $data['direction']);
if (!$elId) {
    fwrite(STDERR, "Не найден элемент направления {$data['direction']}\n");
    exit(1);
}
foreach (array_keys($data['props']) as $code) {
    if (!$DB->Query("SELECT 1 FROM b_iblock_property WHERE IBLOCK_ID = $ibId AND CODE = '" . $DB->ForSql($code) . "'")->Fetch()) {
        fwrite(STDERR, "Нет свойства $code — сначала миграции лендинга\n");
        exit(1);
    }
}
echo ($dry ? "[DRY] " : "") . "Направление {$data['direction']}: инфоблок $ibId, элемент $elId\n";

// --- картинки: старый ID (test3) → новый ID (здесь)
$fileMap = [];
foreach ($data['files'] as $oldId => $f) {
    $rel = $f['SUBDIR'] . '/' . $f['FILE_NAME'];
    $abs = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . $rel;
    $ext = 'landing-sync-' . md5($rel);
    $row = $DB->Query("SELECT ID FROM b_file WHERE EXTERNAL_ID = '$ext' ORDER BY ID LIMIT 1")->Fetch();
    if ($row) {
        $fileMap[(int)$oldId] = (int)$row['ID'];
        echo "  файл $rel: уже заведён (ID={$row['ID']})\n";
        continue;
    }
    if ($f['bundled'] && !is_file($abs)) {
        $src = __DIR__ . '/files/' . $rel;
        if (!is_file($src)) {
            $warn[] = "нет файла в бандле: $rel";
            continue;
        }
        if (!$dry) {
            if (!is_dir(dirname($abs))) {
                mkdir(dirname($abs), 0775, true);
                @chown(dirname($abs), FILE_OWNER);
                @chgrp(dirname($abs), FILE_GROUP);
                @chown(dirname(dirname($abs)), FILE_OWNER);
                @chgrp(dirname(dirname($abs)), FILE_GROUP);
            }
            copy($src, $abs);
            @chown($abs, FILE_OWNER);
            @chgrp($abs, FILE_GROUP);
            @chmod($abs, 0664);
        }
        echo "  файл $rel: скопирован\n";
    } elseif (!is_file($abs)) {
        $warn[] = "нет файла на диске: upload/$rel (картинка останется пустой)";
        continue;
    }
    if ($dry) {
        $fileMap[(int)$oldId] = -1;
        continue;
    }
    $DB->Query(
        'INSERT INTO b_file (TIMESTAMP_X, MODULE_ID, HEIGHT, WIDTH, FILE_SIZE, CONTENT_TYPE, SUBDIR, FILE_NAME, ORIGINAL_NAME, DESCRIPTION, HANDLER_ID, EXTERNAL_ID) VALUES (NOW(), '
        . "'" . $DB->ForSql($f['MODULE_ID'] ?: 'iblock') . "', " . (int)$f['HEIGHT'] . ', ' . (int)$f['WIDTH'] . ', ' . (int)$f['FILE_SIZE'] . ', '
        . "'" . $DB->ForSql($f['CONTENT_TYPE']) . "', '" . $DB->ForSql($f['SUBDIR']) . "', '" . $DB->ForSql($f['FILE_NAME']) . "', "
        . "'" . $DB->ForSql((string)$f['ORIGINAL_NAME']) . "', '" . $DB->ForSql((string)$f['DESCRIPTION']) . "', NULL, '$ext')"
    );
    $fileMap[(int)$oldId] = (int)$DB->LastID();
    echo "  файл $rel: b_file ID={$fileMap[(int)$oldId]}\n";
}

// --- JSON-свойства лендинга
$remap = static function (array $node) use (&$remap, $fileMap): array {
    foreach ($node as $k => $v) {
        if (is_array($v)) {
            $node[$k] = $remap($v);
        } elseif (in_array($k, IMAGE_KEYS, true) && (int)$v > 0) {
            $node[$k] = max(0, $fileMap[(int)$v] ?? 0);
        }
    }
    return $node;
};
foreach ($data['props'] as $code => $value) {
    $json = json_encode($remap($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!$dry) {
        \CIBlockElement::SetPropertyValuesEx($elId, $ibId, [$code => $json]);
        $saved = (string)(\CIBlockElement::GetProperty($ibId, $elId, [], ['CODE' => $code])->Fetch()['VALUE'] ?? '');
        if ($saved === '' && $value) {
            $warn[] = "$code: после записи пусто";
        }
    }
    echo "$code: " . count($value) . " записей\n";
}

// --- привязки по CODE
foreach ($data['links'] as $code => $items) {
    $ids = [];
    foreach ($items as $it) {
        $id = $elByCode($ibByCode($it['iblock']), $it['code']);
        if ($id) {
            $ids[] = $id;
        } else {
            $warn[] = "$code: не найден {$it['iblock']}/{$it['code']}";
        }
    }
    if (!$dry) {
        \CIBlockElement::SetPropertyValuesEx($elId, $ibId, [$code => $ids ?: false]);
    }
    echo "$code: " . count($ids) . " из " . count($items) . "\n";
}

// --- карточки проектов
$ibProjects = $ibByCode('projects');
foreach ($data['cards'] as $pCode => $props) {
    $pId = $elByCode($ibProjects, $pCode);
    if (!$pId) {
        $warn[] = "карточка: нет проекта $pCode";
        continue;
    }
    if (!$dry) {
        $set = [];
        foreach ($props as $code => $vals) {
            $set[$code] = $vals ?: false;
        }
        \CIBlockElement::SetPropertyValuesEx($pId, $ibProjects, $set);
    }
    echo "карточка $pCode (ID=$pId)\n";
}

// --- SEO-шаблоны элемента
if ($data['seo']) {
    $tpl = new \Bitrix\Iblock\InheritedProperty\ElementTemplates($ibId, $elId);
    $keep = [];
    foreach ($tpl->findTemplates() as $code => $row) {
        if (($row['INHERITED'] ?? 'N') === 'N' && $row['TEMPLATE'] !== '') {
            $keep[$code] = $row['TEMPLATE'];
        }
    }
    if (!$dry) {
        $tpl->set($data['seo'] + $keep);
        (new \Bitrix\Iblock\InheritedProperty\ElementValues($ibId, $elId))->clearValues();
    }
    echo "SEO: " . implode(', ', array_keys($data['seo'])) . "\n";
}

if (!$dry) {
    BXClearCache(true, '/profequip/direction_landing/');
    \CIBlock::clearIblockTagCache($ibId);
    \CIBlock::clearIblockTagCache($ibProjects);
}

if ($warn) {
    echo "\nПредупреждения:\n  - " . implode("\n  - ", $warn) . "\n";
}
echo $dry ? "DRY: ничего не записано\n" : "Готово\n";
