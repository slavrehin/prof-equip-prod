<?php
/**
 * SEO-блок под листингом раздела / SEO-тега (по мотивам profi-project.ru/catalog/mebel/):
 *   1) цифры из каталога (моделей, производителей, загрузка от–до) — на теге считаются по его товарам;
 *   2) «Подборки» — подразделы глубже плиток над листингом (по родителю) + индексируемые SEO-теги раздела по
 *      группам (local/php_interface/include/seo_tags.php); noindex-теги не ссылаем, прямых потомков не дублируем;
 *   3) SEO-текст — DESCRIPTION раздела или SEO_TEXT тега (подставлен в section.php);
 *   4) FAQ — вопросы раздела (поле UF_FAQ → инфоблок faq, сид local/deploy/catalog_seo/seed_faq.php)
 *      + 2 вопроса из данных (производители, загрузка). Только на странице самого раздела: на тегах
 *      те же вопросы стали бы дублем FAQPage на десятках страниц;
 *   5) форма «Спроектируем решение для вас» — та же, что на лендинге «Прачечная» (SIMPLE_FORM_4).
 * Есть FAQ — FAQ слева, 1+2 справа узкой колонкой, под ними 5 и 3 на всю ширину; нет — 1+2, 3, 5 подряд.
 * Подключается из ../section.php после листинга. Стили: local/templates/profequip/assets/css/catalog-seo.css.
 */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
if (empty($arResult['CURRENT_SECTION_DATA']['ID'])) {
    return;
}

$seoSection = $arResult['CURRENT_SECTION_DATA'];
$seoIblockId = (int)$arParams['IBLOCK_ID'];
$seoCurrentTag = !empty($seoTag) ? $seoTag : null;
$seoIsFilter = !$seoCurrentTag && trim((string)($_REQUEST['SMART_FILTER_PATH'] ?? '')) !== '';

// ---------- цифры: товары, производители, загрузка ----------
$seoFacts = (static function (int $iblockId, int $sectionId, ?array $tag): array {
    $cache = \Bitrix\Main\Data\Cache::createInstance();
    $key = 'catalog_seo_facts_v1_' . $sectionId . '_' . ($tag ? (int)$tag['ID'] : 0);
    if ($cache->initCache(86400, $key, '/profequip/catalog_seo')) {
        return $cache->getVars();
    }
    $cache->startDataCache();
    $GLOBALS['CACHE_MANAGER']->StartTagCache('/profequip/catalog_seo');
    $GLOBALS['CACHE_MANAGER']->RegisterTag('iblock_id_' . $iblockId);

    $filter = ['IBLOCK_ID' => $iblockId, 'SECTION_ID' => $sectionId, 'INCLUDE_SUBSECTIONS' => 'Y', 'ACTIVE' => 'Y'];
    if ($tag) {
        $filter['ID'] = SeoTags::productIds($tag) ?: [0];
    }
    $ids = [];
    $brands = [];
    $loads = [];
    $rs = \CIBlockElement::GetList([], $filter, false, false, ['ID', 'PROPERTY_PROIZVODITEL', 'PROPERTY_ZAGRUZKA_KG']);
    while ($e = $rs->Fetch()) {
        $id = (int)$e['ID'];
        $brand = trim((string)$e['PROPERTY_PROIZVODITEL_VALUE']);
        if (!isset($ids[$id]) && $brand !== '') {
            $brands[$brand] = ($brands[$brand] ?? 0) + 1;
        }
        $ids[$id] = true;
        if ((float)$e['PROPERTY_ZAGRUZKA_KG_VALUE'] > 0) {
            $loads[] = (float)$e['PROPERTY_ZAGRUZKA_KG_VALUE'];
        }
    }
    arsort($brands);

    $GLOBALS['CACHE_MANAGER']->EndTagCache();
    $result = [
        'COUNT' => count($ids),
        'BRANDS' => $brands,
        'LOAD_MIN' => $loads ? min($loads) : null,
        'LOAD_MAX' => $loads ? max($loads) : null,
    ];
    $cache->endDataCache($result);
    return $result;
})($seoIblockId, (int)$seoSection['ID'], $seoCurrentTag);

$seoNum = static fn(float $n): string => rtrim(rtrim(number_format($n, 1, ',', ' '), '0'), ',');

