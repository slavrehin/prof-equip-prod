<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Лендинг направления — разметка по утверждённому макету (prachechnaya.html).
 * Все классы с префиксом dl-, чтобы не пересекаться со стилями сайта (main.css).
 * Стили: /local/templates/profequip/assets/css/direction-landing.css
 * Скрипты (vanilla, defer): /local/templates/profequip/assets/js/direction-landing.js
 *
 * @var array $arResult
 * @var CMain $APPLICATION
 */

use Bitrix\Main\Page\Asset;

$tplPath = SITE_TEMPLATE_PATH;
$docRoot = $_SERVER['DOCUMENT_ROOT'];
$ver = static fn(string $rel) => is_file($docRoot . $rel) ? filemtime($docRoot . $rel) : 1;

Asset::getInstance()->addCss($tplPath . '/assets/css/direction-landing.css');
$APPLICATION->AddHeadString('<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600&amp;display=swap" rel="stylesheet">', true);
$APPLICATION->AddHeadString('<script defer src="' . $tplPath . '/assets/js/direction-landing.js?v=' . $ver($tplPath . '/assets/js/direction-landing.js') . '"></script>', true);

$h = static fn($s) => htmlspecialcharsbx((string)$s);
$sprite = $tplPath . '/assets/img/direction-landing-sprite.svg?v=' . $ver($tplPath . '/assets/img/direction-landing-sprite.svg');
$ico = static function (string $id, string $class = '') use ($sprite): string {
    return '<svg' . ($class ? ' class="' . $class . '"' : '') . ' aria-hidden="true" focusable="false"><use href="' . $sprite . '#' . $id . '"/></svg>';
};
$art = static function (string $name) use ($sprite): string {
    return '<svg class="dl-art" aria-hidden="true" focusable="false"><use href="' . $sprite . '#art-' . htmlspecialcharsbx($name) . '"/></svg>';
};
/** Многострочный текст из админки: экранируем и сохраняем переносы */
$text = static fn($s) => nl2br(htmlspecialcharsbx((string)$s), false);

$S = $arResult['sections'];
$N = $arResult['form']['names'] ?? [];

// Порядок секций на странице отличается по направлениям (визуальный CSS order, а не физическая
// перестановка PHP-блоков ниже — так у «Прачечной» порядок не меняется, а у «Текстиля» точно
// совпадает с брифом: Проекты·Задача·Зоны·Каталог·Бренды·Технологии·Как работаем·Отзывы·Гарантия·
// Библиотека·Вопросы·Контакты). Секции, которых нет в карте (или для остальных направлений),
// остаются в исходном порядке — inline-style не добавляется.
$sectionOrderMaps = [
    'textile' => [
        'top' => 1, 'projects' => 2, 'task' => 2, 'solutions' => 3, 'stock' => 4, 'brands' => 5,
        'fm' => 6, 'own' => 7, 'brand_custom' => 8, 'parts' => 9, 'tech' => 10, 'design' => 11,
        'reviews' => 12, 'guarantee' => 13, 'library' => 14, 'expert' => 15, 'faq' => 16, 'final' => 17,
    ],
];
$sectionOrder = $sectionOrderMaps[$arResult['DIRECTION_CODE']] ?? [];
$ord = static fn(string $key): string => isset($sectionOrder[$key]) ? ' style="order:' . (int)$sectionOrder[$key] . '"' : '';
$formId = (int)($arResult['form']['form_id'] ?? 0);
$slides = $arResult['slides'];
$host = (\CMain::IsHTTPS() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
$abs = static fn(string $u) => preg_match('#^https?://#', $u) ? $u : $host . $u;

$has = [
    'projects' => !empty($S['projects']) && $arResult['projects'],
    'consult' => !empty($S['consult']),
    'task' => !empty($S['task']),
    'stock' => !empty($S['stock']) && $arResult['stock'],
    'novelties' => !empty($S['stock']) && $arResult['novelties'],
    'brands' => !empty($S['brands']) && $arResult['brands'],
    'own' => !empty($S['own']),
    'fm' => !empty($S['fm']),
    'brand_custom' => !empty($S['brand_custom']),
    'parts' => !empty($S['parts']),
    'solutions' => !empty($S['solutions']) && $arResult['solutions'],
    'calc' => !empty($S['calc']),
    'tech' => !empty($S['tech']) && $arResult['tech'],
    'tech_form' => !empty($S['tech_form']),
    'design' => !empty($S['design']) && $arResult['steps'],
    'tz' => !empty($S['tz']),
    'reviews' => !empty($S['reviews']) && $arResult['reviews'],
    'guarantee' => !empty($S['guarantee']) && count($arResult['guarantee']) === 4,
    'guarantee_form' => !empty($S['guarantee_form']),
    'library' => !empty($S['library']) && $arResult['library'],
    'expert' => !empty($S['expert']) && $arResult['materials']['big'],
    'faq' => !empty($S['faq']) && $arResult['faq'],
    'final' => !empty($S['final']),
];

// Скрытые поля формы: сессия, идентификатор формы, источник лида, URL страницы, Метрика/UTM
$formHidden = static function (string $source) use ($N, $formId, $h): string {
    $o = bitrix_sessid_post();
    $o .= '<input type="hidden" name="WEB_FORM_ID" value="' . $formId . '">';
    $o .= '<input type="hidden" name="web_form_submit" value="Отправить">';
    $o .= '<input type="hidden" class="dl-src" name="' . $h($N['land_source'] ?? '') . '" value="' . $h($source) . '">';
    $o .= '<input type="hidden" class="js-url" name="' . $h($N['land_url'] ?? '') . '" value="">';
    foreach (['client_id', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $k) {
        $o .= '<input type="hidden" data-track="' . $k . '" name="' . $h($N['land_' . $k] ?? '') . '" value="">';
    }
    return $o;
};
$consent = '<p class="dl-consent">Нажимая кнопку, вы соглашаетесь с <a href="/politika-konfidentsialnosti/" target="_blank" rel="noopener">политикой обработки персональных данных</a></p>';
$formOpen = static function (string $source, string $class = '') use ($h): string {
    return '<form class="dl-form fb-form ' . $class . '" method="post" enctype="multipart/form-data" action="/local/ajax/form/?template_form=landing" data-source="' . $h($source) . '" data-goal="' . $h($source) . '" novalidate>';
};
$okBox = static fn(string $t) => '<div class="dl-ok" role="status" aria-live="polite">' . htmlspecialcharsbx($t) . '</div>';

/** Мини-форма «имя + телефон» (консультация) */
$miniForm = static function (array $sec) use ($formOpen, $formHidden, $N, $h, $consent, $okBox): string {
    $o = $formOpen($sec['source'], 'dl-mini-wrap');
    $o .= '<div class="dl-fbody">' . $formHidden($sec['source']) . '<div class="dl-mini">';
    $o .= '<div class="dl-field"><label>Имя</label><input name="' . $h($N['land_name'] ?? '') . '" autocomplete="name"></div>';
    $o .= '<div class="dl-field"><label>Телефон</label><input name="' . $h($N['land_phone'] ?? '') . '" type="tel" inputmode="tel" autocomplete="tel" data-kind="phone" required></div>';
    $o .= '<button class="dl-btn" type="submit">' . $h($sec['btn'] ?: 'Отправить') . '</button>';
    $o .= $consent . '</div></div>' . $okBox($sec['ok'] ?: 'Заявка отправлена.') . '</form>';
    return $o;
};

/** Короткая форма «имя + телефон + компания» (form--short), опционально с чипами задачи впереди */
$shortForm = static function (array $sec) use ($formOpen, $formHidden, $N, $h, $consent, $okBox): string {
    $o = $formOpen($sec['source'], 'dl-mini-wrap');
    $o .= '<div class="dl-fbody">' . $formHidden($sec['source']);
    if (!empty($sec['tasks'])) {
        $o .= '<fieldset class="dl-topics"><legend>Задача</legend><div class="dl-chips">';
        foreach ($sec['tasks'] as $i => $t) {
            $o .= '<label class="dl-chip"><input type="radio" name="' . $h($N['land_topic'] ?? '') . '" value="' . $h($t['value']) . '"' . ($i === 0 ? ' checked' : '') . '><span>' . $h($t['value']) . '</span></label>';
        }
        $o .= '</div></fieldset>';
        $o .= '<fieldset class="dl-topics"><legend>Тип объекта</legend><div class="dl-chips">';
        foreach (['Отель 3–4★', 'Отель 5★', 'SPA', 'Ресторан', 'Частные дома'] as $i => $ot) {
            $o .= '<label class="dl-chip"><input type="radio" name="' . $h($N['land_objtype'] ?? '') . '" value="' . $h($ot) . '"' . ($i === 0 ? ' checked' : '') . '><span>' . $h($ot) . '</span></label>';
        }
        $o .= '</div></fieldset>';
    }
    $o .= '<div class="dl-mini dl-mini--3">';
    $o .= '<div class="dl-field"><label>Имя</label><input name="' . $h($N['land_name'] ?? '') . '" autocomplete="name"></div>';
    $o .= '<div class="dl-field"><label>Телефон</label><input name="' . $h($N['land_phone'] ?? '') . '" type="tel" inputmode="tel" autocomplete="tel" data-kind="phone" required></div>';
    $o .= '<div class="dl-field"><label>Компания</label><input name="' . $h($N['land_company'] ?? '') . '" autocomplete="organization"></div>';
    $o .= '<button class="dl-btn" type="submit">' . $h($sec['btn'] ?: 'Отправить') . '</button>';
    $o .= $consent . '</div></div>' . $okBox($sec['ok'] ?: 'Заявка отправлена.') . '</form>';
    return $o;
};

// ---------------------------------------------------------------------------
// якорное меню
// ---------------------------------------------------------------------------
$anchorMap = ['projects', 'task', 'stock', 'brands', 'parts', 'solutions', 'tech', 'design', 'guarantee', 'library', 'expert', 'faq', 'final'];
$anchorVisible = $has;
$anchorVisible['stock'] = $has['stock'] || $has['novelties']; // «Каталог»: видна, если есть хотя бы одна из вкладок
// Порядок пунктов меню — как секции реально идут на странице ($sectionOrder), а не как в админке;
// для направлений без своей карты порядка (пока только «Прачечная») — как раньше, по сортировке блоков.
$anchorSortKeys = array_keys($S);
if ($sectionOrder) {
    usort($anchorSortKeys, static fn($a, $b) => ($sectionOrder[$a] ?? 999) <=> ($sectionOrder[$b] ?? 999));
}
$anchors = [];
foreach ($anchorSortKeys as $key) {
    $sec = $S[$key];
    if (in_array($key, $anchorMap, true) && !empty($anchorVisible[$key]) && $sec['anchor'] !== '') {
        $anchors[] = ['id' => $key, 'label' => $sec['anchor']];
    }
}

// ---------------------------------------------------------------------------
// микроразметка
// ---------------------------------------------------------------------------
$ld = [];
$ld[] = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => $abs('/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $arResult['direction']['name'] ?? 'Прачечная'],
    ],
];
if ($has['projects']) {
    $pos = 0;
    $ld[] = [
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => $S['projects']['name'],
        'itemListElement' => array_map(static function ($p) use (&$pos, $abs) {
            return ['@type' => 'ListItem', 'position' => ++$pos, 'name' => $p['name'], 'url' => $abs($p['url'])];
        }, $arResult['projects']),
    ];
}
foreach ($has['stock'] ? $arResult['stock'] : [] as $p) {
    $prod = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p['fullName'], 'url' => $abs($p['url'])];
    if ($p['brand']) {
        $prod['brand'] = ['@type' => 'Brand', 'name' => $p['brand']];
    }
    if ($p['pic'] && ($f = \CFile::GetFileArray($p['pic']))) {
        $prod['image'] = $abs($f['SRC']);
    }
    if ($p['price']) {
        $prod['offers'] = ['@type' => 'Offer', 'price' => (string)(int)$p['price'], 'priceCurrency' => 'RUB',
            'availability' => 'https://schema.org/InStock', 'url' => $abs($p['url'])];
    }
    $ld[] = $prod;
}
$ldFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG;
?>
<div class="dl" data-direction="<?= $h($arResult['DIRECTION_CODE']) ?>" data-metrika="<?= (int)YANDEX_METRIKA_COUNTER_ID ?>">
<script>
// Шапка сайта — position:fixed с высотой, зависящей от ширины экрана: выравниваем по факту до первой отрисовки
(function(){var h=document.querySelector('.header'),r=document.currentScript.parentNode;if(h&&r)r.style.setProperty('--dl-hdr',h.offsetHeight+'px');})();
</script>

