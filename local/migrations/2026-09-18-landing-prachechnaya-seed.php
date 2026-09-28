<?php
/**
 * Стартовое содержимое лендинга направления «Прачечная» (/prachechnaya/) —
 * тексты и структура из утверждённого макета prachechnaya.html.
 * Структура (свойства лендинга (вкладка «Лендинг») элемента направления) — в 2026-09-18-landing-direction-structure.php.
 *
 * Пишет содержимое в свойства элемента направления prachechnaya. Свойство, в котором
 * уже что-то есть, не трогается (правки редакторов не затираются при повторном запуске).
 *
 * Связи с существующим контентом ищутся по CODE (не по ID — ID на test3 и
 * на проде расходятся): проекты (инфоблок projects), статьи и новости.
 * Бренды и «Проекты» на самом элементе направления редактор выбирает в админке —
 * если поле пусто, проставляем стартовый набор.
 */

// Всё внутри функции: миграции подключаются через require из run.php, и переменные
// верхнего уровня (например $name, $file) затирают переменные раннера.
call_user_func(static function () {

global $DB;

CModule::IncludeModule('iblock');

$ibId = static function (string $code): int {
    $ib = \CIBlock::GetList([], ['CODE' => $code, 'CHECK_PERMISSIONS' => 'N'])->Fetch();
    if (!$ib) {
        throw new \RuntimeException("Не найден инфоблок $code — сначала 2026-09-18-landing-direction-structure.php");
    }
    return (int)$ib['ID'];
};

/** Копия строки b_file, указывающая на тот же физический файл (не копируем файл — upload/iblock смонтирован read-only) */
$cloneFile = static function (string $subdir, string $fileName) use ($DB): int {
    $abs = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . $subdir . '/' . $fileName;
    if (!is_file($abs)) {
        return 0;
    }
    $src = $DB->Query("SELECT * FROM b_file WHERE SUBDIR = '" . $DB->ForSql($subdir) . "' AND FILE_NAME = '" . $DB->ForSql($fileName) . "' ORDER BY ID LIMIT 1")->Fetch();
    if (!$src) {
        return 0;
    }
    $DB->Query(
        "INSERT INTO b_file (TIMESTAMP_X, MODULE_ID, HEIGHT, WIDTH, FILE_SIZE, CONTENT_TYPE, SUBDIR, FILE_NAME, ORIGINAL_NAME, DESCRIPTION, HANDLER_ID, EXTERNAL_ID) VALUES (NOW(), '"
        . $DB->ForSql($src['MODULE_ID']) . "', " . (int)$src['HEIGHT'] . ', ' . (int)$src['WIDTH'] . ', ' . (int)$src['FILE_SIZE'] . ", '"
        . $DB->ForSql($src['CONTENT_TYPE']) . "', '" . $DB->ForSql($subdir) . "', '" . $DB->ForSql($fileName) . "', '" . $DB->ForSql($src['ORIGINAL_NAME']) . "', '', NULL, '" . md5(uniqid('', true)) . "')"
    );
    return (int)$DB->LastID();
};

// ---------------------------------------------------------------------------
// содержимое лендинга → свойства элемента направления
// ---------------------------------------------------------------------------

$ibDirection = $ibId('prachechnaya');
$dirEl = \CIBlockElement::GetList([], ['IBLOCK_ID' => $ibDirection, 'CODE' => 'prachechnaya'], false, ['nTopCount' => 1], ['ID'])->Fetch();
if (!$dirEl) {
    throw new \RuntimeException('Не найден элемент направления prachechnaya');
}
$dirId = (int)$dirEl['ID'];

/** Заполнено ли свойство элемента направления */
$isFilled = static function (string $code) use ($ibDirection, $dirId): bool {
    $rs = \CIBlockElement::GetProperty($ibDirection, $dirId, [], ['CODE' => $code]);
    while ($r = $rs->Fetch()) {
        if ($r['VALUE'] !== '' && $r['VALUE'] !== null) {
            return true;
        }
    }
    return false;
};

/** Записывает свойство, если оно пустое; $json — значение пишется как JSON (свойства лендинга), иначе как есть */
$saveProp = static function (string $code, $value, bool $json = true) use ($ibDirection, $dirId, $isFilled): void {
    if ($isFilled($code)) {
        echo "$code: уже заполнено, пропускаю\n";
        return;
    }
    if ($json) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    \CIBlockElement::SetPropertyValuesEx($dirId, $ibDirection, [$code => $value]);
    echo "$code: записано\n";
};

/** [['VALUE' => .., 'DESCRIPTION' => ..], ..] → [['v' => .., 'd' => ..], ..] */
$pairs = static fn(array $rows): array => array_map(static fn($r) => ['v' => $r['VALUE'], 'd' => $r['DESCRIPTION']], $rows);

$blogIb = $ibId('blog');
$newsIb = $ibId('news');
$codeToId = static function (int $iblockId, array $codes): array {
    $ids = [];
    foreach ($codes as $code) {
        $row = \CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code], false, ['nTopCount' => 1], ['ID'])->Fetch();
        if ($row) {
            $ids[] = (int)$row['ID'];
        } else {
            echo "  ! не найден элемент с CODE=$code в инфоблоке $iblockId — пропущен\n";
        }
    }
    return $ids;
};

