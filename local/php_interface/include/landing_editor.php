<?php
/**
 * Редактор содержимого лендинга направления — прямо в карточке элемента направления
 * (инфоблок направления, например «Прачечная (направление)»).
 *
 * Тип свойства «Лендинг: список карточек» (USER_TYPE = ProfequipLandingCards): в админке рисует
 * форму со строками и полями, в базе хранит JSON. Какие поля у какого свойства — profequip_LandingSchema().
 * Читает данные компонент local/components/custom/direction.landing через profequip_LandingDecode().
 *
 * Подключается из init.php. Новое направление: profequip_LandingEnsureDirectionProps($iblockId).
 */

use Bitrix\Main\Loader;

/**
 * Описание свойств лендинга. Ключ — CODE свойства инфоблока направления.
 *  mode 'list'   — повторяющиеся карточки (добавить / удалить / переставить), max — предел
 *  mode 'groups' — фиксированный набор блоков страницы, у каждого свой набор полей и флажок «Показывать»
 * Поле: code, label, type (text|textarea|number|select|check|pairs|image), hint, options, labels (pairs), max (pairs)
 */
function profequip_LandingSchema(): array
{
    static $schema = null;
    if ($schema !== null) {
        return $schema;
    }

    $art = [
        'washer' => 'Стиральная машина', 'dryer' => 'Сушильная машина', 'ironer' => 'Гладильный каток',
        'tunnel' => 'Туннельная линия', 'parts' => 'Запчасти', 'table' => 'Пятновыводной стол',
        'doc' => 'Документ', 'plan' => 'План помещения',
        'towels' => 'Стопка полотенец', 'roll' => 'Рулон ткани', 'chem' => 'Канистра химии', 'sofa' => 'Мебель (диван)',
    ];
    $icons = [
        'shield' => 'Щит с галочкой', 'box' => 'Коробка (склад)', 'compass' => 'Циркуль (проектирование)',
        'wrench' => 'Гаечный ключ (сервис)', 'layers' => 'Слои', 'chat' => 'Чат', 'check' => 'Галочка',
    ];

    $f = static fn(string $code, string $label, string $type = 'text', array $extra = []): array
        => ['code' => $code, 'label' => $label, 'type' => $type] + $extra;

    // поля блоков страницы; подписи и подсказки у отдельных блоков переопределяются вторым аргументом
    $b = [
        'title' => static fn(array $o = []) => $o + $f('title', 'Заголовок блока (H2)'),
        'lead' => static fn(array $o = []) => $o + $f('lead', 'Вводный текст под заголовком', 'textarea', ['rows' => 3]),
        'anchor' => static fn(array $o = []) => $o + $f('anchor', 'Пункт якорного меню', 'text', ['hint' => 'Подпись в липком меню под шапкой. Пусто — блока в меню нет.']),
        'link_text' => static fn(array $o = []) => $o + $f('link_text', 'Ссылка в заголовке: текст', 'text', ['hint' => 'Например «Все проекты»']),
        'link_url' => static fn(array $o = []) => $o + $f('link_url', 'Ссылка в заголовке: адрес'),
        'btn' => static fn(array $o = []) => $o + $f('btn', 'Текст главной кнопки'),
        'btn2_text' => static fn(array $o = []) => $o + $f('btn2_text', 'Вторая кнопка: текст'),
        'btn2_url' => static fn(array $o = []) => $o + $f('btn2_url', 'Вторая кнопка: адрес'),
        'form_title' => static fn(array $o = []) => $o + $f('form_title', 'Заголовок формы'),
        'form_text' => static fn(array $o = []) => $o + $f('form_text', 'Подпись под заголовком формы'),
        'ok' => static fn(array $o = []) => $o + $f('ok', 'Сообщение после отправки формы', 'textarea', ['rows' => 2]),
        'source' => static fn(array $o = []) => $o + $f('source', 'Источник лида', 'text', ['hint' => 'Уходит в CRM и в цель Яндекс.Метрики (имя цели = это значение)']),
        'section_code' => static fn(array $o = []) => $o + $f('section_code', 'Раздел каталога (символьный код)', 'text', ['hint' => 'Товары с флагом «В наличии» берутся из этого раздела и подразделов']),
        'limit' => static fn(array $o = []) => $o + $f('limit', 'Сколько карточек показывать', 'number', ['hint' => 'По умолчанию 6']),
        'price_note' => static fn(array $o = []) => $o + $f('price_note', 'Подпись под ценой', 'text', ['hint' => 'Например «с НДС, склад в Москве»']),
        'items' => static fn(array $o = []) => $o + $f('items', 'Список', 'pairs', ['labels' => ['Текст', 'Подпись'], 'max' => 12]),
        'links' => static fn(array $o = []) => $o + $f('links', 'Дополнительные ссылки', 'pairs', ['labels' => ['Адрес', 'Подпись'], 'max' => 4]),
    ];

    $groups = [
        'anchors' => ['Якорное меню (кнопка справа)', [$b['btn'](['hint' => 'Кнопка в липком меню']), $b['source']()]],
        'projects' => ['Реализованные проекты (сами проекты — в свойстве «Портфолио»)', [$b['title'](), $b['lead'](), $b['anchor'](), $b['link_text'](), $b['link_url']()]],
        'consult' => ['Форма «Подберём решение» (под проектами)', [$b['title'](), $b['lead'](), $b['btn'](), $b['ok'](), $b['source']()]],
        'task' => ['Форма «С какой задачей вы пришли» (вместо «Подберём решение» — с чипами задач, под проектами)', [
            $b['title'](), $b['lead'](), $b['anchor'](), $b['btn'](), $b['ok'](), $b['source'](),
            $f('tasks', 'Задачи (чипы в форме)', 'pairs', ['labels' => ['Название задачи', '—'], 'max' => 6]),
        ]],
        'stock' => ['Оборудование в наличии — вкладка «В наличии» (флажок «В наличии» ставится у товара)', [$b['title'](), $b['lead'](), $b['anchor'](), $b['link_text'](), $b['link_url'](), $b['btn'](), $b['section_code'](), $b['limit'](), $b['price_note']()]],
        'brands' => ['Наши бренды (сами бренды — в свойстве «Производители»)', [
            $b['title'](), $b['lead'](), $b['anchor'](), $b['link_text'](), $b['link_url'](),
            $f('featured_pic', 'Свой бренд: цветной логотип', 'image', ['hint' => 'Показывается справа от подзаголовка, отдельно от чёрно-белой ленты — логотип остаётся цветным.']),
            $f('featured_name', 'Свой бренд: название', 'text', ['hint' => 'Например FERMEST. Показывается, если логотип ещё не загружен.']),
            $f('featured_url', 'Свой бренд: ссылка', 'text', ['hint' => 'Необязательно — страница бренда.']),
            $f('featured_label', 'Подпись под названием', 'text', ['hint' => 'По умолчанию «Собственный бренд». Для партнёрского (не ПРОФЭКВИП) бренда впишите свою подпись, например «Ключевой партнёр».']),
        ]],
        'own' => ['Собственное производство ПРОФЭКВИП (тёмная секция; клиент ещё уточняет формулировки — см. открытый вопрос)', [
            $b['title'](), $b['lead'](),
            $b['items'](['label' => 'Тезисы', 'labels' => ['Текст тезиса', 'Заголовок тезиса'], 'max' => 3]),
            $b['btn'](['hint' => 'Например «Запросить образцы» — ведёт к финальной форме']),
            $b['btn2_text'](['hint' => 'Например «Смотреть линейку»']), $b['btn2_url'](),
            $b['source'](), $f('pic', 'Фото или видео', 'image'),
        ]],
        'fm' => ['Fresco Maggiore — акцентная секция (между лентой брендов и индивидуальным брендированием)', [
            $b['title'](), $b['lead'](),
            $b['items'](['label' => 'Строка тезисов', 'labels' => ['Текст тезиса', '—'], 'max' => 4]),
            $b['link_text'](['hint' => 'Например «Fresco Maggiore»']), $b['link_url'](['hint' => 'Страница бренда']),
            $f('pic', 'Фото', 'image'),
        ]],
        'brand_custom' => ['Индивидуальное брендирование (блок после ленты брендов)', [
            $b['title'](), $b['lead'](),
            $b['items'](['label' => 'Тезисы', 'labels' => ['Текст тезиса', 'Заголовок тезиса'], 'max' => 4]),
            $b['link_text'](['hint' => 'Например «Подробнее в статье»']), $b['link_url'](),
            $f('pic', 'Фото', 'image'),
        ]],
        'parts' => ['Запчасти', [
            $b['title'](), $b['lead'](), $b['anchor'](), $b['btn'](['hint' => 'Кнопка раскрывающейся формы подбора']), $b['btn2_text'](), $b['btn2_url'](),
            $b['form_title'](), $b['form_text'](), $b['ok'](), $b['source'](),
            $b['items'](['label' => 'Три тезиса', 'labels' => ['Текст тезиса', 'Заголовок тезиса'], 'max' => 3]),
            $f('pic', 'Фото', 'image', ['hint' => 'Показывается за формой подбора запчасти. Без фото остаётся контурный рисунок.']),
        ]],
        'solutions' => ['Решения / зоны (вкладки — в свойстве «Решения (вкладки)»)', [$b['title'](), $b['lead'](), $b['anchor']()]],
        'calc' => ['Форма «Не знаете, какое оборудование нужно?»', [$b['title'](), $b['lead'](), $b['btn'](), $b['ok'](), $b['source']()]],
        'tech' => ['Технологии (карточки — в свойстве «Технологии»)', [$b['title'](), $b['lead'](), $b['anchor']()]],
        'tech_form' => ['Небольшая форма после технологий', [$b['title'](), $b['lead'](), $b['btn'](), $b['ok'](), $b['source']()]],
        'design' => ['Проектирование и комплексное оснащение (шаги — в свойстве «Этапы проектирования»)', [$b['title'](), $b['lead'](), $b['anchor']()]],
        'tz' => ['Форма «Уже есть план или ТЗ?»', [
            $b['title'](), $b['lead'](), $b['btn'](), $b['ok'](), $b['source'](),
            $b['items'](['label' => 'Факты о компании', 'labels' => ['Число (например 985+)', 'Подпись'], 'max' => 2]),
        ]],
        'reviews' => ['Отзывы (сами отзывы — в свойстве «Отзывы»)', [$b['title'](), $b['lead'](), $b['anchor']()]],
        'guarantee' => ['Круговая гарантия (кольцо — в свойстве «Круговая гарантия»)', [$b['title'](), $b['lead'](), $b['anchor']()]],
        'guarantee_form' => ['Краткая форма после гарантии (имя, телефон, компания)', [$b['title'](), $b['lead'](), $b['btn'](), $b['ok'](), $b['source']()]],
        'library' => ['Библиотека (фото — в свойстве «Библиотека: фото»)', [$b['title'](), $b['lead'](), $b['anchor']()]],
        'expert' => ['Экспертный центр (статьи и новости — в свойствах «Статьи блога» и «Новости»)', [
            $b['title'](), $b['lead'](), $b['anchor'](),
            $b['links'](['hint' => 'Например «/blog/» — «Все статьи»']),
        ]],
        'faq' => ['Вопросы и ответы (вопросы — в свойстве «Вопросы и ответы»)', [$b['title'](), $b['lead'](), $b['anchor']()]],
        'final' => ['Финальная форма', [
            $b['title'](), $b['lead'](), $b['anchor'](['hint' => 'Подпись пункта меню, ведущего к финальной форме']), $b['btn'](), $b['ok'](), $b['source'](),
            $b['items'](['label' => 'Темы обращения', 'labels' => ['Название темы', 'Код (buy, calc, kp, project, service)'], 'max' => 8,
                'hint' => 'Код kp выбирается кнопкой «Получить КП»']),
        ]],
    ];
    $groupDefs = [];
    foreach ($groups as $key => [$label, $fields]) {
        $groupDefs[$key] = ['label' => $label, 'fields' => $fields];
    }

    $schema = [
        'LAND_BLOCKS' => [
            'name' => 'Заголовки и тексты блоков', 'sort' => 800, 'mode' => 'groups', 'groups' => $groupDefs,
            'hint' => 'Заголовки, вводные тексты, кнопки и формы каждого блока страницы. Снимите «Показывать блок», чтобы скрыть его.',
        ],
        'LAND_SLIDES' => [
            'name' => 'Слайды первого экрана', 'sort' => 810, 'mode' => 'list', 'item' => 'Слайд', 'max' => 6, 'titleField' => 'title',
            'hint' => 'Заголовок первого слайда — это H1 страницы.',
            'fields' => [
                $f('title', 'Заголовок слайда'),
                $f('text', 'Текст', 'textarea', ['rows' => 3]),
                $f('kicker', 'Надзаголовок', 'text', ['hint' => 'Малая строка над заголовком (на телефоне скрыта)']),
                $f('tab', 'Подпись вкладки под слайдером'),
                $f('btn1_text', 'Кнопка 1: текст'),
                $f('btn1_target', 'Кнопка 1: куда ведёт', 'text', ['hint' => 'Якорь формы на странице (#consult, #final) или адрес']),
                $f('btn1_source', 'Кнопка 1: источник лида'),
                $f('btn2_text', 'Кнопка 2: текст'),
                $f('btn2_target', 'Кнопка 2: якорь или адрес'),
                $f('art', 'Контурный рисунок, пока нет фото', 'select', ['options' => $art]),
                $f('pic', 'Фон слайда', 'image'),
                $f('pic_mob', 'Фон для телефона (16:9)', 'image'),
            ],
        ],
        'LAND_ADVANTAGES' => [
            'name' => 'Преимущества под слайдером', 'sort' => 820, 'mode' => 'list', 'item' => 'Преимущество', 'max' => 4, 'titleField' => 'title',
            'fields' => [$f('title', 'Название'), $f('icon', 'Иконка', 'select', ['options' => $icons])],
        ],
        'LAND_SOLUTIONS' => [
            'name' => 'Решения (вкладки)', 'sort' => 830, 'mode' => 'list', 'item' => 'Решение', 'max' => 8, 'titleField' => 'title',
            'fields' => [
                $f('title', 'Название вкладки'),
                $f('sub', 'Подзаголовок'),
                $f('specs', 'Характеристики (3 строки)', 'pairs', ['labels' => ['Значение', 'Подпись (Масштаб / Основа / Дополнительно)'], 'max' => 3]),
                $f('url', 'Ссылка «Подробнее»: адрес'),
                $f('link_text', 'Ссылка «Подробнее»: текст'),
                $f('art', 'Контурный рисунок, пока нет фото', 'select', ['options' => $art]),
                $f('pic', 'Фото', 'image'),
                $f('objtypes', 'Показывать только для типа объекта', 'text', ['hint' => 'Коды через запятую (hotel34, hotel5, spa, restoran, private) — над вкладками появится переключатель типа объекта. Пусто — вкладка видна всегда.']),
            ],
        ],
        'LAND_NOVELTIES' => [
            'name' => 'Каталог: новинки', 'sort' => 832, 'mode' => 'list', 'item' => 'Новинка', 'max' => 12, 'titleField' => 'title',
            'hint' => 'Вкладка «Новинки» в блоке каталога. Вкладка «В наличии» — товары с флажком «В наличии» (свойство «Оборудование в наличии» выше).',
            'fields' => [
                $f('title', 'Название'),
                $f('params', 'Параметры (2–3 строки)', 'pairs', ['labels' => ['Значение', 'Подпись'], 'max' => 3]),
                $f('url', 'Ссылка «В каталог»'),
                $f('pic', 'Фото', 'image'),
            ],
        ],
        'LAND_TECH' => [
            'name' => 'Технологии', 'sort' => 844, 'mode' => 'list', 'item' => 'Технология', 'max' => 8, 'titleField' => 'title',
            'hint' => 'Карточки открываются модальным окном с подробным текстом. «Главная карточка» — крупный акцент (одна на блок).',
            'fields' => [
                $f('title', 'Название (например PIANO)'),
                $f('task_label', 'Метка задачи на карточке', 'text', ['hint' => 'Например «Мягкость», «Ресурс», «Стойкость цвета», «Для SPA»']),
                $f('text', 'Короткий текст на карточке', 'textarea', ['rows' => 2]),
                $f('detail', 'Текст в модальном окне', 'textarea', ['rows' => 6]),
                $f('featured', 'Главная карточка', 'select', ['options' => ['y' => 'Да — крупная, с акцентом']]),
                $f('pic', 'Фото', 'image'),
            ],
        ],
        'LAND_STEPS' => [
            'name' => 'Этапы проектирования', 'sort' => 840, 'mode' => 'list', 'item' => 'Этап', 'max' => 8, 'titleField' => 'title',
            'fields' => [
                $f('title', 'Название этапа'),
                $f('text', 'Описание', 'textarea', ['rows' => 3]),
                $f('result', 'Результат этапа', 'text', ['hint' => 'Например «Результат: техническое задание»']),
                $f('pic', 'Фото справа от текста', 'image', ['hint' => 'Необязательно. Без фото карточка этапа остаётся текстовой.']),
            ],
        ],
        'LAND_GUARANTEE' => [
            'name' => 'Круговая гарантия', 'sort' => 850, 'mode' => 'list', 'item' => 'Составляющая', 'max' => 4, 'titleField' => 'title',
            'hint' => 'Ровно четыре составляющие — кольцо рассчитано на четыре сектора.',
            'fields' => [
                $f('title', 'Название'),
                $f('text', 'Описание', 'textarea', ['rows' => 2]),
                $f('url', 'Ссылка: адрес'),
                $f('label', 'Ссылка: подпись'),
                $f('icon', 'Иконка, пока нет фото', 'select', ['options' => $art + $icons]),
                $f('pic', 'Круглое фото', 'image'),
            ],
        ],
        'LAND_LIBRARY' => [
            'name' => 'Библиотека: фото', 'sort' => 856, 'mode' => 'list', 'item' => 'Фото', 'max' => 5, 'titleField' => 'caption',
            'hint' => 'Ровно 5 фото: первое — крупное, слева, остальные четыре — плиткой 2×2 справа. Без фото — фактурная заглушка с подписью «Фото: …» (только свои фото или фото с купленной лицензией).',
            'fields' => [$f('pic', 'Фото', 'image'), $f('caption', 'Подпись')],
        ],
        'LAND_REVIEWS' => [
            'name' => 'Отзывы', 'sort' => 848, 'mode' => 'list', 'item' => 'Отзыв', 'max' => 6, 'titleField' => 'name',
            'hint' => 'Скан письма (необязательно) + короткая цитата + автор.',
            'fields' => [
                $f('quote', 'Цитата', 'textarea', ['rows' => 3]),
                $f('name', 'Имя, фамилия'),
                $f('role', 'Должность и объект'),
                $f('pic', 'Скан письма или фото', 'image'),
            ],
        ],
        'LAND_FAQ' => [
            'name' => 'Вопросы и ответы', 'sort' => 858, 'mode' => 'list', 'item' => 'Вопрос', 'max' => 12, 'titleField' => 'q',
            'fields' => [$f('q', 'Вопрос'), $f('a', 'Ответ', 'textarea', ['rows' => 3])],
        ],
    ];
    return $schema;
}

