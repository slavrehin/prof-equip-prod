<?php
/**
 * Блок над листингом раздела / SEO-тега:
 *   1) плитки подкатегорий с картинкой и числом товаров (на конечном разделе — соседние разделы,
 *      текущий отмечен) — замена списка подразделов в сайдбаре;
 *   2) «Популярные подборки» — SEO-теги раздела (local/php_interface/include/seo_tags.php) + витрины
 *      («В наличии»). Кликабельные ссылки над товарами прокачивают посадочные страницы тегов.
 * Подключается из ../section.php (переменные $arResult, $seoTag, $APPLICATION).
 * Стили: local/templates/profequip/assets/css/catalog-nav.css.
 */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
if (empty($arResult['CURRENT_SECTION_DATA']['ID'])) {
    return;
}

$navSection = $arResult['CURRENT_SECTION_DATA'];
$navIblockId = (int)$arParams['IBLOCK_ID'];

$navData = (static function (int $iblockId, int $sectionId): array {
    $cache = \Bitrix\Main\Data\Cache::createInstance();
    if ($cache->initCache(86400, 'catalog_top_v3_' . $sectionId, '/profequip/catalog_top')) {
        return $cache->getVars();
    }
    $cache->startDataCache();
    $GLOBALS['CACHE_MANAGER']->StartTagCache('/profequip/catalog_top');
    $GLOBALS['CACHE_MANAGER']->RegisterTag('iblock_id_' . $iblockId);

    $current = \CIBlockSection::GetList([], ['ID' => $sectionId], false, ['ID', 'IBLOCK_SECTION_ID', 'DEPTH_LEVEL'])->Fetch();
    $showcase = array_flip(\CatalogStandard::SHOWCASE_SECTIONS);

    $load = static function (int $parentId) use ($iblockId, $showcase): array {
        $out = [];
        $rs = \CIBlockSection::GetList(['SORT' => 'ASC', 'NAME' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'SECTION_ID' => $parentId, 'ACTIVE' => 'Y', 'GLOBAL_ACTIVE' => 'Y', 'CNT_ACTIVE' => 'Y', 'ELEMENT_SUBSECTIONS' => 'Y'],
            true, ['ID', 'CODE', 'NAME', 'PICTURE', 'SECTION_PAGE_URL', 'LEFT_MARGIN', 'RIGHT_MARGIN']);
        while ($s = $rs->GetNext()) {
            if ((int)$s['ELEMENT_CNT'] === 0) {
                continue;
            }
            $pictureId = (int)$s['PICTURE'];
            if (!$pictureId) {
                // нет картинки у раздела — фото первого товара раздела
                $el = \CIBlockElement::GetList(['SORT' => 'ASC', 'ID' => 'ASC'],
                    ['IBLOCK_ID' => $iblockId, 'SECTION_ID' => (int)$s['ID'], 'INCLUDE_SUBSECTIONS' => 'Y', 'ACTIVE' => 'Y', '!PREVIEW_PICTURE' => false],
                    false, ['nTopCount' => 1], ['ID', 'PREVIEW_PICTURE'])->Fetch();
                $pictureId = (int)($el['PREVIEW_PICTURE'] ?? 0);
            }
            $src = '';
            if ($pictureId) {
                $resized = \CFile::ResizeImageGet($pictureId, ['width' => 320, 'height' => 320], BX_RESIZE_IMAGE_PROPORTIONAL, false);
                $src = $resized['src'] ?? '';
                if ($src === '' || !is_file($_SERVER['DOCUMENT_ROOT'] . $src)) {
                    $src = (string)\CFile::GetPath($pictureId);
                }
            }
            $out[] = [
                'ID' => (int)$s['ID'], 'CODE' => $s['CODE'], 'NAME' => $s['~NAME'], 'URL' => $s['SECTION_PAGE_URL'],
                'COUNT' => (int)$s['ELEMENT_CNT'], 'PICTURE' => $src, 'SHOWCASE' => isset($showcase[$s['CODE']]),
            ];
        }
        return $out;
    };

    $children = $load($sectionId);
    $mode = 'children';
    if (!array_filter($children, static fn($c) => !$c['SHOWCASE']) && (int)$current['IBLOCK_SECTION_ID'] > 0) {
        $children = $load((int)$current['IBLOCK_SECTION_ID']);
        $mode = 'siblings';
    }

    $GLOBALS['CACHE_MANAGER']->EndTagCache();
    $result = ['MODE' => $mode, 'ITEMS' => $children];
    $cache->endDataCache($result);
    return $result;
})($navIblockId, (int)$navSection['ID']);