// --- блоки страницы ---------------------------------------------------------
$blogIds = $codeToId($blogIb, [
    'sekrety-uspeshnogo-vedeniya-prachechnoj-sovety-ekspertov',
    'kakie-vidy-prachechnich-bivayut',
    'oborudovanie-dlya-prachechnoj-tolon-stabilnoe-kachestvo-i-postavki-v-rossiyu',
]);
$newsIds = $codeToId($newsIb, ['novyy-brend-v-kataloge-prachechnogo-oborudovaniya']);

$consent = 'Нажимая кнопку, вы соглашаетесь с политикой обработки персональных данных';

$sections = [
    [10, 'anchors', 'Якорное меню', '', [
        'BTN_TEXT' => 'Получить консультацию',
        'SOURCE' => 'Якорное меню · консультация',
    ]],
    [20, 'projects', 'Реализованные прачечные',
        'От прачечной городского отеля до индустриальной линии на 15 тонн белья в смену. Каждый проект — расчёт, поставка и запуск силами одной команды.', [
        'ANCHOR' => 'Проекты', 'LINK_TEXT' => 'Все проекты', 'LINK_URL' => '/portfolio/',
    ]],
    [30, 'consult', 'Подберём решение под ваш объект',
        'Расскажите о задаче — инженер направления рассчитает состав прачечной и покажет, как её решали на похожем проекте.', [
        'BTN_TEXT' => 'Получить консультацию',
        'OK_TEXT' => 'Заявка отправлена. Инженер направления свяжется с вами в рабочее время.',
        'SOURCE' => 'Блок «Проекты» · консультация',
    ]],
    [40, 'stock', 'Оборудование в наличии',
        'Не нужно ждать поставку — оборудование уже на нашем складе. Отгрузим в согласованный срок, поможем с монтажом и запуском.', [
        'ANCHOR' => 'В наличии',
        'LINK_TEXT' => 'Смотреть всё в наличии', 'LINK_URL' => '/product-category/prachechnoe-oborudovanie-v-nalichii/',
        'BTN_TEXT' => 'Получить КП', 'SECTION_CODE' => 'prachechnoe-oborudovanie', 'LIMIT' => 6,
        'PRICE_NOTE' => 'с НДС, склад в Москве',
    ]],
    [50, 'brands', 'Наши бренды',
        "Ведущие мировые бренды и собственные решения ПРОФЭКВИП.\nJENSEN, TOLON, DANUBE, IMESA, PONY, HAWO и другие. Официальные поставки, гарантия, сервис и оригинальные запчасти.", [
        'ANCHOR' => 'Бренды', 'LINK_TEXT' => 'Все бренды', 'LINK_URL' => '/brends/',
    ]],
    [60, 'parts', 'Запчасти для прачечного оборудования',
        'Оригинальные запчасти и комплектующие для профессионального прачечного оборудования.', [
        'ANCHOR' => 'Запчасти',
        'BTN_TEXT' => 'Подобрать запчасть',
        'BTN2_TEXT' => 'Перейти в каталог запчастей', 'BTN2_URL' => '/product-category/zapasnye-chasti-dlya-prachechnogo-oborudovaniya/',
        'FORM_TITLE' => 'Подбор запчасти', 'FORM_TEXT' => 'Ответим с наличием и сроком поставки.',
        'OK_TEXT' => 'Запрос на подбор отправлен. Специалист по запчастям свяжется с вами и уточнит детали.',
        'SOURCE' => 'Запчасти · подбор',
        'ITEMS' => [
            ['VALUE' => 'Ходовые позиции хранятся на складе — простой прачечной не затянется.', 'DESCRIPTION' => 'Запчасти в наличии'],
            ['VALUE' => 'Для оборудования ведущих производителей, с сохранением гарантии.', 'DESCRIPTION' => 'Оригинальные комплектующие'],
            ['VALUE' => 'По модели оборудования, артикулу или фотографии детали.', 'DESCRIPTION' => 'Поможем подобрать'],
        ],
    ]],
    [70, 'solutions', 'Решения для прачечных',
        'Состав прачечной зависит от объекта: объёма и типа белья, санитарных требований и режима работы. Выберите свой — покажем, с чего начинается проект.', [
        'ANCHOR' => 'Решения',
    ]],
    [80, 'calc', 'Не знаете, какое оборудование нужно?',
        'Инженер уточнит объём белья, тип объекта и режим работы, рассчитает состав прачечной и сориентирует по бюджету. Консультация бесплатная.', [
        'BTN_TEXT' => 'Получить консультацию',
        'OK_TEXT' => 'Заявка отправлена. Инженер направления свяжется с вами в рабочее время.',
        'SOURCE' => 'Решения · консультация по составу оборудования',
    ]],
    [90, 'design', 'Проектирование и комплексное оснащение',
        'Собственное проектное бюро и сервисная служба ведут прачечную от первого расчёта до регулярного обслуживания.', [
        'ANCHOR' => 'Проектирование',
    ]],
    [100, 'tz', 'Уже есть план или ТЗ?',
        'Пришлите проект — специалисты ПРОФЭКВИП предложат состав оборудования и подготовят коммерческое предложение.', [
        'BTN_TEXT' => 'Отправить ТЗ / план помещения',
        'OK_TEXT' => 'Файлы получены. Специалист изучит проект и вернётся с составом оборудования и КП.',
        'SOURCE' => 'Проектирование · ТЗ / план помещения',
        'ITEMS' => [
            ['VALUE' => '985+', 'DESCRIPTION' => 'реализованных проектов компании'],
            ['VALUE' => '11', 'DESCRIPTION' => 'региональных представительств'],
        ],
    ]],
    [110, 'guarantee', 'Круговая гарантия ПРОФЭКВИП',
        'Качество стирки складывается из четырёх составляющих. Мы отвечаем за все сразу — искать виноватого между подрядчиками не придётся.', [
        'ANCHOR' => 'Гарантия',
    ]],
    [120, 'expert', 'Экспертный центр',
        'Практика, технологии и новости профессиональной прачечной.', [
        'ANCHOR' => 'Статьи',
        'LINKS' => [
            ['VALUE' => '/blog/', 'DESCRIPTION' => 'Все статьи'],
            ['VALUE' => '/novosti/', 'DESCRIPTION' => 'Все новости'],
        ],
        'MATERIALS_BLOG' => $blogIds,
        'MATERIALS_NEWS' => $newsIds,
    ]],
    [130, 'final', 'Обсудим вашу прачечную?',
        'От отдельной единицы оборудования до комплексного оснащения объекта.', [
        'ANCHOR' => 'Контакты',
        'BTN_TEXT' => 'Отправить запрос',
        'OK_TEXT' => 'Запрос отправлен. Менеджер направления свяжется с вами в рабочее время.',
        'SOURCE' => 'Финальная форма',
        'ITEMS' => [
            ['VALUE' => 'Купить оборудование', 'DESCRIPTION' => 'buy'],
            ['VALUE' => 'Рассчитать прачечную', 'DESCRIPTION' => 'calc'],
            ['VALUE' => 'Получить КП', 'DESCRIPTION' => 'kp'],
            ['VALUE' => 'Обсудить проект', 'DESCRIPTION' => 'project'],
            ['VALUE' => 'Сервис / запчасти', 'DESCRIPTION' => 'service'],
        ],
    ]],
];