/** JSON из свойства → нормализованный массив (для компонента). Пустой массив, если данных нет. */
function profequip_LandingDecode($raw, string $propCode): array
{
    $schema = profequip_LandingSchema()[$propCode] ?? null;
    if (!$schema) {
        return [];
    }
    if (is_array($raw)) {
        $raw = $raw['TEXT'] ?? reset($raw);
    }
    $data = json_decode((string)$raw, true);
    return is_array($data) ? ProfequipLandingCards::Normalize($schema, $data) : [];
}

class ProfequipLandingCards
{
    public const USER_TYPE = 'ProfequipLandingCards';

    public static function GetUserTypeDescription(): array
    {
        return [
            'PROPERTY_TYPE' => 'S',
            'USER_TYPE' => self::USER_TYPE,
            'DESCRIPTION' => 'Лендинг: список карточек',
            'GetPropertyFieldHtml' => [__CLASS__, 'GetPropertyFieldHtml'],
            'ConvertToDB' => [__CLASS__, 'ConvertToDB'],
            'ConvertFromDB' => [__CLASS__, 'ConvertFromDB'],
            'GetAdminListViewHTML' => [__CLASS__, 'GetAdminListViewHTML'],
        ];
    }

    public static function ConvertFromDB($arProperty, $value)
    {
        return $value;
    }