<?php foreach ($ld as $block): ?>
<script type="application/ld+json"><?= json_encode($block, $ldFlags) ?></script>
<?php endforeach; ?>

<nav class="dl-crumbs dl-container" aria-label="Хлебные крошки">
  <ol><li><a href="/">Главная</a></li><li aria-current="page"><?= $h($arResult['direction']['name'] ?? '') ?></li></ol>
</nav>

<?php if ($anchors || !empty($S['anchors']['btn'])): ?>
<nav class="dl-anchors" id="dl-anchors" aria-label="Разделы страницы">
  <div class="dl-container dl-anchors__in">
    <ul class="dl-anchors__scroll">
      <?php foreach ($anchors as $a): ?>
      <li><a href="#<?= $h($a['id']) ?>"><?= $h($a['label']) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <?php if (!empty($S['anchors']['btn'])): ?>
    <button class="dl-btn" type="button" data-goto="#final" data-src="<?= $h($S['anchors']['source']) ?>"><?= $h($S['anchors']['btn']) ?></button>
    <?php endif; ?>
  </div>
</nav>
<?php endif; ?>

<?php if ($arResult['h1'] !== ''): ?><h1 class="dl-visually-hidden"><?= $h($arResult['h1']) ?></h1><?php endif; ?>

<?php if ($slides): ?>
<!-- 01 первый экран -->
<section class="dl-hero" id="top"<?= $ord('top') ?>>
  <div class="dl-hero__stage">
    <?php foreach ($slides as $i => $s): ?>
    <div class="dl-slide<?= $i === 0 ? ' is-on' : '' ?>" id="dl-s<?= $i + 1 ?>" role="group" aria-roledescription="слайд" aria-label="<?= $i + 1 ?> из <?= count($slides) ?>">
      <div class="dl-ph dl-ph--steel">
        <?php if ($s['pic']): ?>
          <?php if ($s['picMob']): ?>
            <picture class="dl-slide__pic">
              <source media="(max-width:860px)" srcset="<?= $h(\CFile::GetPath($s['picMob'])) ?>">
              <?= profequip_LandingPicture($s['pic'], [1280, 1920], ['alt' => '', 'sizes' => '100vw', 'priority' => $i === 0, 'lazy' => $i > 0]) ?>
            </picture>
          <?php else: ?>
            <?= profequip_LandingPicture($s['pic'], [1280, 1920], ['alt' => '', 'sizes' => '100vw', 'priority' => $i === 0, 'lazy' => $i > 0]) ?>
          <?php endif; ?>
        <?php else: ?>
          <?= $art($s['art']) ?>
        <?php endif; ?>
      </div>
      <div class="dl-container dl-slide__body">
        <?php if ($s['kicker']): ?><div class="dl-slide__kicker"><?= $h($s['kicker']) ?></div><?php endif; ?>
        <?php if ($i === 0 && $arResult['h1'] === ''): ?><h1 class="dl-h1"><?= $h($s['name']) ?></h1><?php else: ?><p class="dl-slide__title"><?= $h($s['name']) ?></p><?php endif; ?>
        <p class="dl-slide__text"><?= $h($s['text']) ?></p>
        <div class="dl-slide__cta">
          <?php if ($s['btn1']['text']): ?>
            <?php if (strncmp($s['btn1']['target'], '#', 1) === 0): ?>
              <button class="dl-btn dl-btn--dark" type="button" data-goto="<?= $h($s['btn1']['target']) ?>" data-src="<?= $h($s['btn1']['source']) ?>"><?= $h($s['btn1']['text']) ?></button>
            <?php else: ?>
              <a class="dl-btn dl-btn--dark" href="<?= $h($s['btn1']['target']) ?>"><?= $h($s['btn1']['text']) ?></a>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($s['btn2']['text']): ?>
            <a class="dl-btn dl-btn--ghost-light" href="<?= $h($s['btn2']['target']) ?>"><?= $h($s['btn2']['text']) ?></a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (count($slides) > 1): ?>
    <div class="dl-hero__tabs">
      <div class="dl-container" role="tablist" aria-label="Слайды">
        <?php foreach ($slides as $i => $s): ?>
        <button class="dl-htab<?= $i === 0 ? ' is-on' : '' ?>" type="button" role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="dl-s<?= $i + 1 ?>"><?= $h($s['tab']) ?></button>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($arResult['advantages']): ?>
  <div class="dl-adv">
    <div class="dl-container">
      <ul class="dl-adv__grid">
        <?php foreach ($arResult['advantages'] as $a): ?>
        <li class="dl-adv__item"><?= $ico('i-' . $a['icon']) ?><b><?= $h($a['name']) ?></b></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($has['projects'] || $has['consult']): ?>