$oldToNew = [
    'ANCHOR' => 'anchor', 'LINK_TEXT' => 'link_text', 'LINK_URL' => 'link_url', 'BTN_TEXT' => 'btn',
    'BTN2_TEXT' => 'btn2_text', 'BTN2_URL' => 'btn2_url', 'FORM_TITLE' => 'form_title', 'FORM_TEXT' => 'form_text',
    'OK_TEXT' => 'ok', 'SOURCE' => 'source', 'SECTION_CODE' => 'section_code', 'LIMIT' => 'limit', 'PRICE_NOTE' => 'price_note',
];
$blocks = [];
foreach ($sections as [$sort, $block, $name, $lead, $props]) {
    $row = ['active' => 1, 'title' => $name, 'lead' => $lead];
    foreach ($props as $k => $v) {
        if (isset($oldToNew[$k])) {
            $row[$oldToNew[$k]] = $v;
        } elseif ($k === 'ITEMS') {
            $row['items'] = $pairs($v);
        } elseif ($k === 'LINKS') {
            $row['links'] = $pairs($v);
        }
    }
    $blocks[$block] = $row;
}
$saveProp('LAND_BLOCKS', $blocks);
$saveProp('LAND_MAT_BLOG', $blogIds, false);
$saveProp('LAND_MAT_NEWS', $newsIds, false);