// ---------- подборки: индексируемые теги раздела по группам ----------
// подписи групп — как строки характеристик на лендингах (бирюзовым капсом); у загрузки единица в подписи,
// а в ссылках только числа («8 10 15 … от 140»)
$seoGroupTitles = ['Тип' => 'Тип', 'Назначение' => 'Назначение', 'Производитель' => 'Производитель',
    'Загрузка' => 'Загрузка, кг', 'Тип нагрева' => 'Нагрев', 'Размер' => 'Размер', 'Особенности' => 'Особенности', 'Страна' => 'Страна'];
$seoTagLabel = static fn(array $t): string => $t['GROUP'] === 'Загрузка' ? preg_replace('/\s*кг$/u', '', $t['NAME']) : $t['NAME'];
$seoMaxVisible = 10;  // в группе больше — показываем 6 самых наполненных, остальные под «ещё N»
$seoTagGroups = [];
if (class_exists('SeoTags')) {
    foreach (SeoTags::forSection((string)$seoSection['CODE'], false) as $t) {
        if (SeoTags::isIndexable($t)) {
            $seoTagGroups[$t['GROUP']][] = $t;
        }
    }
    uksort($seoTagGroups, static fn($a, $b) => (SeoTags::GROUP_ORDER[$a] ?? 90) <=> (SeoTags::GROUP_ORDER[$b] ?? 90));
    foreach ($seoTagGroups as $g => &$list) {
        if ($g === 'Загрузка') {  // «8 кг … от 140 кг» — по числу, а не по SORT
            usort($list, static fn($a, $b) => (float)preg_replace('/[^\d.]/', '', $a['NAME']) <=> (float)preg_replace('/[^\d.]/', '', $b['NAME']));
        }
        $visible = array_keys($list);
        if (count($list) > $seoMaxVisible && $g !== 'Загрузка') {  // числа короткие — загрузку показываем всю
            $byCount = $list;
            uasort($byCount, static fn($a, $b) => (int)$b['COUNT'] <=> (int)$a['COUNT']);
            $visible = array_slice(array_keys($byCount), 0, 6);
        }
        foreach ($list as $i => &$t) {
            $t['EXTRA'] = !in_array($i, $visible, true) && (!$seoCurrentTag || $t['ID'] !== (int)$seoCurrentTag['ID']);
        }
        unset($t);
    }
    unset($list);
}

// ---------- категории без прямого перехода со страницы: подразделы глубже плиток над листингом ----------
// Плитки (include/catalog_top.php) ведут только на прямых потомков; внуки (на корне прачечного — Гладильные
// прессы, Центрифуги, Машины химчистки…) иначе достижимы только через промежуточный раздел. Группируем по
// родителю (строка «Сушильное оборудование» → его подразделы); у раздела без внуков группы нет.
$seoCatGroups = (static function (int $iblockId, int $sectionId): array {
    $cache = \Bitrix\Main\Data\Cache::createInstance();
    if ($cache->initCache(86400, 'catalog_seo_cats_v1_' . $sectionId, '/profequip/catalog_seo')) {
        return $cache->getVars();
    }
    $cache->startDataCache();
    $GLOBALS['CACHE_MANAGER']->StartTagCache('/profequip/catalog_seo');
    $GLOBALS['CACHE_MANAGER']->RegisterTag('iblock_id_' . $iblockId);

    $current = \CIBlockSection::GetList([], ['ID' => $sectionId], false, ['ID', 'LEFT_MARGIN', 'RIGHT_MARGIN', 'DEPTH_LEVEL'])->Fetch();
    $showcase = array_flip(\CatalogStandard::SHOWCASE_SECTIONS);
    $parents = [];
    $groups = [];
    $rs = \CIBlockSection::GetList(['LEFT_MARGIN' => 'ASC'],
        ['IBLOCK_ID' => $iblockId, '>LEFT_MARGIN' => (int)$current['LEFT_MARGIN'], '<RIGHT_MARGIN' => (int)$current['RIGHT_MARGIN'],
         'ACTIVE' => 'Y', 'GLOBAL_ACTIVE' => 'Y', 'CNT_ACTIVE' => 'Y', 'ELEMENT_SUBSECTIONS' => 'Y'],
        true, ['ID', 'CODE', 'NAME', 'DEPTH_LEVEL', 'IBLOCK_SECTION_ID', 'SECTION_PAGE_URL']);
    while ($s = $rs->GetNext()) {
        if ((int)$s['ELEMENT_CNT'] === 0 || isset($showcase[$s['CODE']])) {
            continue;
        }
        $level = (int)$s['DEPTH_LEVEL'] - (int)$current['DEPTH_LEVEL'];
        if ($level === 1) {
            $parents[(int)$s['ID']] = $s['~NAME'];
        } elseif ($level === 2 && isset($parents[(int)$s['IBLOCK_SECTION_ID']])) {
            $groups[$parents[(int)$s['IBLOCK_SECTION_ID']]][] = ['NAME' => $s['~NAME'], 'URL' => $s['SECTION_PAGE_URL'], 'COUNT' => (int)$s['ELEMENT_CNT']];
        }
    }

    $GLOBALS['CACHE_MANAGER']->EndTagCache();
    $cache->endDataCache($groups);
    return $groups;
})($seoIblockId, (int)$seoSection['ID']);
$seoCatCount = array_sum(array_map('count', $seoCatGroups));