<!-- 02 проекты + форма консультации -->
<section class="dl-sec dl-projects" id="projects"<?= $ord('projects') ?>>
  <div class="dl-container">
    <?php if ($has['projects']): $sec = $S['projects']; ?>
    <div class="dl-sec__head">
      <div>
        <h2 class="dl-h2"><?= $h($sec['name']) ?></h2>
        <?php if ($sec['lead']): ?><p class="dl-lead"><?= $text($sec['lead']) ?></p><?php endif; ?>
      </div>
      <div class="dl-sec__aside">
        <?php if ($sec['linkText']): ?><a class="dl-link" href="<?= $h($sec['linkUrl']) ?>"><?= $h($sec['linkText']) ?></a><?php endif; ?>
        <div class="dl-arrows"><button class="dl-arr" type="button" data-scroll="#dl-caseTrack" data-dir="-1" aria-label="Назад"><?= $ico('i-left') ?></button><button class="dl-arr" type="button" data-scroll="#dl-caseTrack" data-dir="1" aria-label="Вперёд"><?= $ico('i-arrow') ?></button></div>
      </div>
    </div>
    <div class="dl-track" id="dl-caseTrack">
      <?php foreach ($arResult['projects'] as $i => $p): ?>
      <a class="dl-case" href="<?= $h($p['url']) ?>">
        <div class="dl-ph">
          <?= profequip_LandingPicture($p['pic'], [640, 960], ['alt' => $p['name'], 'sizes' => '(max-width:860px) 86vw, 40vw']) ?>
          <?= $art('tunnel') ?>
        </div>
        <?php if ($p['meta']): ?><div class="dl-case__meta"><?php foreach ($p['meta'] as $m): ?><span><?= $h($m) ?></span><?php endforeach; ?></div><?php endif; ?>
        <h3 class="dl-h3"><?= $h($p['name']) ?></h3>
        <?php if ($p['text']): ?><p><?= $h($p['text']) ?></p><?php endif; ?>
        <?php if ($p['scope']): ?><div class="dl-case__scope"><?php foreach ($p['scope'] as $t): ?><span><?= $h($t) ?></span><?php endforeach; ?></div><?php endif; ?>
        <span class="dl-link">Смотреть проект</span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($has['task']): $sec = $S['task']; ?>
    <div class="dl-cband" id="task">
      <div>
        <h3><?= $h($sec['name']) ?></h3>
        <?php if ($sec['lead']): ?><p><?= $h($sec['lead']) ?></p><?php endif; ?>
      </div>
      <?= $shortForm($sec) ?>
    </div>
    <?php elseif ($has['consult']): $sec = $S['consult']; ?>
    <div class="dl-cband" id="consult">
      <div>
        <h3><?= $h($sec['name']) ?></h3>
        <?php if ($sec['lead']): ?><p><?= $h($sec['lead']) ?></p><?php endif; ?>
      </div>
      <?= $miniForm($sec) ?>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($has['stock'] || $has['novelties']): $sec = $S['stock']; $showToggle = $has['stock'] && $has['novelties']; ?>
<!-- 03 каталог: новинки / в наличии -->
<section class="dl-sec dl-sec--grey" id="stock"<?= $ord('stock') ?>>
  <div class="dl-container">
    <div class="dl-sec__head">
      <div>
        <h2 class="dl-h2"><?= $h($sec['name']) ?></h2>
        <?php if ($sec['lead']): ?><p class="dl-lead"><?= $text($sec['lead']) ?></p><?php endif; ?>
      </div>
      <?php if ($sec['linkText']): ?><div class="dl-sec__aside"><a class="dl-link" href="<?= $h($sec['linkUrl']) ?>"><?= $h($sec['linkText']) ?></a></div><?php endif; ?>
    </div>
    <?php if ($showToggle): ?>
    <div class="dl-toggle" role="tablist" aria-label="Каталог">
      <button class="dl-toggle__btn is-on" type="button" role="tab" aria-selected="true" data-switch-btn="novelties">Новинки</button>
      <button class="dl-toggle__btn" type="button" role="tab" aria-selected="false" data-switch-btn="stock">В наличии на складе</button>
    </div>
    <?php endif; ?>
    <?php if ($has['novelties']): ?>
    <div class="dl-stock__grid" data-switch-panel="novelties">
      <?php foreach ($arResult['novelties'] as $p): ?>
      <article class="dl-pcard">
        <div class="dl-ph dl-ph--light">
          <?= profequip_LandingPicture($p['pic'], [480, 800], ['alt' => $p['name'], 'sizes' => '(max-width:860px) 78vw, 30vw']) ?>
          <span class="dl-badge dl-badge--new">Новинка</span>
          <?= $art('washer') ?>
        </div>
        <div class="dl-pcard__body">
          <h3><?php if ($p['url']): ?><a href="<?= $h($p['url']) ?>"><?= $h($p['name']) ?></a><?php else: ?><?= $h($p['name']) ?><?php endif; ?></h3>
          <?php if ($p['params']): ?>
          <dl><?php foreach ($p['params'] as $sp): ?><dt><?= $h($sp['desc']) ?></dt><dd><?= $h($sp['value']) ?></dd><?php endforeach; ?></dl>
          <?php endif; ?>
          <div class="dl-pcard__foot">
            <?php if ($p['url']): ?><a class="dl-link" href="<?= $h($p['url']) ?>">В каталог</a><?php endif; ?>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($has['stock']): ?>
    <div class="dl-stock__grid" data-switch-panel="stock"<?= $showToggle ? ' hidden' : '' ?>>
      <?php foreach ($arResult['stock'] as $p):
        $kpSrc = 'КП · ' . $p['name'] . ($p['specs'] ? ', ' . mb_strtolower($p['specs'][0]['label']) . ' ' . $p['specs'][0]['value'] : '');
      ?>
      <article class="dl-pcard">
        <div class="dl-ph dl-ph--light">
          <?= profequip_LandingPicture($p['pic'], [480, 800], ['alt' => $p['name'], 'sizes' => '(max-width:860px) 78vw, 30vw']) ?>
          <span class="dl-badge"><?= $ico('i-check') ?>В наличии</span>
          <?= $art('washer') ?>
        </div>
        <div class="dl-pcard__body">
          <?php if ($p['brand']): ?><span class="dl-pcard__brand"><?= $h($p['brand']) ?></span><?php endif; ?>
          <h3><a href="<?= $h($p['url']) ?>"><?= $h($p['name']) ?></a></h3>
          <?php if ($p['type']): ?><span class="dl-pcard__type"><?= $h($p['type']) ?></span><?php endif; ?>
          <?php if ($p['specs']): ?>
          <dl><?php foreach ($p['specs'] as $sp): ?><dt><?= $h($sp['label']) ?></dt><dd><?= $h($sp['value']) ?></dd><?php endforeach; ?></dl>
          <?php endif; ?>
          <div class="dl-pcard__foot">
            <?php if ($p['price']): ?>
              <div class="dl-price"><?= number_format($p['price'], 0, ',', "\u{00A0}") ?>&nbsp;₽<?php if ($sec['priceNote']): ?><small><?= $h($sec['priceNote']) ?></small><?php endif; ?></div>
            <?php else: ?>
              <div class="dl-price dl-price--req">Цена по запросу</div>
            <?php endif; ?>
            <button class="dl-btn dl-btn--sm" type="button" data-goto="#final" data-src="<?= $h($kpSrc) ?>"><?= $h($sec['btn'] ?: 'Получить КП') ?></button>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($has['brands']): $sec = $S['brands']; ?>