// --- слайды -----------------------------------------------------------------
$slides = [
    [10, 'Прачечные и оборудование — от задачи до запуска',
        'Проектируем, поставляем, монтируем и обслуживаем прачечные для отелей, клиник и производств. За результат на всех этапах отвечает одна команда.',
        ['KICKER' => 'Профессиональные прачечные', 'TAB' => 'Прачечная под ключ',
         'BTN1_TEXT' => 'Получить консультацию', 'BTN1_TARGET' => '#consult', 'BTN1_SOURCE' => 'Первый экран · консультация',
         'BTN2_TEXT' => 'Оборудование в наличии', 'BTN2_TARGET' => '#stock', 'ART' => 'tunnel']],
    [20, 'Оборудование на складе — без ожидания поставки',
        'Стирально-отжимные, сушильные и гладильные машины ведущих производителей с оперативной отгрузкой. Подберём аналог, если нужной модели нет в наличии.',
        ['KICKER' => 'Складской запас', 'TAB' => 'Складской запас',
         'BTN1_TEXT' => 'Получить консультацию', 'BTN1_TARGET' => '#consult', 'BTN1_SOURCE' => 'Первый экран · слайд «Склад»',
         'BTN2_TEXT' => 'Смотреть наличие', 'BTN2_TARGET' => '#stock', 'ART' => 'washer']],
    [30, 'Сервис, который знает ваше оборудование',
        'Монтаж, пусконаладка, гарантийное и постгарантийное обслуживание силами собственной сервисной службы. Ходовые запчасти — на складе.',
        ['KICKER' => 'Сервис и запчасти', 'TAB' => 'Сервис и запчасти',
         'BTN1_TEXT' => 'Получить консультацию', 'BTN1_TARGET' => '#consult', 'BTN1_SOURCE' => 'Первый экран · слайд «Сервис»',
         'BTN2_TEXT' => 'Подобрать запчасть', 'BTN2_TARGET' => '#parts', 'ART' => 'parts']],
];
$rows = [];
foreach ($slides as [$sort, $name, $text, $p]) {
    $rows[] = [
        'title' => $name, 'text' => $text, 'kicker' => $p['KICKER'], 'tab' => $p['TAB'],
        'btn1_text' => $p['BTN1_TEXT'], 'btn1_target' => $p['BTN1_TARGET'], 'btn1_source' => $p['BTN1_SOURCE'],
        'btn2_text' => $p['BTN2_TEXT'], 'btn2_target' => $p['BTN2_TARGET'], 'art' => $p['ART'],
    ];
}
$saveProp('LAND_SLIDES', $rows);