$navTiles = array_values(array_filter($navData['ITEMS'], static fn($i) => !$i['SHOWCASE']));
$navShowcases = array_values(array_filter($navData['ITEMS'], static fn($i) => $i['SHOWCASE'] && $navData['MODE'] === 'children'));
$navTags = class_exists('SeoTags') ? SeoTags::forSection((string)$navSection['CODE']) : [];
// Первые чипсы — по одному из каждой группы по очереди (назначение, загрузка, бренд, нагрев…), чтобы
// в видимой строке были разные сценарии выбора, а не десять брендов подряд.
$navByGroup = [];
foreach ($navTags as $t) {
    $navByGroup[$t['GROUP']][] = $t;
}
$navTags = [];
while ($navByGroup) {
    foreach ($navByGroup as $g => &$list) {
        $navTags[] = array_shift($list);
        if (!$list) {
            unset($navByGroup[$g]);
        }
    }
    unset($list);
}
$navCurrentTagId = !empty($seoTag) ? (int)$seoTag['ID'] : 0;
$navVisibleChips = 10;
?>
<?if ($navTiles):?>
<nav class="cnav-tiles<?= $navData['MODE'] === 'siblings' ? ' cnav-tiles--compact' : '' ?>" aria-label="Разделы каталога">
    <ul class="cnav-tiles__list<?= count($navTiles) <= 3 ? ' cnav-tiles__list--few' : (count($navTiles) > 8 ? ' cnav-tiles__list--many' : '') ?>" style="--cnav-cols: <?= \CatalogNav::columns(count($navTiles), 4, 6) ?>; --cnav-cols-md: <?= \CatalogNav::columns(count($navTiles), 2, 4) ?>;">
        <?foreach ($navTiles as $tile):
            $isCurrent = $navData['MODE'] === 'siblings' && $tile['ID'] === (int)$navSection['ID'];?>
            <li class="cnav-tiles__item">
                <a class="cnav-tile<?= $isCurrent ? ' is-current' : '' ?>" href="<?= htmlspecialcharsbx($tile['URL']) ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?>>
                    <span class="cnav-tile__img">
                        <?if ($tile['PICTURE']):?>
                            <img src="<?= htmlspecialcharsbx($tile['PICTURE']) ?>" alt="<?= htmlspecialcharsbx($tile['NAME']) ?>" loading="lazy" width="160" height="160">
                        <?endif;?>
                    </span>
                    <span class="cnav-tile__text">
                        <span class="cnav-tile__name"><?= htmlspecialcharsbx($tile['NAME']) ?></span>
                        <span class="cnav-tile__count"><?= (int)$tile['COUNT'] ?> <?= \CatalogNav::plural((int)$tile['COUNT']) ?></span>
                    </span>
                </a>
            </li>
        <?endforeach;?>
    </ul>
</nav>
<?endif;?>
<?
// «Популярные подборки» над каталогом временно скрыты (решение 2026-10-16). Страницы тегов и ссылки
// на них из фильтра работают; вернуть блок — $navShowChips = true.
$navShowChips = false;
?>
<?if ($navShowChips && ($navTags || $navShowcases || $navCurrentTagId)):?>
<nav class="cnav-chips" aria-label="Популярные подборки">
    <span class="cnav-chips__title">Популярные подборки</span>
    <ul class="cnav-chips__list js-cnav-chips" data-visible="<?= $navVisibleChips ?>">
        <?if ($navCurrentTagId):?>
            <li><a class="cnav-chip cnav-chip--reset" href="/product-category/<?= htmlspecialcharsbx($navSection['CODE']) ?>/" title="Показать все товары раздела">Все <?= htmlspecialcharsbx(mb_strtolower($navSection['NAME'])) ?></a></li>
        <?endif;?>
        <?foreach ($navShowcases as $showcase):?>
            <li><a class="cnav-chip cnav-chip--accent" href="<?= htmlspecialcharsbx($showcase['URL']) ?>">В наличии<span class="cnav-chip__count"><?= (int)$showcase['COUNT'] ?></span></a></li>
        <?endforeach;?>
        <?$chipIndex = 0;
        foreach ($navTags as $tag):
            $chipIndex++;
            $isActive = $tag['ID'] === $navCurrentTagId;?>
            <li<?= $chipIndex > $navVisibleChips && !$isActive ? ' class="is-extra"' : '' ?>>
                <a class="cnav-chip<?= $isActive ? ' is-active' : '' ?>" href="<?= htmlspecialcharsbx(SeoTags::url($tag)) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>
                   data-group="<?= htmlspecialcharsbx($tag['GROUP']) ?>"><?= htmlspecialcharsbx($tag['NAME']) ?><?if ($tag['COUNT'] !== null):?><span class="cnav-chip__count"><?= (int)$tag['COUNT'] ?></span><?endif;?></a>
            </li>
        <?endforeach;?>
        <?if ($chipIndex > $navVisibleChips):?>
            <li class="cnav-chips__more-item"><button type="button" class="cnav-chip cnav-chip--more js-cnav-more" aria-expanded="false">Ещё <?= $chipIndex - $navVisibleChips ?></button></li>
        <?endif;?>
    </ul>
</nav>
<?endif;?>
<script>
(function () { // мобильная лента соседних разделов — сразу показать текущий
    var cur = document.querySelector('.cnav-tiles--compact .cnav-tile.is-current');
    if (cur && cur.parentNode && cur.closest('.cnav-tiles__list').scrollWidth > cur.closest('.cnav-tiles__list').clientWidth) {
        var list = cur.closest('.cnav-tiles__list');
        list.scrollLeft = cur.parentNode.offsetLeft - list.offsetLeft;
    }
})();
// Вид листинга (плитка/строки): после AJAX-фильтра список товаров заменяется новым элементом,
// и обработчик main.js, привязанный к старому, переставал работать — держим вид здесь.
(function () {
    var current = null;
    function apply() {
        if (!current) { return; }
        document.querySelectorAll('.catalog__list').forEach(function (l) { l.classList.toggle('lines', current === 'lines'); });
        document.querySelectorAll('.catalog-view__btn').forEach(function (b) { b.classList.toggle('active', b.dataset.view === current); });
    }
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('.catalog-view__btn');
        if (b && b.dataset.view) { current = b.dataset.view; setTimeout(apply, 0); }
    });
    var box = document.querySelector('.js-product-container');
    if (box && window.MutationObserver) { new MutationObserver(apply).observe(box, { childList: true }); }
})();
document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('.js-cnav-more');
    if (!btn) { return; }
    var list = btn.closest('.js-cnav-chips');
    if (list) { list.classList.add('is-expanded'); btn.setAttribute('aria-expanded', 'true'); }
});
</script>