// Форма «Спроектируем решение для вас» — как на лендинге направления (веб-форма SIMPLE_FORM_4, шаблон
// ответа form.result.new/landing). Отправляет общий js/dev.js по классу .fb-form (капча, fetch, URL
// страницы в .js-url); маска телефона, проверка полей и «Заявка отправлена» — скрипт внизу файла.
$seoForm = function_exists('profequip_LandingFormFields') ? profequip_LandingFormFields() : ['form_id' => 0, 'names' => []];
$seoFormSource = 'Каталог «' . $seoSection['NAME'] . '» · консультация';
$seoRoot = \CIBlockSection::GetNavChain($seoIblockId, (int)$seoSection['ID'], ['CODE'])->Fetch();
$seoFormLead = ($seoRoot['CODE'] ?? '') === 'prachechnoe-oborudovanie'
    ? 'Расскажите о задаче — наша команда разработает концепцию прачечной и подберёт оптимальные варианты оборудования.'
    : 'Расскажите о задаче — наша команда разработает решение для вашего объекта и подберёт оптимальные варианты оборудования.';

// ---------- FAQ ----------
$seoFaq = [];
if (!$seoCurrentTag && !$seoIsFilter) {
    if (!empty($seoSection['FAQ'])) {
        $rs = \CIBlockElement::GetList(['SORT' => 'ASC', 'ID' => 'ASC'],
            ['IBLOCK_ID' => (int)GetIBlockIDByCode('faq'), 'ID' => (array)$seoSection['FAQ'], 'ACTIVE' => 'Y'],
            false, false, ['ID', 'NAME', 'DETAIL_TEXT', 'PREVIEW_TEXT']);
        while ($e = $rs->Fetch()) {
            $answer = trim((string)$e['DETAIL_TEXT']) !== '' ? $e['DETAIL_TEXT'] : $e['PREVIEW_TEXT'];
            $seoFaq[] = ['Q' => $e['NAME'], 'A' => trim((string)$answer)];
        }
    }

    $seoTagByName = static function (string $group, string $name) use ($seoTagGroups): ?array {
        foreach ($seoTagGroups[$group] ?? [] as $t) {
            if (mb_strtolower($t['NAME']) === mb_strtolower($name)) {
                return $t;
            }
        }
        return null;
    };
    // авто-вопросы — только в дополнение к ручным: иначе одинаковый FAQ из 1–2 пунктов на всех разделах
    if ($seoFaq && count($seoFacts['BRANDS']) > 1) {
        $parts = [];
        foreach ($seoFacts['BRANDS'] as $brand => $cnt) {
            $t = $seoTagByName('Производитель', $brand);
            $label = htmlspecialcharsbx($brand) . ' (' . $cnt . ')';
            $parts[] = $t ? '<a href="' . htmlspecialcharsbx(SeoTags::url($t)) . '">' . $label . '</a>' : $label;
        }
        $seoFaq[] = [
            'Q' => 'Какие производители представлены в разделе «' . $seoSection['NAME'] . '»?',
            'A' => '<p>Сейчас в каталоге ' . $seoFacts['COUNT'] . ' ' . CatalogNav::plural($seoFacts['COUNT'], ['модель', 'модели', 'моделей'])
                . ' от ' . count($seoFacts['BRANDS']) . ' ' . CatalogNav::plural(count($seoFacts['BRANDS']), ['производителя', 'производителей', 'производителей'])
                . ': ' . implode(', ', $parts) . '. Если нужной модели нет на сайте, менеджер проверит её у поставщика.</p>',
        ];
    }
    if ($seoFaq && !empty($seoTagGroups['Загрузка']) && $seoFacts['LOAD_MIN'] !== null) {
        $popular = $seoTagGroups['Загрузка'];
        usort($popular, static fn($a, $b) => (int)$b['COUNT'] <=> (int)$a['COUNT']);
        $popular = array_slice($popular, 0, 4);
        usort($popular, static fn($a, $b) => (float)preg_replace('/[^\d.]/', '', $a['NAME']) <=> (float)preg_replace('/[^\d.]/', '', $b['NAME']));
        $links = array_map(static fn($t) => '<a href="' . htmlspecialcharsbx(SeoTags::url($t)) . '">' . htmlspecialcharsbx($t['NAME']) . '</a> ('
            . (int)$t['COUNT'] . ' ' . CatalogNav::plural((int)$t['COUNT'], ['модель', 'модели', 'моделей']) . ')', $popular);
        $seoFaq[] = [
            'Q' => 'Какая загрузка бывает у моделей в каталоге?',
            'A' => '<p>От ' . $seoNum($seoFacts['LOAD_MIN']) . ' до ' . $seoNum($seoFacts['LOAD_MAX']) . ' кг за цикл. Больше всего моделей с загрузкой '
                . implode(', ', $links) . '. Остальные значения — в фильтре «Загрузка, кг» слева от каталога.</p>',
        ];
    }
}