<!-- 04 бренды -->
<section class="dl-sec dl-brands-sec" id="brands"<?= $ord('brands') ?>>
  <div class="dl-container">
    <div class="dl-sec__head">
      <div>
        <h2 class="dl-h2"><?= $h($sec['name']) ?></h2>
        <?php if ($sec['lead']): ?><p class="dl-lead"><?= $text($sec['lead']) ?></p><?php endif; ?>
      </div>
      <?php if ($sec['featuredPic'] || $sec['featuredName'] || $sec['linkText']): ?>
      <div class="dl-sec__aside">
        <?php if ($sec['featuredPic'] || $sec['featuredName']): $tag = $sec['featuredUrl'] ? 'a' : 'div'; ?>
        <<?= $tag ?> class="dl-brand-featured"<?= $sec['featuredUrl'] ? ' href="' . $h($sec['featuredUrl']) . '"' : '' ?>>
          <?php if ($sec['featuredPic']): ?><?= profequip_LandingPicture($sec['featuredPic'], [200, 400], ['alt' => $sec['featuredName'], 'sizes' => '200px', 'class' => 'dl-brand-featured__img']) ?>
          <?php else: ?><span class="dl-brand-featured__txt"><?= $h(mb_strtoupper($sec['featuredName'])) ?></span>
          <?php endif; ?>
          <span class="dl-brand-featured__cap"><?= $h($sec['featuredLabel'] ?: 'Собственный бренд') ?></span>
        </<?= $tag ?>>
        <?php endif; ?>
        <?php if ($sec['linkText']): ?><a class="dl-link" href="<?= $h($sec['linkUrl']) ?>"><?= $h($sec['linkText']) ?></a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="dl-brands" aria-label="Производители">
    <div class="dl-brands__row">
      <?php for ($pass = 0; $pass < 2; $pass++): // второй проход — копия для бесшовной ленты ?>
        <?php foreach ($arResult['brands'] as $b): ?>
        <a href="<?= $h($b['url']) ?>"<?= $pass ? ' tabindex="-1" aria-hidden="true"' : '' ?>>
          <?php if ($b['logo']): ?><?= profequip_LandingPicture($b['logo'], [180, 360], ['alt' => $pass ? '' : $b['name'], 'sizes' => '180px', 'class' => 'dl-brand-logo']) ?><?php endif; ?>
          <span class="dl-brand-txt"><?= $h(mb_strtoupper($b['name'])) ?></span>
        </a>
        <?php endforeach; ?>
      <?php endfor; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($has['fm']): $sec = $S['fm']; ?>
<!-- 04a Fresco Maggiore -->
<section class="dl-sec dl-fm"<?= $ord('fm') ?> id="fm">
  <div class="dl-container dl-fm__grid">
    <div class="dl-fm__txt">
      <div class="dl-fm__kicker">Fresco Maggiore</div>
      <h2 class="dl-h2"><?= $h($sec['name']) ?></h2>
      <?php if ($sec['lead']): ?><p class="dl-lead"><?= $text($sec['lead']) ?></p><?php endif; ?>
      <?php if ($sec['items']): ?>
      <ul class="dl-fm__points">
        <?php foreach ($sec['items'] as $t): ?><li><?= $h($t['value']) ?></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if ($sec['linkText']): ?><a class="dl-btn dl-btn--dark" href="<?= $h($sec['linkUrl'] ?: '#') ?>"><?= $h($sec['linkText']) ?></a><?php endif; ?>
    </div>
    <div class="dl-ph dl-ph--steel"><?= profequip_LandingPicture($sec['pic'], [640, 1000], ['alt' => $sec['name'], 'sizes' => '(max-width:860px) 100vw, 45vw']) ?><?= $art('roll') ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($has['own']): $sec = $S['own']; ?>
<!-- 04a.2 собственное производство ПРОФЭКВИП -->
<section class="dl-own"<?= $ord('own') ?> id="own">
  <div class="dl-container dl-own__grid">
    <div class="dl-own__txt">
      <div class="dl-own__kicker"><?= $ico('i-check') ?>Собственное производство</div>
      <h2 class="dl-h2"><?= $h($sec['name']) ?></h2>
      <?php if ($sec['lead']): ?><p class="dl-lead"><?= $text($sec['lead']) ?></p><?php endif; ?>
      <?php if ($sec['items']): ?>
      <ul class="dl-theses">
        <?php foreach ($sec['items'] as $t): ?>
        <li><?= $ico('i-shield') ?><div><b><?= $h($t['desc']) ?></b><span><?= $h($t['value']) ?></span></div></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <div class="dl-own__cta">
        <?php if ($sec['btn']): ?><button class="dl-btn dl-btn--dark" type="button" data-goto="#final" data-topic="samples" data-src="<?= $h($sec['source'] ?: $sec['btn']) ?>"><?= $h($sec['btn']) ?></button><?php endif; ?>
        <?php if ($sec['btn2Text']): ?><a class="dl-btn dl-btn--ghost-light" href="<?= $h($sec['btn2Url'] ?: '#stock') ?>"><?= $h($sec['btn2Text']) ?></a><?php endif; ?>
      </div>
    </div>
    <div class="dl-ph dl-ph--steel dl-own__media"><?= profequip_LandingPicture($sec['pic'], [640, 1000], ['alt' => $sec['name'], 'sizes' => '(max-width:860px) 100vw, 52vw']) ?><?= $art('towels') ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($has['brand_custom']): $sec = $S['brand_custom']; ?>