// --- преимущества -----------------------------------------------------------
$adv = [
    [10, 'Официальные поставки', 'shield'],
    [20, 'Складской запас', 'box'],
    [30, 'Своё проектное бюро', 'compass'],
    [40, 'Своя сервисная служба', 'wrench'],
];
$saveProp('LAND_ADVANTAGES', array_map(static fn($a) => ['title' => $a[1], 'icon' => $a[2]], $adv));

// --- решения ----------------------------------------------------------------
$sol = [
    [10, 'Гостиничная прачечная', 'Отели, апарт-отели, санатории', 'ironer',
        [['от 30 номеров', 'Масштаб'], ['стирально-отжимные, сушильные машины, каток', 'Основа'], ['дозирование химии, тележки, стеллажи', 'Дополнительно']],
        '/gotovye-resheniya/prachechnaya-v-gostinitse/', 'Подробнее о гостиничной прачечной'],
    [20, 'Коммерческая и промышленная', 'Фабрики-прачечные, сервис для нескольких объектов', 'tunnel',
        [['от 1 т белья в смену', 'Масштаб'], ['туннельные линии, прессы, сушильные машины', 'Основа'], ['автоматизация, учёт белья', 'Дополнительно']],
        '/gotovye-resheniya/promyshlennaya-prachechnaya/', 'Подробнее о промышленной прачечной'],
    [30, 'Медицинские и санитарные объекты', 'Больницы, клиники, пансионаты', 'plan',
        [['от стационара на 50 коек', 'Масштаб'], ['барьерные стиральные машины', 'Основа'], ['дезинфицирующая химия, санпропускник', 'Дополнительно']],
        '/gotovye-resheniya/prachechnaya-v-bolnitse/', 'Подробнее о прачечной для медучреждения'],
    [40, 'Прачечная предприятия', 'Спецодежда и униформа персонала', 'washer',
        [['от 100 сотрудников', 'Масштаб'], ['стирально-отжимные и сушильные машины', 'Основа'], ['программы для сложных загрязнений', 'Дополнительно']],
        '/gotovye-resheniya/prachechnaya-na-predpriyatii/', 'Подробнее о прачечной предприятия'],
    [50, 'Химчистка и аквачистка', 'Сервис для частных клиентов и отелей', 'dryer',
        [['от точки приёма до цеха', 'Масштаб'], ['машины аквачистки, финишное оборудование', 'Основа'], ['пятновыводные станции, химия', 'Дополнительно']],
        '/gotovye-resheniya/akvachistka/', 'Подробнее об аквачистке'],
];
$rows = [];
foreach ($sol as [$sort, $name, $sub, $art, $specs, $url, $linkText]) {
    $rows[] = [
        'title' => $name, 'sub' => $sub, 'art' => $art, 'url' => $url, 'link_text' => $linkText,
        'specs' => array_map(static fn($s) => ['v' => $s[0], 'd' => $s[1]], $specs),
    ];
}
$saveProp('LAND_SOLUTIONS', $rows);