$seoHasNav = $seoTagGroups || $seoCatGroups;
$seoDescription = trim((string)$seoSection['DESCRIPTION']);
if (!$seoHasNav && $seoDescription === '' && !$seoFaq) {
    return;
}
// есть FAQ — вопросы слева, подборки справа узкой колонкой, форма под ними на всю ширину
$seoSplit = (bool)$seoFaq;
ob_start();
?>
    <?if ($seoHasNav):?>
    <nav class="cseo-nav<?= $seoSplit ? ' cseo-nav--aside' : '' ?>" aria-label="Подборки">
        <h2 class="cseo-nav__title">Подборки</h2>
        <?if ($seoFacts['COUNT'] > 0):?>
        <dl class="cseo-stats">
            <div><dt><?= CatalogNav::plural($seoFacts['COUNT'], ['модель', 'модели', 'моделей']) ?></dt><dd><?= $seoFacts['COUNT'] ?></dd></div>
            <?if (count($seoFacts['BRANDS']) > 1):?>
                <div><dt><?= CatalogNav::plural(count($seoFacts['BRANDS']), ['бренд', 'бренда', 'брендов']) ?></dt><dd><?= count($seoFacts['BRANDS']) ?></dd></div>
            <?endif;?>
            <?if ($seoFacts['LOAD_MIN'] === null || empty($seoTagGroups['Загрузка'])):  // на корне загрузка есть лишь у части товаров
                if ($seoCatCount):?>
                <div><dt><?= CatalogNav::plural($seoCatCount, ['категория', 'категории', 'категорий']) ?></dt><dd><?= $seoCatCount ?></dd></div>
            <?endif; else:?>
                <div><dt>загрузка, кг</dt><dd><?= $seoFacts['LOAD_MIN'] == $seoFacts['LOAD_MAX'] ? $seoNum($seoFacts['LOAD_MIN']) : $seoNum($seoFacts['LOAD_MIN']) . '–' . $seoNum($seoFacts['LOAD_MAX']) ?></dd></div>
            <?endif;?>
        </dl>
        <?endif;?>
        <div class="cseo-rows">
            <?foreach ($seoCatGroups as $parentName => $cats):?>
            <div class="cseo-row cseo-row--cats">
                <p class="cseo-row__label"><?= htmlspecialcharsbx($parentName) ?></p>
                <ul class="cseo-row__links">
                    <?foreach ($cats as $cat):?>
                        <li><a href="<?= htmlspecialcharsbx($cat['URL']) ?>"><?= htmlspecialcharsbx($cat['NAME']) ?></a></li>
                    <?endforeach;?>
                </ul>
            </div>
            <?endforeach;?>
            <?foreach ($seoTagGroups as $group => $list):
                $extra = count(array_filter($list, static fn($t) => $t['EXTRA']));?>
            <div class="cseo-row">
                <p class="cseo-row__label"><?= htmlspecialcharsbx($seoGroupTitles[$group] ?? $group) ?></p>
                <ul class="cseo-row__links js-cseo-chips<?= $group === 'Загрузка' ? ' cseo-row__links--num' : '' ?>">
                    <?foreach ($list as $t):
                        $isActive = $seoCurrentTag && $t['ID'] === (int)$seoCurrentTag['ID'];?>
                        <li<?= $t['EXTRA'] ? ' class="is-extra"' : '' ?>>
                            <?if ($isActive):?>
                                <span class="is-active" aria-current="page"><?= htmlspecialcharsbx($seoTagLabel($t)) ?></span>
                            <?else:?>
                                <a href="<?= htmlspecialcharsbx(SeoTags::url($t)) ?>"<?= $group === 'Загрузка' ? ' title="' . htmlspecialcharsbx($t['NAME']) . '"' : '' ?>><?= htmlspecialcharsbx($seoTagLabel($t)) ?></a>
                            <?endif;?>
                        </li>
                    <?endforeach;?>
                    <?if ($extra):?>
                        <li class="cseo-row__more"><button type="button" class="js-cseo-more" aria-expanded="false">ещё <?= $extra ?></button></li>
                    <?endif;?>
                </ul>
            </div>
            <?endforeach;?>
        </div>
        <?if ($seoCurrentTag):?>
            <a class="cseo-nav__all" href="/product-category/<?= htmlspecialcharsbx($seoSection['CODE']) ?>/">Все <?= htmlspecialcharsbx(mb_strtolower($seoSection['NAME'])) ?></a>
        <?endif;?>
    </nav>
    <?endif;?>