<!-- 04b индивидуальное брендирование -->
<section class="dl-sec dl-brand-custom"<?= $ord('brand_custom') ?>>
  <div class="dl-container dl-brand-custom__grid">
    <div class="dl-brand-custom__txt">
      <h2 class="dl-h2"><?= $h($sec['name']) ?></h2>
      <?php if ($sec['lead']): ?><p class="dl-lead"><?= $text($sec['lead']) ?></p><?php endif; ?>
      <?php if ($sec['items']): ?>
      <ul class="dl-theses">
        <?php foreach ($sec['items'] as $i => $t): ?>
        <li><?= $ico('i-check') ?><div><b><?= $h($t['desc']) ?></b><span><?= $h($t['value']) ?></span></div></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if ($sec['linkText']): ?><a class="dl-link" href="<?= $h($sec['linkUrl'] ?: '#') ?>"><?= $h($sec['linkText']) ?></a><?php endif; ?>
    </div>
    <div class="dl-ph dl-ph--alt"><?= profequip_LandingPicture($sec['pic'], [640, 1000], ['alt' => $sec['name'], 'sizes' => '(max-width:860px) 100vw, 45vw']) ?><?= $art('doc') ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($has['parts']): $sec = $S['parts']; ?>
<!-- 05 запчасти -->
<section class="dl-parts" id="parts"<?= $ord('parts') ?>>
  <div class="dl-container dl-parts__grid">
    <div class="dl-parts__txt">
      <h2 class="dl-h2"><?= $h($sec['name']) ?></h2>
      <?php if ($sec['lead']): ?><p class="dl-lead"><?= $text($sec['lead']) ?></p><?php endif; ?>
      <?php if ($sec['items']): $icons = ['i-box', 'i-shield', 'i-search']; ?>
      <ul class="dl-theses">
        <?php foreach ($sec['items'] as $i => $t): ?>
        <li><?= $ico($icons[$i % 3]) ?><div><b><?= $h($t['desc']) ?></b><span><?= $h($t['value']) ?></span></div></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <div class="dl-parts__cta">
        <button class="dl-btn dl-btn--dark" type="button" id="dl-partsOpen" aria-controls="dl-partsForm" aria-expanded="false"><?= $h($sec['btn'] ?: 'Подобрать запчасть') ?></button>
        <?php if ($sec['btn2Text']): ?><a class="dl-btn dl-btn--ghost-light" href="<?= $h($sec['btn2Url']) ?>"><?= $h($sec['btn2Text']) ?></a><?php endif; ?>
      </div>
    </div>
    <div class="dl-parts__media">
      <div class="dl-ph dl-ph--steel"><?= profequip_LandingPicture($sec['pic'], [640, 1000], ['alt' => $sec['name'], 'sizes' => '(max-width:860px) 100vw, 52vw']) ?><?= $art('parts') ?></div>
      <form class="dl-form fb-form dl-pform" id="dl-partsForm" method="post" enctype="multipart/form-data" action="/local/ajax/form/?template_form=landing" data-source="<?= $h($sec['source']) ?>" data-goal="<?= $h($sec['source']) ?>" novalidate>
        <div class="dl-pform__top">
          <div><h3 class="dl-h3"><?= $h($sec['formTitle'] ?: 'Подбор запчасти') ?></h3><?php if ($sec['formText']): ?><p class="dl-small dl-muted"><?= $h($sec['formText']) ?></p><?php endif; ?></div>
          <button type="button" class="dl-x" id="dl-partsClose" aria-label="Закрыть форму"><?= $ico('i-x') ?></button>
        </div>
        <div class="dl-fbody">
          <?= $formHidden($sec['source']) ?>
          <div class="dl-fgrid">
            <div class="dl-field dl-full"><label for="dl-pf1">Производитель и модель оборудования</label><input id="dl-pf1" name="<?= $h($N['land_model'] ?? '') ?>" placeholder="Например, Electrolux WH6-20" required></div>
            <div class="dl-field"><label for="dl-pf2">Артикул, если известен</label><input id="dl-pf2" name="<?= $h($N['land_article'] ?? '') ?>" placeholder="Необязательно"></div>
            <div class="dl-field"><label for="dl-pf3">Телефон или e-mail</label><input id="dl-pf3" name="<?= $h($N['land_phone'] ?? '') ?>" data-kind="contact" autocomplete="tel" required></div>
            <label class="dl-file dl-full"><?= $ico('i-clip') ?><span data-fname>Прикрепить фото детали или шильдика</span><em>JPG, PNG до 10 МБ</em><input type="file" accept="image/*" data-max="10" data-files-to=".dl-fileslots"></label>
            <div class="dl-fileslots" hidden><?php for ($k = 1; $k <= 3; $k++): ?><input type="file" name="<?= $h($N['land_file_' . $k] ?? '') ?>"><?php endfor; ?></div>
            <div class="dl-fsend dl-full"><button class="dl-btn" type="submit"><?= $h($sec['btn'] ?: 'Подобрать запчасть') ?></button><?= $consent ?></div>
          </div>
        </div>
        <?= $okBox($sec['ok'] ?: 'Запрос отправлен.') ?>
      </form>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($has['solutions'] || $has['calc']): ?>
<!-- 06 решения + расчёт -->
<section class="dl-sec" id="solutions"<?= $ord('solutions') ?>>
  <div class="dl-container">
    <?php if ($has['solutions']): $sec = $S['solutions']; ?>
    <div class="dl-sec__head">
      <div>
        <h2 class="dl-h2"><?= $h($sec['name']) ?></h2>
        <?php if ($sec['lead']): ?><p class="dl-lead"><?= $text($sec['lead']) ?></p><?php endif; ?>
      </div>
    </div>
    <?php
    $objTypeLabels = ['hotel34' => 'Отель 3–4★', 'hotel5' => 'Отель 5★', 'spa' => 'SPA', 'restoran' => 'Ресторан', 'private' => 'Частные дома'];
    $anyObjType = false;
    foreach ($arResult['solutions'] as $s) {
        if ($s['objtypes']) {
            $anyObjType = true;
            break;
        }
    }
    // переключатель — всегда полный список из пяти типов (тот же, что в форме «Задача»), чтобы оба места на странице совпадали
    $usedTypes = $anyObjType ? $objTypeLabels : [];
    ?>
    <?php if ($usedTypes): ?>
    <div class="dl-toggle dl-toggle--obj" id="dl-objtype" role="tablist" aria-label="Тип объекта" data-switch-scope=".dl-sol">
      <?php foreach ($usedTypes as $code => $label): ?>
      <button class="dl-toggle__btn<?= $code === array_key_first($usedTypes) ? ' is-on' : '' ?>" type="button" role="tab" aria-selected="<?= $code === array_key_first($usedTypes) ? 'true' : 'false' ?>" data-objtype="<?= $h($code) ?>"><?= $h($label) ?></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="dl-sol">
      <ul class="dl-sol__list" role="tablist" aria-label="Зоны">
        <?php foreach ($arResult['solutions'] as $i => $s): ?>
        <li data-objtypes="<?= $h(implode(',', $s['objtypes'])) ?>"><button class="dl-sol__btn" type="button" role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="dl-sp<?= $i + 1 ?>" id="dl-st<?= $i + 1 ?>"><span><b><?= $h($s['name']) ?></b><?php if ($s['sub']): ?><small><?= $h($s['sub']) ?></small><?php endif; ?></span><?= $ico('i-plus') ?></button><div class="dl-sol__slot"></div></li>
        <?php endforeach; ?>
      </ul>
      <div class="dl-sol__panels">
        <?php foreach ($arResult['solutions'] as $i => $s): ?>
        <div class="dl-sol__panel" role="tabpanel" id="dl-sp<?= $i + 1 ?>" aria-labelledby="dl-st<?= $i + 1 ?>"<?= $i === 0 ? '' : ' hidden' ?>>
          <div class="dl-ph <?= $i % 2 ? 'dl-ph--steel' : 'dl-ph--alt' ?>">
            <?= profequip_LandingPicture($s['pic'], [640, 1000], ['alt' => $s['name'], 'sizes' => '(max-width:860px) 100vw, 45vw']) ?>
            <?= $art($s['art']) ?>
          </div>
          <div class="dl-sol__body">
            <?php if ($s['specs']): ?>
            <ul class="dl-sol__spec"><?php foreach ($s['specs'] as $sp): ?><li><span><?= $h($sp['desc']) ?></span><b><?= $h($sp['value']) ?></b></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <?php if ($s['url']): ?><a class="dl-link dl-sol__more" href="<?= $h($s['url']) ?>"><?= $h($s['linkText'] ?: 'Подробнее') ?></a><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($has['calc']): $sec = $S['calc']; ?>
    <div class="dl-calc" id="calc">
      <div>
        <h2 class="dl-h2"><?= $h($sec['name']) ?></h2>
        <?php if ($sec['lead']): ?><p class="dl-lead"><?= $h($sec['lead']) ?></p><?php endif; ?>
      </div>
      <?= $miniForm($sec) ?>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($has['tech']): $sec = $S['tech']; ?>