// --- этапы ------------------------------------------------------------------
$steps = [
    [10, 'Анализ задачи', 'Объём и тип белья, режим работы, санитарные нормы и стандарты сети. На выходе — требуемая производительность и ориентир по бюджету.', 'Результат: техническое задание'],
    [20, 'Проектирование', 'Технологический проект собственного бюро: расстановка оборудования, чистая и грязная зоны, нагрузки на электричество, воду, вентиляцию и канализацию.', 'Результат: проект и задания смежникам'],
    [30, 'Подбор оборудования', 'Спецификация под задачу с вариантами по бюджету. Если позиция выходит за рамки сметы — предложим аналог из каталога и покажем разницу в стоимости владения.', 'Результат: спецификация и КП'],
    [40, 'Поставка и монтаж', 'Логистика с заводов производителей и с нашего склада, погрузка и разгрузка на объекте. Монтаж выполняют сертифицированные инженеры собственной сервисной службы.', 'Результат: смонтированная прачечная'],
    [50, 'Запуск и обучение', 'Подключение к сетям 220/380 В, холодной и горячей воде, тестовые циклы и калибровка. Вводное обучение персонала работе с оборудованием и программами стирки.', 'Результат: прачечная в работе'],
    [60, 'Сервис', 'Гарантийное и постгарантийное обслуживание, плановые ТО по договору, оригинальные запчасти со склада.', 'Результат: стабильная работа без простоев'],
];
$saveProp('LAND_STEPS', array_map(static fn($s) => ['title' => $s[1], 'text' => $s[2], 'result' => $s[3]], $steps));

// --- круговая гарантия (+ круглые фото — те же файлы, что уже лежат в upload/iblock) ---
$guar = [
    [10, 'Оборудование', 'Стирка, сушка и финиш: от одной машины до туннельной линии', '/product-category/prachechnoe-oborudovanie/', 'Каталог оборудования', 'washer', ['iblock/81e', 'dcdeermymqbe100q4j8d4upv7lvuoobb.jpg']],
    [20, 'Химия', 'Средства и дозирующие системы, подобранные под программы ваших машин', '/himiya/', 'Направление «Химия»', 'parts', ['iblock/b55', '56b3x7ojdkjwfvjeb59z3sdfv7ocf9b8.jpg']],
    [30, 'Текстиль', 'Бельё, полотенца и халаты, рассчитанные на промышленную стирку', '/tekstil/', 'Направление «Текстиль»', 'doc', ['iblock/042', 'y421bw70ptmfltx0lg40i8svc0t5lk3r.jpg']],
    [40, 'Сервис', 'Плановое ТО, ремонт и оригинальные запчасти со склада', '/services/', 'Сервисная служба', 'wrench', null],
];
$rows = [];
foreach ($guar as [$sort, $name, $text, $url, $label, $icon, $file]) {
    $fileId = $file ? $cloneFile($file[0], $file[1]) : 0;
    if ($file && !$fileId) {
        echo "  ! файл {$file[0]}/{$file[1]} не найден — «$name» без фото\n";
    }
    $rows[] = ['title' => $name, 'text' => $text, 'url' => $url, 'label' => $label, 'icon' => $icon, 'pic' => $fileId];
}
$saveProp('LAND_GUARANTEE', $rows);

// ---------------------------------------------------------------------------
// карточки проектов + выбор проектов/брендов на элементе направления
// ---------------------------------------------------------------------------