<?
$seoNavHtml = ob_get_clean();
ob_start();
?>
    <?if ($seoForm['form_id']):
        $fn = static fn(string $sid): string => htmlspecialcharsbx($seoForm['names'][$sid] ?? '');?>
    <div class="cseo-cta" id="consult">
        <div class="cseo-cta__head">
            <p class="cseo-cta__title">Спроектируем решение для вас</p>
            <p class="cseo-cta__text"><?= htmlspecialcharsbx($seoFormLead) ?></p>
        </div>
        <form class="cseo-form fb-form" method="post" enctype="multipart/form-data" action="/local/ajax/form/?template_form=landing" data-source="<?= htmlspecialcharsbx($seoFormSource) ?>" novalidate>
            <div class="cseo-form__body">
                <?= bitrix_sessid_post() ?>
                <input type="hidden" name="WEB_FORM_ID" value="<?= (int)$seoForm['form_id'] ?>">
                <input type="hidden" name="web_form_submit" value="Отправить">
                <input type="hidden" name="<?= $fn('land_source') ?>" value="<?= htmlspecialcharsbx($seoFormSource) ?>">
                <input type="hidden" class="js-url" name="<?= $fn('land_url') ?>" value="">
                <?// Client ID Метрики и UTM — заполняет dev.js по классам js-client-id / js-utm_* ?>
                <input type="hidden" class="js-client-id" name="<?= $fn('land_client_id') ?>" value="">
                <?foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $utm):?>
                    <input type="hidden" class="js-<?= $utm ?>" name="<?= $fn('land_' . $utm) ?>" value="">
                <?endforeach;?>
                <div class="cseo-form__row">
                    <div class="cseo-field"><label for="cseo-name">Имя</label><input id="cseo-name" name="<?= $fn('land_name') ?>" autocomplete="name"></div>
                    <div class="cseo-field"><label for="cseo-phone">Телефон</label><input id="cseo-phone" name="<?= $fn('land_phone') ?>" type="tel" inputmode="tel" autocomplete="tel" data-kind="phone" required></div>
                    <button class="cseo-form__btn" type="submit">Получить консультацию</button>
                </div>
                <p class="cseo-form__consent">Нажимая кнопку, вы соглашаетесь с <a href="/politika-konfidentsialnosti/" target="_blank" rel="noopener">политикой обработки персональных данных</a></p>
            </div>
            <div class="cseo-form__ok" role="status" aria-live="polite">Заявка отправлена. Инженер направления свяжется с вами в рабочее время.</div>
        </form>
    </div>
    <?endif;?>