<!-- 06b технологии -->
<section class="dl-sec dl-sec--grey" id="tech"<?= $ord('tech') ?>>
  <div class="dl-container">
    <div class="dl-sec__head">
      <div>
        <h2 class="dl-h2"><?= $h($sec['name']) ?></h2>
        <?php if ($sec['lead']): ?><p class="dl-lead"><?= $text($sec['lead']) ?></p><?php endif; ?>
      </div>
    </div>
    <?php
    // Разные контурные рисунки на карточках, пока нет реальных фото (иначе все карточки выглядят одинаково)
    $techArts = ['ironer', 'dryer', 'table', 'parts', 'tunnel', 'plan', 'washer', 'doc'];
    $techFeatured = null;
    $techSmall = [];
    foreach ($arResult['tech'] as $i => $t) {
        if ($t['featured'] && $techFeatured === null) {
            $techFeatured = [$i, $t];
        } else {
            $techSmall[] = [$i, $t];
        }
    }
    $techCard = static function (int $i, array $t, bool $full = false) use ($techArts, $h, $art, $text): string {
        $o = '<button class="dl-tech__card' . ($full ? ' dl-tech__card--full' : '') . '" type="button" data-tech-open aria-haspopup="dialog">';
        $o .= '<div class="dl-ph ' . ($i % 2 ? 'dl-ph--steel' : 'dl-ph--alt') . '">' . profequip_LandingPicture($t['pic'], [480, 800], ['alt' => $t['name'], 'sizes' => '240px']) . $art($techArts[$i % count($techArts)]) . '</div>';
        $o .= '<div class="dl-tech__body">';
        if ($t['task']) {
            $o .= '<span class="dl-tech__tag">' . $h($t['task']) . '</span>';
        }
        $o .= '<b class="dl-tech__name">' . $h($t['name']) . '</b>';
        if ($t['text']) {
            $o .= '<p>' . $h($t['text']) . '</p>';
        }
        $o .= '<span class="dl-tech__more">Подробнее →</span></div>';
        $o .= '<template data-tech-detail data-tech-title="' . $h($t['name']) . '">' . $text($t['detail'] ?: $t['text']) . '</template>';
        $o .= '</button>';
        return $o;
    };
    ?>
    <?php
    // Нечётная «лишняя» карточка встаёт в один ряд с главной (PIANO) — 50/50, а не отдельной строкой во всю ширину
    $techLeftover = null;
    if ($techFeatured && count($techSmall) % 2 === 1) {
        $techLeftover = array_pop($techSmall);
    }
    ?>
    <?php if ($techFeatured): ?>
    <div class="dl-tech__hero<?= $techLeftover ? ' dl-tech__hero--pair' : '' ?>">
      <?= $techCard($techFeatured[0], $techFeatured[1], true) ?>
      <?php if ($techLeftover): ?><?= $techCard($techLeftover[0], $techLeftover[1], true) ?><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="dl-tech">
      <?php foreach ($techSmall as $j => [$i, $t]):
        // если пары не получилось (нет featured-карточки) — нечётная последняя всё равно на всю строку
        $isLastSmall = !$techFeatured && ($j === count($techSmall) - 1) && (count($techSmall) % 2 === 1);
        echo $techCard($i, $t, $isLastSmall);
      endforeach; ?>
    </div>
    <dialog class="dl-tech__dialog" id="dl-techDialog" aria-labelledby="dl-techTitle">
      <button type="button" class="dl-x" data-tech-close aria-label="Закрыть"><?= $ico('i-x') ?></button>
      <b id="dl-techTitle"></b>
      <p data-tech-body></p>
    </dialog>

    <?php if ($has['tech_form']): $sec = $S['tech_form']; ?>
    <div class="dl-cband dl-cband--compact" id="tech_form">
      <div>
        <h3><?= $h($sec['name']) ?></h3>
        <?php if ($sec['lead']): ?><p><?= $h($sec['lead']) ?></p><?php endif; ?>
      </div>
      <?= $miniForm($sec) ?>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($has['design'] || $has['tz']): ?>