$ibProjects = $ibId('projects');
$cards = [
    'mantera-supreme-seaside-5-2' => [
        'meta' => ['Курорт 5*', 'Сочи', '400 номеров'],
        'text' => 'Прачечный комплекс премиального курорта: стирка, сушка, аквачистка, химическая чистка, глажение, финишная обработка и упаковка — полный цикл внутри объекта.',
        'scope' => ['TOLON', 'IMESA', 'PONY', 'SOVRANA', 'HAWO'],
    ],
    'otel-yalta-inturist-4-industrialnaya-prachechnaya-yalta-2006-2014' => [
        'meta' => ['Отель 4*', 'Ялта', '1140 номеров'],
        'text' => 'Индустриальная прачечная под ключ мощностью свыше 15 тонн белья в смену на немецкой технологии стирки JENSEN: от проектирования до сервиса и поставки химии.',
        'scope' => ['Проектирование', 'Поставка', 'Монтаж', 'Обучение', 'Сервис'],
    ],
    'otel-the-local-5-zvezd-groznyj-2018' => [
        'meta' => ['Отель 5*', 'Грозный', '130 номеров'],
        'text' => 'Прачечная отеля в составе комплексного проекта вместе со всеми зонами питания: стирка и сушка IMESA, финиш PONY.',
        'scope' => ['Комплексное оснащение', 'Проектирование', 'Монтаж', 'Сервис'],
    ],
    'industrialnaya-prachechnaya-x-moskva-2017' => [
        'meta' => ['Промышленная прачечная', 'Москва', '300 тонн в месяц'],
        'text' => 'Дооснащение индустриальной прачечной и регулярные поставки профессиональной химии: промышленное оборудование JENSEN и дозирующая система Christeyns.',
        'scope' => ['JENSEN', 'Christeyns', 'Поставка химии'],
    ],
    'mnogofunktsionalnyj-kompleks-burny-vladivostok-mys-burnyj-2022' => [
        'meta' => ['Многофункциональный комплекс', 'Владивосток', '2022'],
        'text' => 'Профессиональная прачечная комплекса: консультирование, подбор, поставка, монтаж и пусконаладочные работы на оборудовании IMESA.',
        'scope' => ['IMESA', 'Подбор', 'Поставка', 'Пусконаладка'],
    ],
];

$projectIds = [];
foreach ($cards as $code => $card) {
    $row = \CIBlockElement::GetList([], ['IBLOCK_ID' => $ibProjects, 'CODE' => $code], false, ['nTopCount' => 1], ['ID'])->Fetch();
    if (!$row) {
        echo "  ! проект $code не найден — карточка пропущена\n";
        continue;
    }
    $projectIds[] = (int)$row['ID'];
    // Не затираем то, что уже заполнил редактор
    $cur = \CIBlockElement::GetProperty($ibProjects, (int)$row['ID'], [], ['CODE' => 'CARD_TEXT'])->Fetch();
    if (!empty($cur['VALUE'])) {
        continue;
    }
    \CIBlockElement::SetPropertyValuesEx((int)$row['ID'], $ibProjects, [
        'CARD_META' => $card['meta'],
        'CARD_TEXT' => $card['text'],
        'CARD_SCOPE' => $card['scope'],
    ]);
}

$curProjects = \CIBlockElement::GetProperty($ibDirection, $dirId, [], ['CODE' => 'PROJECTS']);
$hasProjects = false;
while ($p = $curProjects->Fetch()) {
    if (!empty($p['VALUE'])) {
        $hasProjects = true;
    }
}
if (!$hasProjects && $projectIds) {
    \CIBlockElement::SetPropertyValuesEx($dirId, $ibDirection, ['PROJECTS' => $projectIds]);
    echo "Направление «Прачечная»: выбрано проектов — " . count($projectIds) . "\n";
} else {
    echo "Направление «Прачечная»: проекты уже выбраны, пропускаю\n";
}

// Проверка запросом к базе
foreach (['LAND_BLOCKS', 'LAND_SLIDES', 'LAND_ADVANTAGES', 'LAND_SOLUTIONS', 'LAND_STEPS', 'LAND_GUARANTEE'] as $code) {
    if (!$isFilled($code)) {
        throw new \RuntimeException("Свойство $code элемента направления пусто после заливки");
    }
}
echo "Стартовое содержимое лендинга «Прачечная» готово.\n";

});

return true;