<?
$seoCtaHtml = ob_get_clean();
?>
<div class="catalog-seo">
    <?if (!$seoSplit):?>
        <?= $seoNavHtml ?>
    <?else:?>
    <div class="cseo-split<?= $seoCatGroups ? ' cseo-split--half' : '' ?>"><?// много категорий — колонки поровну, иначе подборки длиннее FAQ ?>
        <div class="cseo-split__main">
    <div class="cseo-faq" itemscope itemtype="https://schema.org/FAQPage">
        <h2 class="cseo-faq__title">Вопросы и ответы</h2>
        <div class="cseo-faq__list">
            <?foreach ($seoFaq as $i => $item):?>
            <details class="cseo-faq__item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question"<?= $i === 0 ? ' open' : '' ?>>
                <summary class="cseo-faq__q" itemprop="name"><?= htmlspecialcharsbx($item['Q']) ?></summary>
                <div class="cseo-faq__a" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                    <div itemprop="text"><?= $item['A'] ?></div>
                </div>
            </details>
            <?endforeach;?>
        </div>
    </div>
        </div>
        <aside class="cseo-split__aside">
            <?= $seoNavHtml ?>
        </aside>
    </div>
    <?= $seoCtaHtml ?>
    <?endif;?>

    <?if ($seoDescription !== ''):?>
    <div class="catalog__text">
        <?= $seoDescription ?>
    </div>
    <?endif;?>

    <?if (!$seoSplit):?>
        <?= $seoCtaHtml ?>
    <?endif;?>
</div>
<script>
document.addEventListener('click', function (e) { // «Ещё N» в группе подборок
    var b = e.target.closest && e.target.closest('.js-cseo-more');
    if (!b) { return; }
    b.closest('.js-cseo-chips').classList.add('is-expanded');
    b.setAttribute('aria-expanded', 'true');
});
(function () { // форма консультации: маска и проверка телефона/имени (как на лендингах), «отправлено»
    var form = document.querySelector('.cseo-form');
    if (!form) { return; }
    var tel = form.querySelector('input[data-kind="phone"]'), name = form.querySelector('input[autocomplete="name"]');
    var digits = function (v) { var d = v.replace(/\D/g, ''); if (d.length === 11 && /^[78]/.test(d)) { d = d.slice(1); } return d.slice(0, 10); };
    tel.addEventListener('input', function () {
        var d = digits(tel.value), o = d ? '+7 (' + d.slice(0, 3) : '';
        if (d.length > 3) { o += ') ' + d.slice(3, 6); }
        if (d.length > 6) { o += '-' + d.slice(6, 8); }
        if (d.length > 8) { o += '-' + d.slice(8, 10); }
        tel.value = o;
    });
    function check(input, ok, msg) {
        var box = input.closest('.cseo-field'), old = box.querySelector('.cseo-field__err');
        if (old) { old.remove(); }
        box.classList.toggle('is-error', !ok);
        if (!ok) { var e = document.createElement('span'); e.className = 'cseo-field__err'; e.textContent = msg; box.appendChild(e); }
        return ok;
    }
    var vTel = function () { return check(tel, digits(tel.value).length === 10, 'Введите номер телефона'); };
    var vName = function () { var v = name.value.trim(); return check(name, !v || /^[А-Яа-яA-Za-zёЁ \-]+$/.test(v), 'Введите имя'); };
    tel.addEventListener('blur', vTel);
    name.addEventListener('blur', vName);
    // capture на document — раньше обработчика dev.js на форме, чтобы не отправлять с ошибками
    document.addEventListener('submit', function (e) {
        if (e.target !== form) { return; }
        var okName = vName(), okTel = vTel();
        if (!okName || !okTel) { e.preventDefault(); e.stopPropagation(); (okName ? tel : name).focus(); }
    }, true);
    form.addEventListener('form:success', function () { form.classList.add('is-sent'); });
})();
</script>