<!-- 07 проектирование + ТЗ -->
<section class="dl-sec dl-sec--grey" id="design"<?= $ord('design') ?>>
  <div class="dl-container">
    <?php if ($has['design']): $sec = $S['design']; $st = $arResult['steps']; ?>
    <div class="dl-sec__head">
      <div>
        <h2 class="dl-h2"><?= $h($sec['name']) ?></h2>
        <?php if ($sec['lead']): ?><p class="dl-lead"><?= $text($sec['lead']) ?></p><?php endif; ?>
      </div>
    </div>
    <?php
    // фото этапа (справа от текста): адрес нужного размера; пусто — карточка текстовая
    $stepPic = static function (int $id): string {
        if ($id <= 0) {
            return '';
        }
        $img = \CFile::ResizeImageGet($id, ['width' => 720, 'height' => 720], BX_RESIZE_IMAGE_PROPORTIONAL, false);
        return (string)($img['src'] ?? '');
    };
    $stepPics = array_map(static fn($s) => $stepPic((int)($s['pic'] ?? 0)), $st);
    ?>
    <div class="dl-steps" role="list" style="--dl-steps:<?= count($st) ?>">
      <?php foreach ($st as $i => $s): ?>
      <button class="dl-step" type="button" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" data-title="<?= $h($s['name']) ?>" data-text="<?= $h($s['text']) ?>" data-meta="<?= $h($s['result']) ?>" data-pic="<?= $h($stepPics[$i]) ?>"><span class="dl-step__n"><?= $i + 1 ?></span><b><?= $h($s['name']) ?></b></button>
      <?php endforeach; ?>
    </div>
    <div class="dl-step__detail<?= $stepPics[0] !== '' ? ' has-pic' : '' ?>" aria-live="polite"><p><strong><?= $h($st[0]['name']) ?></strong><span data-step-text><?= $h($st[0]['text']) ?></span></p><em data-step-meta><?= $h($st[0]['result']) ?></em><img class="dl-step__pic" data-step-pic src="<?= $h($stepPics[0]) ?>" alt="<?= $h($st[0]['name']) ?>" loading="lazy"<?= $stepPics[0] === '' ? ' hidden' : '' ?>></div>
    <?php endif; ?>

    <?php if ($has['tz']): $sec = $S['tz']; ?>
    <div class="dl-tz" id="tz">
      <div class="dl-tz__intro">
        <div>
          <h2 class="dl-h2"><?= $h($sec['name']) ?></h2>
          <?php if ($sec['lead']): ?><p class="dl-lead"><?= $h($sec['lead']) ?></p><?php endif; ?>
        </div>
        <?php if ($sec['items']): ?>
        <div class="dl-tz__facts"><?php foreach ($sec['items'] as $f): ?><div><b><?= $h($f['value']) ?></b><?= $h($f['desc']) ?></div><?php endforeach; ?></div>
        <?php endif; ?>
      </div>
      <?= $formOpen($sec['source']) ?>
        <div class="dl-fbody">
          <?= $formHidden($sec['source']) ?>
          <div class="dl-fgrid">
            <label class="dl-file dl-file--drop dl-full"><?= $ico('i-clip') ?><span data-fname>Прикрепите ТЗ, спецификацию или план помещения</span><em>PDF, DWG, XLSX, JPG до 25 МБ</em><input type="file" multiple data-max="25" data-files-to=".dl-fileslots"></label>
            <div class="dl-fileslots" hidden><?php for ($k = 1; $k <= 3; $k++): ?><input type="file" name="<?= $h($N['land_file_' . $k] ?? '') ?>"><?php endfor; ?></div>
            <div class="dl-field"><label for="dl-t1">Имя</label><input id="dl-t1" name="<?= $h($N['land_name'] ?? '') ?>" autocomplete="name"></div>
            <div class="dl-field"><label for="dl-t2">Компания</label><input id="dl-t2" name="<?= $h($N['land_company'] ?? '') ?>" autocomplete="organization"></div>
            <div class="dl-field"><label for="dl-t3">Телефон</label><input id="dl-t3" name="<?= $h($N['land_phone'] ?? '') ?>" type="tel" inputmode="tel" autocomplete="tel" data-kind="phone" required></div>
            <div class="dl-field"><label for="dl-t4">E-mail</label><input id="dl-t4" name="<?= $h($N['land_email'] ?? '') ?>" type="email" autocomplete="email"></div>
            <div class="dl-fsend dl-full"><button class="dl-btn" type="submit"><?= $h($sec['btn'] ?: 'Отправить') ?></button><?= $consent ?></div>
          </div>
        </div>
        <?= $okBox($sec['ok'] ?: 'Файлы получены.') ?>
      </form>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($has['reviews']): $sec = $S['reviews']; ?>
<!-- 07b отзывы -->
<section class="dl-sec" id="reviews"<?= $ord('reviews') ?>>
  <div class="dl-container">
    <div class="dl-sec__head">
      <div>
        <h2 class="dl-h2"><?= $h($sec['name']) ?></h2>
        <?php if ($sec['lead']): ?><p class="dl-lead"><?= $text($sec['lead']) ?></p><?php endif; ?>
      </div>
    </div>
    <div class="dl-letters">
      <?php foreach ($arResult['reviews'] as $rv): ?>
      <figure class="dl-letter">
        <div class="dl-ph dl-ph--light"><?= profequip_LandingPicture($rv['pic'], [220, 380], ['alt' => $rv['name'] ?: 'Отзыв', 'sizes' => '110px']) ?><?= $art('doc') ?></div>
        <figcaption>
          <blockquote><?= $text($rv['quote']) ?></blockquote>
          <cite><b><?= $h($rv['name']) ?></b><?= $h($rv['role']) ?></cite>
        </figcaption>
      </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($has['guarantee']): $sec = $S['guarantee']; $g = $arResult['guarantee']; ?>
<!-- 08 круговая гарантия -->
<section class="dl-sec" id="guarantee"<?= $ord('guarantee') ?>>
  <div class="dl-container dl-circ">
    <div class="dl-circ__txt">
      <h2 class="dl-h2"><?= $h($sec['name']) ?></h2>
      <?php if ($sec['lead']): ?><p><?= $h($sec['lead']) ?></p><?php endif; ?>
    </div>
    <div class="dl-ringwrap">
      <div class="dl-ring" id="dl-ring">
        <svg class="dl-ring__svg" viewBox="0 0 400 400" aria-hidden="true">
          <circle class="dl-ring__track" cx="200" cy="200" r="186"/>
          <?php foreach ([-130, -40, 50, 140] as $i => $rot): ?>
          <circle class="dl-ring__seg<?= $i === 0 ? ' is-on' : '' ?>" cx="200" cy="200" r="186" stroke-dasharray="258 910" transform="rotate(<?= $rot ?> 200 200)"/>
          <?php endforeach; ?>
        </svg>
        <?php foreach ($g as $i => $n): ?>
        <button class="dl-node dl-node--<?= $i + 1 ?>" type="button" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" data-title="<?= $h($n['name']) ?>" data-text="<?= $h($n['text']) ?>" data-link="<?= $h($n['url']) ?>" data-label="<?= $h($n['label']) ?>">
          <span class="dl-node__img">
            <?php if ($n['pic']): ?><?= profequip_LandingPicture($n['pic'], [96, 192], ['alt' => '', 'sizes' => '96px']) ?><?php else: ?><?= strncmp($n['icon'], 'i-', 2) === 0 || in_array($n['icon'], ['shield', 'box', 'compass', 'wrench', 'layers', 'chat', 'check'], true) ? $ico('i-' . $n['icon']) : $art($n['icon']) ?><?php endif; ?>
          </span><b><?= $h($n['name']) ?></b>
        </button>
        <?php endforeach; ?>
      </div>
      <div class="dl-ring__center" id="dl-ringCenter" aria-live="polite">
        <b><?= $h($g[0]['name']) ?></b>
        <span><?= $h($g[0]['text']) ?></span>
        <?php if ($g[0]['url']): ?><a href="<?= $h($g[0]['url']) ?>"><?= $h($g[0]['label']) ?></a><?php endif; ?>
      </div>
    </div>
    <?php if ($has['guarantee_form']): $sec = $S['guarantee_form']; ?>
    <div class="dl-cband dl-cband--compact" id="guarantee_form">
      <div>
        <h3><?= $h($sec['name']) ?></h3>
        <?php if ($sec['lead']): ?><p><?= $h($sec['lead']) ?></p><?php endif; ?>
      </div>
      <?= $shortForm($sec) ?>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($has['library']): $sec = $S['library']; ?>
<!-- 08b библиотека -->
<section class="dl-sec dl-sec--grey" id="library"<?= $ord('library') ?>>
  <div class="dl-container">
    <div class="dl-sec__head">
      <div>
        <h2 class="dl-h2"><?= $h($sec['name']) ?></h2>
        <?php if ($sec['lead']): ?><p class="dl-lead"><?= $text($sec['lead']) ?></p><?php endif; ?>
      </div>
    </div>
    <?php
    // крупное фото слева + плитка 2×2 справа: всего 5 карточек, лишние строки из админки не выводим
    $libCard = static function (array $p, int $i, bool $big) use ($h, $art, $sec): string {
        ob_start(); ?>
      <figure class="dl-library__<?= $big ? 'big' : 'sm' ?>">
        <?php if ($p['pic']): ?>
        <div class="dl-ph dl-ph--light"><?= profequip_LandingPicture($p['pic'], $big ? [640, 1000] : [320, 480], ['alt' => $p['caption'] ?: $sec['name'], 'sizes' => $big ? '(max-width:860px) 100vw, 45vw' : '(max-width:860px) 50vw, 25vw', 'lazy' => !$big]) ?></div>
        <?php if ($p['caption']): ?><figcaption><?= $h($p['caption']) ?></figcaption><?php endif; ?>
        <?php else: /* нет своего фото — фактурная заглушка, а не случайная картинка */ ?>
        <div class="dl-ph dl-ph--light dl-library__stub"><?= $art($i % 2 ? 'roll' : 'towels') ?><span class="dl-library__stub-cap">Фото: <?= $h($p['caption'] ?: 'уточняется') ?></span></div>
        <?php endif; ?>
      </figure>
        <?php return ob_get_clean();
    };
    $libItems = array_slice($arResult['library'], 0, 5);
    ?>
    <div class="dl-library">
      <?= $libCard($libItems[0], 0, true) ?>
      <?php if (count($libItems) > 1): ?>
      <div class="dl-library__tiles">
        <?php foreach (array_slice($libItems, 1) as $k => $p): ?><?= $libCard($p, $k + 1, false) ?><?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($has['expert']): $sec = $S['expert']; $big = $arResult['materials']['big']; ?>
