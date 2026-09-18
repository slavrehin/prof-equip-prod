<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

// Догрузка карточек "Проекты" (/portfolio/) по клику "Показать ещё" или при
// смене фильтра — см. news.list/projects/template.php (первая порция) и
// local/layout/src/widgets/projects-list/projects-list.js (кто это вызывает).
// Свой запрос к инфоблоку, а не переиспользование компонента bitrix:news.list —
// компонентное кэширование (CACHE_TYPE=A) тут не нужно и только мешало бы:
// resize_cache/webp_cache и так кэшируют самое дорогое (генерацию картинок).

header("Content-Type: application/json; charset=UTF-8");

$filter = isset($_GET["filter"]) ? (int)$_GET["filter"] : 0;
$offset = isset($_GET["offset"]) ? max(0, (int)$_GET["offset"]) : 0;
$batchSize = PROFEQUIP_PORTFOLIO_BATCH_SIZE;

$iblockId = GetIBlockIDByCode("projects");
if (!$iblockId) {
    echo json_encode(["html" => "", "count" => 0, "hasMore" => false]);
    return;
}

$elementFilter = ["IBLOCK_ID" => $iblockId, "ACTIVE" => "Y"];
if ($filter > 0) {
    $elementFilter["PROPERTY_TYPE"] = $filter;
}

// +1 сверх окна offset..offset+batchSize — чтобы понять, есть ли ещё
// элементы дальше, без отдельного COUNT-запроса.
$res = CIBlockElement::GetList(
    ["SORT" => "ASC"],
    $elementFilter,
    false,
    ["nTopCount" => $offset + $batchSize + 1],
    ["ID", "NAME", "DETAIL_PAGE_URL", "PREVIEW_PICTURE", "DETAIL_PICTURE"]
);

$html = "";
$count = 0;
$fetchedTotal = 0;

while ($ob = $res->GetNextElement()) {
    $fetchedTotal++;
    if ($fetchedTotal <= $offset) {
        continue;
    }
    if ($count >= $batchSize) {
        break;
    }

    $fields = $ob->GetFields();
    $pictureId = (int)($fields["PREVIEW_PICTURE"] ?: $fields["DETAIL_PICTURE"]);

    $cardHtml = profequip_RenderProjectCard([
        "NAME" => $fields["NAME"],
        "DETAIL_PAGE_URL" => $fields["DETAIL_PAGE_URL"],
        "PICTURE_ID" => $pictureId,
    ], $count, true);

    if ($cardHtml !== "") {
        $html .= $cardHtml;
        $count++;
    }
}

$hasMore = $fetchedTotal > $offset + $batchSize;

echo json_encode(["html" => $html, "count" => $count, "hasMore" => $hasMore]);