    public static function GetAdminListViewHTML($arProperty, $value, $strHTMLControlName)
    {
        $data = profequip_LandingDecode($value['VALUE'] ?? '', (string)($arProperty['CODE'] ?? ''));
        return $data ? 'заполнено: ' . count($data) : '';
    }

    /** Оставляет только известные поля, приводит типы, выкидывает пустые строки */
    public static function Normalize(array $schema, array $data): array
    {
        $clean = static function (array $fields, array $row): array {
            $out = [];
            foreach ($fields as $f) {
                $c = $f['code'];
                $v = $row[$c] ?? null;
                switch ($f['type']) {
                    case 'number':
                        $out[$c] = ($v === null || $v === '' || !is_numeric($v)) ? '' : (int)$v;
                        break;
                    case 'select':
                        $out[$c] = (is_string($v) && isset($f['options'][$v])) ? $v : '';
                        break;
                    case 'image':
                        $out[$c] = max(0, (int)$v);
                        break;
                    case 'pairs':
                        $pairs = [];
                        foreach (is_array($v) ? $v : [] as $p) {
                            $a = trim((string)($p['v'] ?? ''));
                            $d = trim((string)($p['d'] ?? ''));
                            if ($a !== '' || $d !== '') {
                                $pairs[] = ['v' => $a, 'd' => $d];
                            }
                        }
                        $out[$c] = array_slice($pairs, 0, (int)($f['max'] ?? 20));
                        break;
                    default:
                        $out[$c] = trim(str_replace("\r", '', (string)($v ?? '')));
                }
            }
            return $out;
        };

        if ($schema['mode'] === 'groups') {
            $out = [];
            foreach ($schema['groups'] as $key => $g) {
                if (!isset($data[$key]) || !is_array($data[$key])) {
                    continue;
                }
                $row = $clean($g['fields'], $data[$key]);
                $row['active'] = empty($data[$key]['active']) ? 0 : 1;
                $out[$key] = $row;
            }
            return $out;
        }

        $out = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $r = $clean($schema['fields'], $row);
            $filled = false;
            foreach ($r as $k => $v) {
                if ($v !== '' && $v !== 0 && $v !== []) {
                    $filled = true;
                }
            }
            if ($filled) {
                $out[] = $r;
            }
        }
        return array_slice($out, 0, (int)($schema['max'] ?? 50));
    }

    /**
     * Удаляет файл, но не физический, если на него ссылается ещё одна запись b_file
     * (стартовые картинки лендинга — копии записей, указывающие на файлы других элементов).
     * CFile::Delete физически стирает файл, не проверяя других ссылок.
     */
    private static function DeleteFile(int $id): void
    {
        global $DB;
        $f = $DB->Query('SELECT SUBDIR, FILE_NAME FROM b_file WHERE ID = ' . $id)->Fetch();
        if (!$f) {
            return;
        }
        $shared = (int)$DB->Query("SELECT COUNT(*) C FROM b_file WHERE ID <> $id AND SUBDIR = '" . $DB->ForSql($f['SUBDIR']) . "' AND FILE_NAME = '" . $DB->ForSql($f['FILE_NAME']) . "'")->Fetch()['C'];
        if ($shared) {
            $DB->Query('DELETE FROM b_file WHERE ID = ' . $id);
        } else {
            \CFile::Delete($id);
        }
    }

    /** Загруженные в форме файлы (поля-картинки) → b_file; в JSON остаётся ID файла */
    private static function SaveFiles(array $schema, array $data): array
    {
        $handle = static function (array $fields, array $row): array {
            foreach ($fields as $f) {
                if ($f['type'] !== 'image') {
                    continue;
                }
                $c = $f['code'];
                $old = (int)($row[$c] ?? 0);
                $up = (string)($row['_up_' . $c] ?? '');
                if ($up !== '' && preg_match('/^ple_file_\d+$/', $up) && !empty($_FILES[$up]['tmp_name']) && is_uploaded_file($_FILES[$up]['tmp_name'])) {
                    $file = $_FILES[$up];
                    if ((string)\CFile::CheckImageFile($file) === '') {
                        $file['MODULE_ID'] = 'iblock';
                        $id = (int)\CFile::SaveFile($file, 'landing');
                        if ($id) {
                            if ($old) {
                                self::DeleteFile($old);
                            }
                            $row[$c] = $id;
                        }
                    }
                } elseif (!empty($row['_del_' . $c]) && $old) {
                    self::DeleteFile($old);
                    $row[$c] = 0;
                }
            }
            return $row;
        };

        if ($schema['mode'] === 'groups') {
            foreach ($schema['groups'] as $key => $g) {
                if (isset($data[$key]) && is_array($data[$key])) {
                    $data[$key] = $handle($g['fields'], $data[$key]);
                }
            }
            return $data;
        }
        foreach ($data as $i => $row) {
            if (is_array($row)) {
                $data[$i] = $handle($schema['fields'], $row);
            }
        }
        return $data;
    }

    public static function ConvertToDB($arProperty, $value)
    {
        $schema = profequip_LandingSchema()[$arProperty['CODE'] ?? ''] ?? null;
        $raw = $value['VALUE'] ?? '';
        if (!$schema || $raw === '' || $raw === null) {
            return ['VALUE' => ''];
        }
        $data = is_array($raw) ? $raw : json_decode((string)$raw, true);
        if (!is_array($data)) {
            return ['VALUE' => ''];
        }
        $data = self::Normalize($schema, self::SaveFiles($schema, $data));
        return ['VALUE' => $data ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''];
    }

    public static function GetPropertyFieldHtml($arProperty, $value, $strHTMLControlName)
    {
        $code = (string)($arProperty['CODE'] ?? '');
        $schema = profequip_LandingSchema()[$code] ?? null;
        if (!$schema) {
            return '<i>Для свойства ' . htmlspecialcharsbx($code) . ' нет описания полей (profequip_LandingSchema)</i>';
        }
        $data = profequip_LandingDecode($value['VALUE'] ?? '', $code);

        // адреса картинок для предпросмотра
        $files = [];
        $imageFields = array_filter(
            $schema['mode'] === 'groups' ? array_merge(...array_column($schema['groups'], 'fields')) : $schema['fields'],
            static fn($f) => $f['type'] === 'image'
        );
        if ($imageFields) {
            $walk = $schema['mode'] === 'groups' ? array_values($data) : $data;
            foreach ($walk as $row) {
                foreach ($imageFields as $f) {
                    $id = (int)($row[$f['code']] ?? 0);
                    if ($id && !isset($files[$id])) {
                        $files[$id] = (string)\CFile::GetPath($id);
                    }
                }
            }
        }

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $attr = static fn($v) => htmlspecialcharsbx(json_encode($v, $flags));
        $html = self::Assets();
        $html .= '<div class="ple" data-schema="' . $attr($schema) . '" data-value="' . $attr($data ?: new \stdClass()) . '" data-files="' . $attr($files ?: new \stdClass()) . '">'
            . '<input type="hidden" name="' . htmlspecialcharsbx($strHTMLControlName['VALUE']) . '" value="' . htmlspecialcharsbx($data ? json_encode($data, $flags) : '') . '">'
            . '</div>'
            . '<script>ProfLandingEd.boot();</script>';
        return $html;
    }

    private static function Assets(): string
    {
        static $done = false;
        if ($done) {
            return '';
        }
        $done = true;
        $css = <<<'CSS'
<style>
.ple{width:100%;max-width:980px;font-size:13px}
.ple details{border:1px solid #c4ced2;border-radius:4px;margin:0 0 6px;background:#fff}
.ple summary{cursor:pointer;padding:7px 10px;background:#eef2f4;display:flex;align-items:center;gap:8px;list-style:none}
.ple summary::-webkit-details-marker{display:none}
.ple summary .ple-n{color:#748186;min-width:22px}
.ple summary .ple-t{flex:1;font-weight:bold;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ple summary button{padding:0 7px}
.ple-body{padding:10px;display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:10px 14px}
.ple-f{display:flex;flex-direction:column;gap:3px}
.ple-f.wide{grid-column:1/-1}
.ple-f>span{color:#3f4b54}
.ple-f small{color:#748186}
.ple-f input[type=text],.ple-f input[type=number],.ple-f textarea,.ple-f select{width:100%;box-sizing:border-box}
.ple-pair{display:flex;gap:6px;margin-bottom:4px}
.ple-pair input{flex:1}
.ple-pic{display:flex;align-items:center;gap:10px}
.ple-pic img{max-height:56px;max-width:120px;border:1px solid #c4ced2}
.ple-add{margin:4px 0 12px}
.ple-empty{color:#748186;margin:6px 0}
</style>
CSS;
        $js = <<<'JS'
<script>
window.ProfLandingEd = window.ProfLandingEd || (function () {
  var seq = 0;
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined) e.textContent = text;
    return e;
  }
  function btn(text, title, fn) {
    var b = el('button', '', text);
    b.type = 'button';
    b.title = title;
    b.addEventListener('click', function (ev) { ev.preventDefault(); ev.stopPropagation(); fn(); });
    return b;
  }

  // Поле → { node, get(row) }
  function buildField(f, val, files, sync) {
    var wrap = el(f.type === 'pairs' || f.type === 'image' ? 'div' : 'label', 'ple-f' + (f.type === 'textarea' || f.type === 'pairs' ? ' wide' : ''));
    wrap.appendChild(el('span', '', f.label));
    var get;
    if (f.type === 'textarea') {
      var ta = el('textarea'); ta.rows = f.rows || 3; ta.value = val || '';
      ta.addEventListener('input', sync); wrap.appendChild(ta);
      get = function (row) { row[f.code] = ta.value; };
    } else if (f.type === 'select') {
      var sel = el('select'), o0 = el('option', '', '— не выбрано —'); o0.value = ''; sel.appendChild(o0);
      Object.keys(f.options).forEach(function (k) { var o = el('option', '', f.options[k]); o.value = k; sel.appendChild(o); });
      sel.value = val || ''; sel.addEventListener('change', sync); wrap.appendChild(sel);
      get = function (row) { row[f.code] = sel.value; };
    } else if (f.type === 'pairs') {
      var box = el('div'), rows = [];
      var add = function (v, d) {
        var line = el('div', 'ple-pair'), a = el('input'), b = el('input');
        a.type = b.type = 'text'; a.placeholder = f.labels[0]; b.placeholder = f.labels[1];
        a.value = v || ''; b.value = d || '';
        a.addEventListener('input', sync); b.addEventListener('input', sync);
        var item = { line: line, a: a, b: b };
        line.appendChild(a); line.appendChild(b);
        line.appendChild(btn('✕', 'Удалить строку', function () {
          rows.splice(rows.indexOf(item), 1); box.removeChild(line); toggle(); sync();
        }));
        rows.push(item); box.insertBefore(line, addBtn);
      };
      var addBtn = btn('+ строка', 'Добавить строку', function () { add('', ''); toggle(); sync(); });
      var toggle = function () { addBtn.style.display = rows.length >= (f.max || 20) ? 'none' : ''; };
      box.appendChild(addBtn);
      (val || []).forEach(function (p) { add(p.v, p.d); });
      toggle(); wrap.appendChild(box);
      get = function (row) { row[f.code] = rows.map(function (r) { return { v: r.a.value, d: r.b.value }; }); };
    } else if (f.type === 'image') {
      var id = parseInt(val || 0, 10) || 0, cur = el('div', 'ple-pic'), img = el('img');
      var name = 'ple_file_' + (++seq), file = el('input'), del = el('input');
      file.type = 'file'; file.name = name; file.accept = 'image/*';
      del.type = 'checkbox';
      if (id && files[id]) img.src = files[id]; else img.style.display = 'none';
      file.addEventListener('change', function () {
        if (file.files && file.files[0]) { img.src = URL.createObjectURL(file.files[0]); img.style.display = ''; del.checked = false; }
        sync();
      });
      del.addEventListener('change', sync);
      var dl = el('label'); dl.appendChild(del); dl.appendChild(document.createTextNode(' удалить'));
      cur.appendChild(img); cur.appendChild(file); if (id) cur.appendChild(dl);
      wrap.appendChild(cur);
      get = function (row) {
        row[f.code] = id;
        if (file.value) row['_up_' + f.code] = name;
        if (del.checked) row['_del_' + f.code] = 1;
      };
    } else {
      var inp = el('input'); inp.type = f.type === 'number' ? 'number' : 'text'; inp.value = val === undefined || val === null ? '' : val;
      inp.addEventListener('input', sync); wrap.appendChild(inp);
      get = function (row) { row[f.code] = inp.value; };
    }
    if (f.hint) wrap.appendChild(el('small', '', f.hint));
    return { node: wrap, get: get, input: wrap.querySelector('input,textarea,select') };
  }

  function buildFields(fields, data, files, sync, body) {
    var parts = fields.map(function (f) {
      var p = buildField(f, data ? data[f.code] : undefined, files, sync);
      body.appendChild(p.node);
      return p;
    });
    return {
      collect: function () { var row = {}; parts.forEach(function (p) { p.get(row); }); return row; },
      parts: parts
    };
  }

  function init(root) {
    var schema = JSON.parse(root.getAttribute('data-schema'));
    var data = JSON.parse(root.getAttribute('data-value'));
    var files = JSON.parse(root.getAttribute('data-files'));
    var hidden = root.querySelector('input[type=hidden]');
    var collect;
    var sync = function () { hidden.value = JSON.stringify(collect()); };
    var form = root.closest('form');
    if (form) form.addEventListener('submit', sync, true);

    if (schema.mode === 'groups') {
      var blocks = [];
      Object.keys(schema.groups).forEach(function (key) {
        var g = schema.groups[key], d = data[key], det = el('details'), sum = el('summary');
        var on = el('input'); on.type = 'checkbox'; on.checked = !!(d && d.active);
        on.addEventListener('click', function (e) { e.stopPropagation(); }); on.addEventListener('change', sync);
        var lab = el('label'); lab.appendChild(on); lab.appendChild(document.createTextNode(' показывать'));
        lab.addEventListener('click', function (e) { e.stopPropagation(); });
        sum.appendChild(el('span', 'ple-t', g.label)); sum.appendChild(lab); det.appendChild(sum);
        var body = el('div', 'ple-body'); det.appendChild(body);
        var fs = buildFields(g.fields, d, files, sync, body);
        blocks.push({ key: key, on: on, fs: fs });
        root.appendChild(det);
      });
      collect = function () {
        var out = {};
        blocks.forEach(function (b) { var r = b.fs.collect(); r.active = b.on.checked ? 1 : 0; out[b.key] = r; });
        return out;
      };
    } else {
      var rows = [], list = el('div'), empty = el('div', 'ple-empty', 'Пока ничего нет.');
      var addBtn = btn('+ ' + schema.item, 'Добавить', function () { addRow({}, true); });
      addBtn.className = 'ple-add';
      var renum = function () {
        rows.forEach(function (r, i) { r.n.textContent = (i + 1) + '.'; });
        empty.style.display = rows.length ? 'none' : '';
        addBtn.style.display = rows.length >= (schema.max || 50) ? 'none' : '';
      };
      var addRow = function (d, open) {
        var det = el('details'), sum = el('summary'), n = el('span', 'ple-n'), t = el('span', 'ple-t');
        sum.appendChild(n); sum.appendChild(t);
        var body = el('div', 'ple-body'), item = {};
        var fs = buildFields(schema.fields, d, files, function () { setTitle(); sync(); }, body);
        var titleIdx = 0;
        schema.fields.forEach(function (f, i) { if (f.code === schema.titleField) titleIdx = i; });
        var setTitle = function () { t.textContent = fs.parts[titleIdx].input.value || '(без названия)'; };
        setTitle();
        var move = function (dir) {
          var i = rows.indexOf(item), j = i + dir;
          if (j < 0 || j >= rows.length) return;
          rows.splice(i, 1); rows.splice(j, 0, item);
          list.insertBefore(det, dir < 0 ? list.children[j] : list.children[j].nextSibling);
          renum(); sync();
        };
        sum.appendChild(btn('↑', 'Выше', function () { move(-1); }));
        sum.appendChild(btn('↓', 'Ниже', function () { move(1); }));
        sum.appendChild(btn('✕', 'Удалить', function () {
          if (!confirm('Удалить «' + t.textContent + '»?')) return;
          rows.splice(rows.indexOf(item), 1); list.removeChild(det); renum(); sync();
        }));
        det.appendChild(sum); det.appendChild(body);
        if (open) det.open = true;
        item.n = n; item.fs = fs;
        rows.push(item); list.appendChild(det); renum();
        if (open) sync();
      };
      root.appendChild(empty); root.appendChild(list); root.appendChild(addBtn);
      (Array.isArray(data) ? data : []).forEach(function (d) { addRow(d, false); });
      renum();
      collect = function () { return rows.map(function (r) { return r.fs.collect(); }); };
    }
    sync();
  }

  return {
    boot: function () {
      document.querySelectorAll('.ple:not([data-ready])').forEach(function (r) { r.setAttribute('data-ready', '1'); init(r); });
    }
  };
})();
</script>
JS;
        return $css . $js;
    }
}

AddEventHandler('iblock', 'OnIBlockPropertyBuildList', ['ProfequipLandingCards', 'GetUserTypeDescription']);

/**
 * Создаёт на инфоблоке направления свойства лендинга (идемпотентно) и вкладку «Лендинг» в форме
 * элемента (общая настройка и личные настройки, где форма уже кастомизирована).
 * Возвращает [код => ID свойства].
 */
function profequip_LandingEnsureDirectionProps(int $iblockId): array
{
    Loader::includeModule('iblock');
    $ids = [];
    $prop = new \CIBlockProperty();
    $add = static function (array $fields) use ($iblockId, $prop): int {
        $exists = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $fields['CODE']])->Fetch();
        if ($exists) {   // название и подсказку держим в актуальном виде (правятся в схеме)
            if ($exists['NAME'] !== $fields['NAME'] || $exists['HINT'] !== ($fields['HINT'] ?? '')) {
                $prop->Update((int)$exists['ID'], ['NAME' => $fields['NAME'], 'HINT' => $fields['HINT'] ?? '']);
            }
            return (int)$exists['ID'];
        }
        $id = $prop->Add($fields + ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', 'MULTIPLE' => 'N', 'FILTRABLE' => 'N']);
        if (!$id) {
            throw new \RuntimeException('Не создано свойство ' . $fields['CODE'] . ': ' . $prop->LAST_ERROR);
        }
        return (int)$id;
    };

    foreach (profequip_LandingSchema() as $code => $s) {
        $ids[$code] = $add([
            'CODE' => $code, 'NAME' => $s['name'], 'SORT' => $s['sort'], 'HINT' => $s['hint'] ?? '',
            'PROPERTY_TYPE' => 'S', 'USER_TYPE' => ProfequipLandingCards::USER_TYPE,
        ]);
    }

    $blog = (int)(\CIBlock::GetList([], ['CODE' => 'blog'])->Fetch()['ID'] ?? 0);
    $news = (int)(\CIBlock::GetList([], ['CODE' => 'news'])->Fetch()['ID'] ?? 0);
    $ids['LAND_MAT_BLOG'] = $add([
        'CODE' => 'LAND_MAT_BLOG', 'NAME' => 'Статьи блога', 'SORT' => 860, 'PROPERTY_TYPE' => 'E', 'MULTIPLE' => 'Y', 'LINK_IBLOCK_ID' => $blog,
        'HINT' => 'Блок «Экспертный центр». Самая свежая статья — крупная карточка, остальное (статьи и новости вместе) — списком по дате.',
    ]);
    $ids['LAND_MAT_NEWS'] = $add([
        'CODE' => 'LAND_MAT_NEWS', 'NAME' => 'Новости', 'SORT' => 870, 'PROPERTY_TYPE' => 'E', 'MULTIPLE' => 'Y', 'LINK_IBLOCK_ID' => $news,
        'HINT' => 'Блок «Экспертный центр»: новости идут списком справа от крупной карточки, по дате.',
    ]);

    profequip_LandingEnsureFormTab($iblockId, $ids);
    return $ids;
}

/**
 * В форме элемента направления настроены вкладки вручную (свойства перечислены поимённо), поэтому
 * новые свойства сами не появятся — добавляем (или обновляем) вкладку «Лендинг» перед вкладкой «SEO».
 */
function profequip_LandingEnsureFormTab(int $iblockId, array $propIds): void
{
    global $DB;
    $name = 'form_element_' . $iblockId;
    $items = ['edit_landing--#--Лендинг'];
    foreach ($propIds as $pid) {
        $meta = \CIBlockProperty::GetByID($pid)->Fetch();
        $items[] = 'PROPERTY_' . $pid . '--#--' . $meta['NAME'];
    }
    $tab = implode('--,--', $items) . '--;--';

    $rs = $DB->Query("SELECT USER_ID, COMMON, VALUE FROM b_user_option WHERE CATEGORY = 'form' AND NAME = '" . $DB->ForSql($name) . "'");
    while ($row = $rs->Fetch()) {
        $val = unserialize($row['VALUE'], ['allowed_classes' => false]);
        if (!is_array($val) || empty($val['tabs'])) {
            continue;
        }
        $tabs = $val['tabs'];
        $start = strpos($tabs, 'edit_landing--#--');
        if ($start !== false) {   // вкладка уже есть — обновляем состав и подписи
            $end = strpos($tabs, '--;--', $start);
            $tabs = substr($tabs, 0, $start) . $tab . substr($tabs, $end === false ? strlen($tabs) : $end + 5);
        } else {
            $pos = strpos($tabs, 'edit14--#--');   // вкладка SEO
            $tabs = $pos !== false ? substr($tabs, 0, $pos) . $tab . substr($tabs, $pos) : $tabs . $tab;
        }
        $val['tabs'] = $tabs;
        \CUserOptions::SetOption('form', $name, $val, $row['COMMON'] === 'Y', (int)$row['USER_ID']);
    }
}