<!-- 09 экспертный центр -->
<section class="dl-sec dl-sec--grey" id="expert"<?= $ord('expert') ?>>
  <div class="dl-container">
    <div class="dl-sec__head">
      <div>
        <h2 class="dl-h2"><?= $h($sec['name']) ?></h2>
        <?php if ($sec['lead']): ?><p class="dl-lead"><?= $text($sec['lead']) ?></p><?php endif; ?>
      </div>
      <?php if ($sec['links']): ?><div class="dl-sec__aside dl-exp-links"><?php foreach ($sec['links'] as $l): ?><a class="dl-link" href="<?= $h($l['value']) ?>"><?= $h($l['desc']) ?></a><?php endforeach; ?></div><?php endif; ?>
    </div>
    <div class="dl-exp">
      <a class="dl-art-big" href="<?= $h($big['url']) ?>">
        <div class="dl-ph dl-ph--alt"><?= profequip_LandingPicture($big['pic'], [640, 1000], ['alt' => $big['name'], 'sizes' => '(max-width:860px) 100vw, 55vw']) ?><?= $art('plan') ?></div>
        <div class="dl-art-meta"><span class="dl-tag<?= $big['type'] === 'news' ? ' dl-tag--news' : '' ?>"><?= $big['type'] === 'news' ? 'Новость' : 'Статья' ?></span><?php if ($big['date']): ?><span class="dl-num"><?= $h($big['date']) ?></span><?php endif; ?><?php if ($big['type'] === 'blog' && $big['minutes']): ?><span><?= (int)$big['minutes'] ?> мин чтения</span><?php endif; ?></div>
        <h3><?= $h($big['name']) ?></h3>
        <?php if ($big['text']): ?><p><?= $h($big['text']) ?></p><?php endif; ?>
      </a>
      <div class="dl-art-list">
        <?php foreach ($arResult['materials']['list'] as $m): ?>
        <a class="dl-art-sm" href="<?= $h($m['url']) ?>">
          <div class="dl-ph dl-ph--light"><?= profequip_LandingPicture($m['pic'], [160, 320], ['alt' => $m['name'], 'sizes' => '128px']) ?><?= $art($m['type'] === 'news' ? 'doc' : 'washer') ?></div>
          <div><div class="dl-art-meta"><span class="dl-tag<?= $m['type'] === 'news' ? ' dl-tag--news' : '' ?>"><?= $m['type'] === 'news' ? 'Новость' : 'Статья' ?></span><?php if ($m['date']): ?><span class="dl-num"><?= $h($m['date']) ?></span><?php endif; ?></div><h3><?= $h($m['name']) ?></h3></div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($has['faq']): $sec = $S['faq']; ?>
<!-- 09b вопросы и ответы -->
<section class="dl-sec" id="faq"<?= $ord('faq') ?>>
  <div class="dl-container">
    <div class="dl-sec__head">
      <div>
        <h2 class="dl-h2"><?= $h($sec['name']) ?></h2>
        <?php if ($sec['lead']): ?><p class="dl-lead"><?= $text($sec['lead']) ?></p><?php endif; ?>
      </div>
    </div>
    <div class="dl-faq">
      <?php foreach ($arResult['faq'] as $qa): ?>
      <details>
        <summary><?= $h($qa['q']) ?></summary>
        <p><?= $text($qa['a']) ?></p>
      </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($has['final']): $sec = $S['final']; $c = $arResult['contacts']; ?>
<!-- 10 финальная форма -->
<section class="dl-sec dl-final" id="final"<?= $ord('final') ?>>
  <div class="dl-container dl-final__grid">
    <div>
      <h2 class="dl-h2"><?= $h($sec['name']) ?></h2>
      <?php if ($sec['lead']): ?><p class="dl-lead"><?= $h($sec['lead']) ?></p><?php endif; ?>
      <div class="dl-contacts">
        <?php if ($c['phone']): ?><a href="tel:<?= $h(preg_replace('/[^\d+]/', '', $c['phone'])) ?>" class="dl-num"><small>Телефон</small><?= $h($c['phone']) ?></a><?php endif; ?>
        <?php if ($c['email']): ?><a href="mailto:<?= $h($c['email']) ?>"><small>E-mail</small><?= $h($c['email']) ?></a><?php endif; ?>
        <?php if ($c['address']): ?><div class="dl-addr"><small>Головной офис</small><?= $h($c['address']) ?></div><?php endif; ?>
      </div>
    </div>
    <div class="dl-fcard">
      <?= $formOpen($sec['source']) ?>
        <div class="dl-fbody">
          <?= $formHidden($sec['source']) ?>
          <?php if ($sec['items']): ?>
          <fieldset class="dl-topics">
            <legend>Тема обращения</legend>
            <div class="dl-chips">
              <?php foreach ($sec['items'] as $i => $t): ?>
              <label class="dl-chip"><input type="radio" name="<?= $h($N['land_topic'] ?? '') ?>" value="<?= $h($t['value']) ?>" data-topic="<?= $h($t['desc']) ?>"<?= $i === 0 ? ' checked' : '' ?>><span><?= $h($t['value']) ?></span></label>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <?php endif; ?>
          <div class="dl-fgrid">
            <div class="dl-field dl-full"><label for="dl-f1">Имя</label><input id="dl-f1" name="<?= $h($N['land_name'] ?? '') ?>" autocomplete="name"></div>
            <div class="dl-field"><label for="dl-f2">Телефон</label><input id="dl-f2" name="<?= $h($N['land_phone'] ?? '') ?>" type="tel" inputmode="tel" autocomplete="tel" data-kind="phone" required></div>
            <div class="dl-field"><label for="dl-f3">E-mail</label><input id="dl-f3" name="<?= $h($N['land_email'] ?? '') ?>" type="email" autocomplete="email"></div>
            <div class="dl-field dl-full"><label for="dl-f4">Комментарий</label><textarea id="dl-f4" name="<?= $h($N['land_comment'] ?? '') ?>" placeholder="Коротко о задаче — необязательно"></textarea></div>
            <label class="dl-file dl-full"><?= $ico('i-clip') ?><span data-fname>Прикрепить файл</span><em>до 25 МБ</em><input type="file" multiple data-max="25" data-files-to=".dl-fileslots"></label>
            <div class="dl-fileslots" hidden><?php for ($k = 1; $k <= 3; $k++): ?><input type="file" name="<?= $h($N['land_file_' . $k] ?? '') ?>"><?php endfor; ?></div>
            <div class="dl-fsend dl-full"><button class="dl-btn" type="submit"><?= $h($sec['btn'] ?: 'Отправить') ?></button><?= $consent ?></div>
          </div>
        </div>
        <?= $okBox($sec['ok'] ?: 'Запрос отправлен.') ?>
      </form>
    </div>
  </div>
</section>
<?php endif; ?>

</div>
