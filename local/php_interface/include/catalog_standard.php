<?php
/**
 * Стандарт характеристик каталога (инфоблок catalog) — единый источник правды для:
 *   - миграции 2026-10-13-catalog-standard-*.php (структура разделов, названия свойств,
 *     слияние дублей, нормализация значений, числовые свойства, «Тип», «Тип предприятия»);
 *   - набора и порядка фильтров по разделам (CatalogStandard::filterCodes(), шаблон
 *     catalog.smart.filter/.default/result_modifier.php);
 *   - классификации новых товаров (ProductType — «Тип» запчастей и текстиля).
 *
 * Всё адресуется по CODE (раздела, свойства, товара), не по ID — ID на test3 и проде разные.
 * Описание принятых решений: local/deploy/catalog_restructure/README.md.
 */

class CatalogStandard
{
    /* ===================================================================== *
     *  1. СТРУКТУРА РАЗДЕЛОВ (прачечное оборудование)
     * ===================================================================== */

    /** Новые разделы: CODE => [NAME, CODE родителя, SORT, CODE раздела-донора картинки]. */
    public const NEW_SECTIONS = [
        'oborudovanie-dlya-himchistki' => ['Оборудование для химчистки', 'prachechnoe-oborudovanie', 450, 'mashiny-himchistki'],
        'sortirovka-belya' => ['Сортировка белья', 'vspomogatelnoe-oborudovanie', 200, 'transportirovka-belya'],
    ];

    /** Перенос разделов в дереве: CODE => CODE нового родителя. URL разделов плоские — не меняются. */
    public const MOVE_SECTIONS = [
        'mashiny-himchistki' => 'oborudovanie-dlya-himchistki',
        'pyatnovyvodnye-stoly' => 'oborudovanie-dlya-himchistki',
        'podayushhie-ustrojstva' => 'gladilnoe-oborudovanie',
        'professionalnye-skladyvateli' => 'gladilnoe-oborudovanie',
    ];

    /** Порядок разделов (SORT) — по логике техпроцесса прачечной: стирка → сушка → глажение → финиш. */
    public const SECTION_SORT = [
        'stiralnye-mashiny' => 100, 'sushilnoe-oborudovanie' => 200, 'gladilnoe-oborudovanie' => 300,
        'finishnoe-oborudovanie' => 400, 'oborudovanie-dlya-himchistki' => 450, 'vspomogatelnoe-oborudovanie' => 500,
        'upakovochnoe-oborudovanie' => 550, 'komplekty-prachechnogo-oborudovaniya' => 600, 'prachechnoe-oborudovanie-v-nalichii' => 900,
        // стиральные
        'vysokoskorostnye-stiralnye-mashiny' => 100, 'nizko-i-sredneskorostnye-stiralnye-mashiny' => 200,
        'barernye-stiralnye-mashiny' => 300, 'tunnelnye-stiralnye-mashiny' => 400, 'stiralno-sushilnye-mashiny' => 500,
        'tandem-stiralnaya-i-sushilnaya-mashina-v-kolonnu' => 600,
        // сушильное
        'sushilnye-mashiny' => 100, 'sushilnye-mashiny-s-funktsiej-akvachistki' => 200, 'tsentrifugi' => 300, 'sushilnye-kabinety' => 400,
        // гладильное
        'gladilnye-katki' => 100, 'gladilnye-kalandry' => 200, 'podayushhie-ustrojstva' => 300, 'professionalnye-skladyvateli' => 400,
        // финишное
        'gladilnye-stoly' => 100, 'paromanekeny' => 200, 'parogeneratory' => 300, 'gladilnye-pressy' => 400, 'obrabotka-fasonnyh-izdelij' => 500,
        // химчистка, вспомогательное
        'mashiny-himchistki' => 100, 'pyatnovyvodnye-stoly' => 200, 'transportirovka-belya' => 100, 'sortirovka-belya' => 200,
    ];

    /** Переименование разделов (NAME). Влияет на меню, крошки и «Тип». */
    public const RENAME_SECTIONS = [
        'podayushhie-ustrojstva' => 'Подающие машины и сепараторы',
        'professionalnye-skladyvateli' => 'Складыватели и штабелеры',
        'tandem-stiralnaya-i-sushilnaya-mashina-v-kolonnu' => 'Стирально-сушильные колонны (тандем)',
    ];

    /**
     * H1 раздела (SEO-поле «Заголовок раздела», SECTION_PAGE_TITLE): название раздела в меню
     * короткое, H1 — под основной запрос кластера. section.php берёт H1 отсюда, если задан.
     */
    public const SECTION_H1 = [
        'prachechnoe-oborudovanie' => 'Профессиональное прачечное оборудование',
        'stiralnye-mashiny' => 'Промышленные стиральные машины',
        'vysokoskorostnye-stiralnye-mashiny' => 'Высокоскоростные стирально-отжимные машины',
        'sushilnoe-oborudovanie' => 'Промышленное сушильное оборудование',
        'sushilnye-mashiny' => 'Промышленные сушильные машины для прачечных',
        'gladilnoe-oborudovanie' => 'Гладильное оборудование для прачечных',
        'oborudovanie-dlya-himchistki' => 'Оборудование для химчистки',
        'mashiny-himchistki' => 'Машины химчистки',
        'professionalnye-skladyvateli' => 'Складыватели белья и штабелеры',
    ];

    /**
     * Разделы, которые расформировываются: товары переносятся (PRODUCT_MOVES), раздел
     * деактивируется, его URL — 301 на указанный адрес (REDIRECTS в .htaccess-фрагменте).
     */
    public const DISSOLVE_SECTIONS = [
        'promyshlennye-stiralnye-mashiny' => '/product-category/stiralnye-mashiny/',
        'stiralno-otzhimnye-mashiny' => '/product-category/vysokoskorostnye-stiralnye-mashiny/',
        'gladilnye-mashiny' => '/product-category/gladilnye-kalandry/',
    ];

    /**
     * Перенос товаров: CODE товара => список CODE разделов, к которым он должен быть привязан
     * (заменяет все текущие привязки внутри прачечного оборудования; витрина «в наличии» сохраняется).
     */
    public const PRODUCT_MOVES = [
        // «Промышленные стиральные машины» — свалка: сушилка, туннель, барьерные
        'promyshlennaya-stiralnaya-mashina-jensen-tumble-dryer-jtd' => ['sushilnye-mashiny'],
        'promyshlennaya-stiralnaya-mashina-jensen-washer-extractor-jwe' => ['vysokoskorostnye-stiralnye-mashiny'],
        '600' => ['vysokoskorostnye-stiralnye-mashiny'], // JENSEN JWE 270/600
        'promyshlennaya-barernaya-stiralnaya-mashina-jensen-tunnel-washer-uniq' => ['tunnelnye-stiralnye-mashiny'],
        'promyshlennaya-barernaya-stiralnaya-mashina-jensen-barrier-washer-jbw' => ['barernye-stiralnye-mashiny'],
        'promyshlennaya-barernaya-stiralnaya-mashina-oasis-xl110-110-kg' => ['barernye-stiralnye-mashiny'],
        'promyshlennaya-stiralnaya-mashina-oasis-softmount-s-naklonom-100-kg' => ['vysokoskorostnye-stiralnye-mashiny'],
        'promyshlennaya-stiralnaya-mashina-oasis-softmount-s-otzhimom-sxt1200fd-zq-120-kg' => ['vysokoskorostnye-stiralnye-mashiny'],
        'promyshlennaya-stiralnaya-mashina-oasis-softmount-s-otzhimom-i-sensornym-upravleniem-25-kg' => ['vysokoskorostnye-stiralnye-mashiny'],
        // «Стирально-отжимные» Danube — G-фактор 450–500, это высокоскоростные машины
        'professionalnaya-stiralno-otzhimnaya-mashina-danube-wed-11c-et2-zagruzka-11-' => ['vysokoskorostnye-stiralnye-mashiny'],
        'professionalnaya-stiralno-otzhimnaya-mashina-danube-wed-14c-et2-zagruzka-14-kg-' => ['vysokoskorostnye-stiralnye-mashiny'],
        'professionalnaya-stiralno-otzhimnaya-mashina-danube-wed-18c-et2-zagruzka-18-kg-' => ['vysokoskorostnye-stiralnye-mashiny'],
        'professionalnaya-stiralno-otzhimnaya-mashina-danube-wed-22c-et2-zagruzka-22-' => ['vysokoskorostnye-stiralnye-mashiny'],
        'professionalnaya-stiralno-otzhimnaya-mashina-danube-wed-28c-et2-zagruzka-28-kg' => ['vysokoskorostnye-stiralnye-mashiny'],
        'professionalnaya-stiralnaya-mashina-danube-wpr-10-zagruzka-10-kg' => ['vysokoskorostnye-stiralnye-mashiny'],
        'professionalnaya-stiralnaya-mashina-danube-wpr-8-zagruzka-8-kg' => ['vysokoskorostnye-stiralnye-mashiny'],
        // «Гладильные машины» — 3 каландра, дубль раздела каландров
        'gladilnyj-kalandr-sailstar-yc8-30-1r' => ['gladilnye-kalandry'],
        'professionalnyj-gladilnyj-kalandr-tolon-tfi3516' => ['gladilnye-kalandry'],
        'professionalnyj-gladilnyj-kalandr-tolon-tfi3521' => ['gladilnye-kalandry'],
        // Системы сортировки Jensen лежали в «Финишном оборудовании»
        'sortirovochnye-sistemy-jensen-futurail' => ['sortirovka-belya'],
        'integrirovannaya-sistema-sortirovki-jensen-futural-and-inwatex-collaboration' => ['sortirovka-belya'],
        'sistema-sortirovki-jensen-continuusort' => ['sortirovka-belya'],
        'sistema-avtokalibrovki-dlya-sortirovochnyh-kontejnerov-jensen' => ['sortirovka-belya'],
        'ruchnaya-sortirovka-dlya-sistemy-jensen-futurail' => ['sortirovka-belya'],
        'mobilnaya-sortirovochnaya-sistema-jensen-mobilesort' => ['sortirovka-belya'],
        'sortirovochnaya-sistema-jensen-transsrot' => ['sortirovka-belya'],
        'programnnoe-obespechenie-jensen-railexpress' => ['sortirovka-belya'],
        'ergonomichnaya-sortirovochnaya-sistema-jensen-multisort' => ['sortirovka-belya'],
        'sortirovochnaya-sistema-jensen-multisort' => ['sortirovka-belya'],
        'sistema-sortirovki-jensen-combisort' => ['sortirovka-belya'],
        'dvojnye-dveri-dlya-sortirovochnyh-kontejnerov' => ['sortirovka-belya'],
        'mnogofunktsionalnaya-sistema-skanirovaniya-dlya-prachechnoj-jensen-jenscan-plus' => ['sortirovka-belya'],
        // Штабелеры/складыватели/сепараторы Jensen были привязаны только к корню
        'promyshlennyj-shtabeler-jensen-jenstack-max-twin-450-500' => ['professionalnye-skladyvateli'],
        'promyshlennyj-shtabeler-jensen-max-blanket' => ['professionalnye-skladyvateli'],
        'promyshlennyj-shtabeler-jensen-jenstack-max-sw' => ['professionalnye-skladyvateli'],
        'professionalnyj-skladyvatel-jensen-jenfold-classic-s' => ['professionalnye-skladyvateli'],
        'promyshlennyj-shtabeler-jensen-jenstack-max' => ['professionalnye-skladyvateli'],
        'promyshlennyj-shtabeler-jensen-jenstack-ot-dlya-krupnyh-izdelij' => ['professionalnye-skladyvateli'],
        'promyshlennyj-shtabeler-jensen-jenstack-mfs' => ['professionalnye-skladyvateli'],
        'shtabeler-alpha-s5-sw5' => ['professionalnye-skladyvateli'],
        'separator-belya-jensen-viking-2000' => ['podayushhie-ustrojstva'],
        'sistema-podachi-polotenets-jensen-evolution-cube' => ['podayushhie-ustrojstva'],
        'kalandrovaya-konvejernaya-liniya-dlya-krupnogo-belya-jensen-jenrail-2000-automatic' => ['gladilnye-kalandry'],
        // Тоннельный финишер — парокабинет; упаковщик DEKKEN был в складывателях
        'universalnyj-tunnelnyj-finisher-macpi-534' => ['paromanekeny'],
        'avtomaticheskaya-upakovochnaya-mashina-dekken-t-matic-5020-' => ['upakovochnoe-oborudovanie'],
        'modul-markirovki-dekken-t-matic-iq-label-' => ['upakovochnoe-oborudovanie'],
    ];

    /** Разделы-витрины: привязка к ним сохраняется при переносе, «Тип» по ним не считается. */
    public const SHOWCASE_SECTIONS = ['prachechnoe-oborudovanie-v-nalichii', 'test-import-tmp'];

    /* ===================================================================== *
     *  2. СВОЙСТВА: названия, дубли, скрытие, числовые
     * ===================================================================== */

    /**
     * Названия свойств: CODE => NAME. Единый стиль — первое слово с заглавной, единицы через
     * запятую в конце строчными (кВт, мм, кг, л, дБ, об/мин, м³/ч, м²).
     */
    public const PROPERTY_NAMES = [
        'BRAND' => 'Бренд',
        'TIP_OBORUDOVANIYA' => 'Тип оборудования',
        'GABARITNYE_RAZMERY_SH_G_V_MM' => 'Габаритные размеры (Ш×Г×В), мм',
        'VES_KG' => 'Вес, кг',
        'TIP_PREDPRIYATIYA' => 'Тип предприятия',
        'SHIRINA_VALA_MM' => 'Ширина вала, мм',
        'DIAMETR_VALA_MM' => 'Диаметр вала, мм',
        'SKOROST_GLAZHKI_M_MIN' => 'Скорость глажения, м/мин',
        'DVIGATEL_VALA_KVT' => 'Мощность двигателя вала, кВт',
        'POTREBLENIE_PARA_KG_CH' => 'Потребление пара, кг/ч',
        'DLINA_VALA_MM' => 'Длина вала, мм',
        'SHIRINA_ZONY_GLAZHENIYA_MM' => 'Рабочая ширина вала, мм',
        'SKOROST_GLAZHENIYA_M_MIN' => 'Скорость глажения, м/мин',
        'TIP_NAGREVA' => 'Тип нагрева',
        'MOSHHNOST_KVT' => 'Мощность, кВт',
        'NAPRYAZHENIE_V' => 'Напряжение, В',
        'MOSHHNOST_DVIGATELYA_KVT' => 'Мощность двигателя, кВт',
        'UROVEN_SHUMA_DB' => 'Уровень шума, дБ',
        'VYPARIVAYUSHAYA_SPOSOBNOST' => 'Выпаривающая способность, л/ч',
        'PROIZVODITELNOST_KG_CH' => 'Производительность, кг/ч',
        'POTREBLENIE_ELEKTROENERGII' => 'Номинальное потребление электроэнергии, кВт',
        'MOTOR_VENTILYATORA_KVT' => 'Мотор вентилятора, кВт',
        'POTREBLENIE_PARA_KG_CHAS' => 'Потребление пара, кг/ч',
        'VYVOD_VOZDUHA_DIAMETR_MM' => 'Вывод воздуха, диаметр, мм',
        'VOZDUSHNYJ_POTOK_ELEKTRO' => 'Воздушный поток (электрический нагрев), м³/ч',
        'VOZDUSHNYJ_POTOK_PAR_GAZ' => 'Воздушный поток (паровой/газовый нагрев), м³/ч',
        'TEPLOOBMENNAYA_POVERHNOST' => 'Теплообменная поверхность, дм²',
        'NAZNACHENIE' => 'Назначение',
        'CHISLO_RYADOV_PODACHI' => 'Число рядов подачи',
        'CHISLO_STANTSIJ_PODACHI' => 'Число станций подачи',
        'ZAGRUZKA_KG' => 'Загрузка, кг',
        'POLNOE_VREMYA_CIKLA_MIN' => 'Полное время цикла, мин',
        'DIAMETR_BARABANA_MM' => 'Диаметр барабана, мм',
        'GLUBINA_BARABANA_MM' => 'Глубина барабана, мм',
        'OBYEM_BARABANA_L' => 'Объём барабана, л',
        'SKOROST_PRI_STIRKE_OB_MIN' => 'Скорость вращения при стирке, об/мин',
        'SKOROST_PRI_OTZHIME_OB_MIN' => 'Скорость отжима, об/мин',
        'G_FACTOR' => 'G-фактор (сила отжима)',
        'DIAMETR_ZAGRUZOCHNOGO_LYUKA' => 'Диаметр загрузочного люка, мм',
        'RABOCHEE_DAVLENIE_PARA_BAR' => 'Рабочее давление пара, бар',
        'RASHOD_ENERGII_DVIGATEL' => 'Расход электроэнергии двигателя, кВт',
        'PAROVOJ_KLAPAN_SOEDINENIE' => 'Подсоединение парового клапана, DN (BSP)',
        'DAVLENIE_PARA_KPA' => 'Давление пара, кПа',
        'RASHOD_PARA_KG_CIKL' => 'Расход пара, кг/цикл',
        'PODSOEDINENIE_VODY' => 'Подсоединение входящей воды, DN (BSP)',
        'DAVLENIE_VODY_KPA' => 'Давление воды, кПа',
        'SKOROST_ZALIVA' => 'Скорость залива, л/мин',
        'POTREBLENIE_VODY_HOL_GOR_L' => 'Потребление воды (хол. + гор.), л',
        'PODSOEDINENIE_SLIVA_MM' => 'Подсоединение слива, диаметр, мм',
        'SKOROST_SLIVA' => 'Скорость слива, л/мин',
        'OBYEM_ISPARYAEMOGO_VOZDUHA' => 'Объём испаряемого воздуха, л/ч',
        'DAVLENIE_PARA_OPCIYA' => 'Опциональное давление пара, бар',
        'MOSHHNOST_GAZ_KVT' => 'Мощность газового нагрева, кВт',
        'RASHOD_ENERGII_VENTILYATOR' => 'Расход электроэнергии вентилятора, кВт',
        'ISPARITEL_SOEDINENIE' => 'Подсоединение испарителя, DN (BSP)',
        'PODSOEDINENIE_GAZA' => 'Подсоединение газа, DN (BSP)',
        'DAVLENIE_GAZA_MBAR' => 'Давление газа, мбар',
        'POTREBLENIE_GAZA' => 'Потребление газа, м³/ч',
        'MOSHNOST_PAR_KVT' => 'Мощность парового нагрева, кВт',
        'GLAZHENIE' => 'Подогрев столешницы',
        'RAZMERY_STOLA' => 'Размеры стола, мм',
        'RUKAV' => 'Рукав',
        'BOJLER' => 'Бойлер',
        'OBYEM_BOYLERA_L' => 'Объём бойлера, л',
        'TIP_PLITY' => 'Тип столешницы',
        'REGULIROVANIE_STOLESHNICY' => 'Регулировка столешницы по высоте',
        'UTYUG_V_KOMPLEKTE' => 'Утюг в комплекте',
        'SOSTAV_KOMPLEKTA' => 'Состав комплекта оборудования',
        'OSTATOCHNAYA_VLAZHNOST' => 'Остаточная влажность, %',
        'SISTEMA_UPRAVLENIYA' => 'Система управления',
        'KONTROL_OST_VLAZHNOSTI' => 'Контроль остаточной влажности',
        'REVERS_BARABANA' => 'Реверс барабана',
        'AKVACHISTKA' => 'Аквачистка',
        'ISTOCHNIK_PARA' => 'Источник пара',
        'PODACHA_VOZDUHA' => 'Подача воздуха',
        'DAVLENIE_VOZDUHA_BAR' => 'Рабочее давление воздуха, бар',
        'POTREBLENIE_SZHATOGO_VOZDUHA' => 'Потребление сжатого воздуха, л/мин',
        'PODKLYUCHENIE_PARA' => 'Подключение пара',
        'VOZVRAT_KONDENSATA' => 'Возврат конденсата',
        'OBYEM_VOZDUHA_G_MIN' => 'Объём испаряемого воздуха, г/мин',
        'VREMYA_SUSHKI' => 'Время сушки при полной загрузке, мин',
        'FUNKTSIYA_SHTABELIROVANIYA' => 'Функция штабелирования',
        'SHIRINA_DLYA_SKLADYVATELYA' => 'Рабочая ширина складывателя, мм',
        'POTREBLENIE_PARA_L_MIN' => 'Потребление пара, л/мин',
        'RABOCHAYA_TEMPERATURA_C' => 'Рабочая температура, °C',
        'MOTOR_ASPIRATORA_KVT' => 'Мотор аспиратора, кВт',
        'SLIV_BOJLERA' => 'Слив бойлера',
        'RASHOD_PARA_KG_CHAS' => 'Расход пара, кг/ч',
        'DOPOLNITELNYE_PARAMETRY' => 'Дополнительные параметры',
        'REZISTOR_BOJLERA_KVT' => 'Мощность ТЭНа бойлера, кВт',
        'MOTOR_NASOSA_KVT' => 'Мотор насоса, кВт',
        'MOTOR_PODDUVA_KVT' => 'Мотор поддува, кВт',
        'REZISTOR_RAZOGREVA_STOLA' => 'Мощность подогрева стола, кВт',
        'REZISTOR_UTYUGA_KVT' => 'Мощность утюга, кВт',
        'UDALENIE_PYATEN' => 'Удаление пятен',
        'RASTVORITEL' => 'Растворитель',
        'KOLICHESTVO_BAKOV' => 'Количество баков',
        'NAGREV' => 'Нагрев',
        'OBEM_PERVOGO_BAKA_L' => 'Объём первого бака, л',
        'OBEM_VTOROGO_BAKA_L' => 'Объём второго бака, л',
        'OBEM_TRETEGO_BAKA_L' => 'Объём третьего бака, л',
        'OBEM_DISTILLYATORA_L' => 'Объём дистиллятора, л',
        'POLEZNY_OBYEM_DISTILLYATORA' => 'Полезный объём дистиллятора, л',
        'OBYEM_NEYLON_FILTRA' => 'Объём нейлонового фильтра, л',
        'PLOSHAD_NEYLONOVOGO_FILTRA' => 'Площадь нейлонового фильтра, м²',
        'OBEM_DECO_FILTRA_L' => 'Объём Deco-фильтра, л',
        'KOLICHESTVO_DECO_FILTROV' => 'Количество Deco-фильтров',
        'KARTRIDZHI_DLYA_DECO_FILTRA' => 'Картриджи для Deco-фильтра',
        'RABOCHAYA_MOSHHNOST_PAR_KVT' => 'Рабочая мощность (пар), кВт',
        'PODKLYUCHENIE_VOZDUHA' => 'Подключение сжатого воздуха, мм',
        'DINAMICHESKAYA_NAGRUZKA' => 'Динамическая нагрузка, кг/м²',
        'OBEM_EKO_FILTRA_L' => 'Объём эко-фильтра, л',
        'PLOSHAD_POVERHNOSTI_FILTRA' => 'Площадь эко-фильтра, м²',
        'RABOCHEE_DAVLENIE_PARA' => 'Рабочее давление пара, бар',
        'MIN_RABOCHEE_DAVLENIE_VODY' => 'Минимальное давление воды, бар',
        'MAX_RABOCHEE_DAVLENIE_VODY' => 'Максимальное давление воды, бар',
        'RABOCHEE_DAVLENIE_VOZDUHA' => 'Рабочее давление сжатого воздуха, бар',
        'MAX_DAVLENIE_VOZDUHA' => 'Максимальное давление сжатого воздуха, бар',
        'KOMPLEKT_DLYA_AKVACHISTKI' => 'Комплект для аквачистки',
        'MAX_RASHOD_VOZDUHA' => 'Максимальный расход воздуха, л/мин',
        'KOLICHESTVO_IZDELIJ_V_CHAS' => 'Производительность, изделий/ч',
        'DLITELNOST_AVTONOMNO' => 'Длительность автономной работы, ч',
        'VSTROENNYJ_VAKUUM_KVT' => 'Встроенный вакуум, кВт',
        'RAZMER_RABOCHEJ_POVERHNOSTI' => 'Размер рабочей поверхности, мм',
        'FORMA_STOLESHNITSY' => 'Форма столешницы',
        'MAX_RASHOD_VOZDUHA_ATM' => 'Максимальный расход воздуха, куб. фут/мин',
        'MAX_SKOROST_SKLADYVANIYA' => 'Максимальная скорость складывания, м/мин',
        'MIN_SKOROST_SKLADYVANIYA' => 'Минимальная скорость складывания, м/мин',
        'CHISLO_VALOV_KATKA' => 'Число валов',
        'OTDELENIYA_BARABANA' => 'Количество отделений барабана',
        'MAX_POTOK_VODY' => 'Максимальный поток подачи воды, л/мин',
        'DAVLENIE_VODY_BAR' => 'Давление воды, бар',
        'KLAPANA_PODACHI_VODY' => 'Клапаны подачи воды',
        'RAZMER_SLIVNYH_KLAPANOV' => 'Размер сливных клапанов (внешний), мм',
        'POTOK_SBROSA_VODY_L_MIN' => 'Поток сброса воды, л/мин',
        'VMESTIMOST_KG' => 'Вместимость, кг',
        'CHASTOTA_VRASHENIYA' => 'Частота вращения, об/мин',
        'AVTOMATICHESKAYA_OSTANOVKA' => 'Автоматическая остановка',
        'TAJMER' => 'Таймер',
        'RAZDVIZHNYE_DVERTSY_LYUKA' => 'Раздвижные дверцы люка',
        'VREMYA_REZHIM_AKVACHISTKI' => 'Время цикла аквачистки, мин',
        'RASHOD_VODY_AKVACHISTKA' => 'Потребление воды в режиме аквачистки, л',
        'GODOVOE_POTREBLENIE_ENERGII' => 'Годовое потребление электроэнергии, кВт·ч',
        'DLYA_SETEVOGO_OTELYA' => 'Для сетевого отеля',
        'TIP_IZDELIYA' => 'Тип изделия',
        'NALICHIE' => 'Наличие',
        'SOSTAV_TKANI_HLOPOK' => 'Состав ткани: хлопок, %',
        'TSVET_TKANI' => 'Цвет ткани',
        'NAZNACHENIE_POLOTENCA' => 'Назначение полотенца',
        'SOSTAV_PETELNYH_NITEJ' => 'Состав петельных нитей: хлопок, %',
        'SOSTAV_OSNOVY' => 'Состав основы: хлопок, %',
        'TIP_PETELNOY_NITI' => 'Тип петельной нити',
        'DIZAJN_POLOTENCA' => 'Дизайн полотенца',
        'TSVET_POLOTENTSA' => 'Цвет полотенца',
        'RAZMERY_POLOTENETS_SM' => 'Размеры полотенец, см',
        'POVERHNOSTNAYA_PLOTNOST' => 'Плотность, г/м²',
        'LINEJNAYA_PLOTNOST' => 'Линейная плотность',
        'NAZNACHENIE_TKANI' => 'Назначение ткани',
        'SHIRINY_TKANI_SM' => 'Ширина ткани, см',
        'DIZAJN_TKANI' => 'Дизайн ткани',
        'SOSTAV_TKANI_POLIESTER' => 'Состав ткани: полиэстер, %',
        'TIP_KROYA' => 'Тип кроя',
        'RAZMER_MUZHSKOJ' => 'Размер мужской',
        'TSVET_KOSTYUMY' => 'Цвет (костюмы)',
        'SOSTAV_TKANI_KOSTYUMY' => 'Состав ткани (костюмы)',
        'TIP_POSADKI' => 'Тип посадки',
        'RAZMER_ZHENSKIJ' => 'Размер женский',
        'TSVET_RUBASHKI' => 'Цвет (рубашки)',
        'SOSTAV_TKANI_RUB_BLUZKI' => 'Состав ткани (рубашки, блузки)',
        'TSVET_FORMA_OFITSIANTOV' => 'Цвет (форма для официантов)',
        'MODELI_OBORUDOVANIYA' => 'Совместимые модели оборудования',
        'ORIGINAL_ZAPCHASTI' => 'Оригинальное наименование запчасти',
        'ARTIKUL_ZAPCHASTI' => 'Артикул запчасти',
        'TIP_ZAPCHASTI' => 'Наименование детали',
        'UPAKOVKA_KG' => 'Упаковка, кг',
        'UPAKOVKA_HIMIYA' => 'Упаковка',
        'SREDA_ISPOLZOVANIYA' => 'Среда использования',
        'NAZNACHENIE_OBORUDOVANIYA' => 'Назначение кухонного оборудования',
        'KOLICHESTVO_UROVNEY' => 'Количество уровней',
    ];

    /**
     * Дубли: значения свойства-источника переносятся в целевое у товаров, где целевое пустое
     * (с пересчётом единиц: factor), источник после этого скрывается (ACTIVE=N, данные остаются).
     * CODE источника => [CODE цели, множитель единиц].
     */
    public const PROPERTY_MERGES = [
        'STRANA' => ['COUNTRY', null],
        'SHIRINA_VALA_MM' => ['SHIRINA_ZONY_GLAZHENIYA_MM', 1],
        'SKOROST_GLAZHKI_M_MIN' => ['SKOROST_GLAZHENIYA_M_MIN', 1],
        'POTREBLENIE_PARA_KG_CH' => ['POTREBLENIE_PARA_KG_CHAS', 1],
        'RASHOD_PARA_KG_CHAS' => ['POTREBLENIE_PARA_KG_CHAS', 1],
        'DVIGATEL_VALA_KVT' => ['MOSHHNOST_DVIGATELYA_KVT', 1],
        'DAVLENIE_PARA_KPA' => ['RABOCHEE_DAVLENIE_PARA_BAR', 0.01],
        'RABOCHEE_DAVLENIE_PARA' => ['RABOCHEE_DAVLENIE_PARA_BAR', 0.981],
        'DAVLENIE_VODY_KPA' => ['DAVLENIE_VODY_BAR', 0.01],
        'MAX_RASHOD_VOZDUHA_ATM' => ['MAX_RASHOD_VOZDUHA', 28.317],
        'VMESTIMOST_KG' => ['ZAGRUZKA_KG', 1],
    ];

    /**
     * Скрываются (ACTIVE=N — не выводятся ни в карточке, ни в фильтре; значения сохраняются):
     * дубли «Типа» и служебные/мусорные свойства.
     */
    public const DEACTIVATE = [
        'TIP_OBORUDOVANIYA',   // дубль «Типа» (TIP_TOVARA)
        'TIP_IZDELIYA',        // перенесён в «Тип» (TIP_TOVARA) текстиля
        'TEST_MIGRATION_PROPERTY',
        'NALICHIE',            // заменён STOCK_BADGE
    ];

    /**
     * Числовые свойства: тип «Список» → «Число» (ползунок «от–до» в фильтре, диапазонные теги).
     * Значение берётся из названия товара, если оно там точнее (загрузка «13,9 кг» вместо «10, 15»).
     * CODE => [DISPLAY_TYPE в фильтре (A — ползунок), шаблон извлечения из названия|null].
     */
    public const NUMERIC = [
        // «загрузка 13,9 кг», «загрузка 33 кг – 37 кг», «загрузка 2×16,7 кг» (две камеры → 33,4)
        'ZAGRUZKA_KG' => ['A', '/загрузк[аи]\s*(?:до\s*)?(?:(?<mul>\d+)\s*[×xх]\s*)?(\d+(?:[.,]\d+)?)\s*(?:кг)?(?:\s*[–\-]\s*(\d+(?:[.,]\d+)?)\s*кг)?/u'],
        'SHIRINA_ZONY_GLAZHENIYA_MM' => ['A', null],
        'G_FACTOR' => ['A', null],
    ];

    /* ===================================================================== *
     *  3. НОРМАЛИЗАЦИЯ ЗНАЧЕНИЙ СПИСКОВ
     * ===================================================================== */

    /**
     * Точечные замены значений: CODE свойства => [старое значение => новое]. Если новое уже
     * существует — варианты сливаются (товары переходят на него, старый удаляется, его XML_ID
     * пишется в историю для 301).
     */
    public const VALUE_MAP = [
        'NAPRYAZHENIE_V' => ['220 / 380' => '220/380'],
        'TIP_ZAPCHASTI' => ['Инсталятор' => 'Инсталлятор', 'Шарикоподшипник' => 'Шариковый подшипник', 'Нагревательный элемент (ТЭН)' => 'ТЭН',
            'Элемент нагревательный электрический (ТЭН)' => 'ТЭН', 'Элемент нагревательный прямой (ТЭН)' => 'ТЭН прямой',
            'Пневмоцилиндр' => 'Пневматический цилиндр', 'Пневмоклапан' => 'Пневматический клапан', 'Кольцо уплотнительное' => 'Уплотнительное кольцо',
            'Уплотнительные кольца' => 'Уплотнительное кольцо', 'Датчик уровня воды' => 'Датчик уровня', 'Температурный датчик' => 'Датчик температуры'],
        'PROIZVODITEL' => ['Фермест' => 'FERMEST', 'Greif' => 'Greiff', 'WELSPUN HOSPITALITY' => 'Welspun Hospitality'],
        'TIP_NAGREVA' => [
            'электрический' => 'Электрический',
            'паровой' => 'Паровой',
            'газовый' => 'Газовый',
            'комбинированный – электричество/пар' => 'Электрический/паровой',
            'без нагрева' => 'Без нагрева',
            'масляный' => 'Масляный (термомасло)',
        ],
        'GLAZHENIE' => ['горячее' => 'Есть', 'холодное' => 'Нет'],
        'AKVACHISTKA' => ['не предусмотрено' => 'Нет', 'опционально' => 'Опционально', 'стандартная комплектация' => 'Есть'],
        'ISTOCHNIK_PARA' => ['Внешний' => 'Внешний парогенератор', 'Встроенный' => 'Встроенный парогенератор'],
        'TIP_PREDPRIYATIYA' => ['Прачечная в гостинице' => 'Прачечная гостиницы', 'Прачечная в больнице' => 'Прачечная больницы'],
    ];

    /** «Производитель» = страна (ошибка импорта): значение переносится в COUNTRY, производитель очищается. */
    public const MANUFACTURER_IS_COUNTRY = ['Россия'];

    /* ===================================================================== *
     *  4. «ТИП» (TIP_TOVARA) — единый для оборудования, запчастей, текстиля, химии
     * ===================================================================== */

    /**
     * Группы запчастей: [название группы, SORT, регулярка по наименованию детали/товара].
     * Порядок важен — побеждает первое совпадение. SORT 900+ — после оборудования на брендах.
     */
    public const PART_GROUPS = [
        ['Ремни и шкивы', 901, '/рем[ен][нь]|шкив/u'],
        ['ТЭНы, термостаты и бойлеры', 902, '/тэн|нагреват|термостат|термореле|бойлер|теплообменник|резистор/u'],
        ['Клапаны и краны', 903, '/клапан|кран\b|кран |затвор|регулятор давления|ремкомплект парового/u'],
        ['Пневматика', 904, '/пневм|цилиндр|блок подготовки воздуха|распределитель/u'],
        ['Датчики и реле', 905, '/датчик|реле|манометр|газоанализатор|сенсор|термопар|probe|sensor/u'],
        ['Кнопки, выключатели и переключатели', 906, '/кнопк|выключател|переключател|педал|микропереключ|концевик|switch/u'],
        ['Платы, контроллеры и электроника', 907, '/плат[аы]|контроллер|панель управления|модуль|блок питания|инвертор|преобразовател|выпрямител|контактор|трансформатор|конденсатор|предохранител|стабилизатор|шлейф|кабел|разъ[её]м|контакт|батарейк|лампа|светильник|катушк|магнит|inverter|board/u'],
        ['Двигатели, редукторы и насосы', 908, '/двигател|мотор|редуктор|привод|насос|компрессор|вентилятор|крыльчатк|колесо лопастн|улитк|motor|pump/u'],
        ['Подшипники, валы и втулки', 909, '/подшипник|втулк|\bвал\b|^вал|шпонк|ступиц|опорный блок|муфт|ролик|bearing/u'],
        ['Уплотнения и прокладки', 910, '/уплотн|прокладк|кольц|сальник|манжет|мембран|seal|gasket/u'],
        ['Шланги, трубки и фитинги', 911, '/шланг|трубк|труб[аы]|патрубок|фитинг|штуцер|тройник|переходник|соединени|соединител|угольник|коллектор|врезк|водоотвод|желоб|hose|pipe/u'],
        ['Двери, стёкла и замки', 912, '/стекл|замок|двер|крышк|фиксатор|ручк|люк|door|loading/u'],
        ['Амортизаторы и пружины', 913, '/амортизатор|пружин|отбойник|подвес|трос/u'],
        ['Чехлы, ленты и покрытия', 914, '/чехол|чехл|ткань покрытия|гладильная лента|набивк|накладк|покрыти|лент[аы]|сетк|войлок/u'],
        ['Фильтры', 915, '/фильтр|filter/u'],
        ['Крепёж', 916, '/болт|винт|гайк|хомут|кронштейн|ножк|крепеж|крепёж|шайб|заглушк|угол\b|пластин/u'],
        ['Смазки, масла и химия для ТО', 917, '/смазк|масл|очистител|ароматизатор|салфетк|горюче|средство/u'],
        ['Инструмент и монтаж', 918, '/инструмент|нож|ножниц|кримпер|перфоратор|пылесос|монтаж|инсталятор|совок|щетк|щётк|пистолет/u'],
        ['Диски и рабочие органы', 919, '/диск|крестовин|корзин|барабан|абразив|тёрк|терк|лопаст|узел ополаскивания|устройство всасывания/u'],
    ];
    public const PART_GROUP_FALLBACK = ['Прочие запчасти', 950];

    /**
     * Текстиль: «Тип» по наименованию товара (первое совпадение), иначе по «Тип изделия»,
     * иначе по разделу. [тип, регулярка по названию].
     */
    public const TEXTILE_TYPES = [
        ['Наволочки', '/наволоч/u'],
        ['Пододеяльники', '/пододеял/u'],
        ['Простыни', '/простын/u'],
        ['Наматрасники', '/наматрасник/u'],
        ['Покрывала и пледы', '/покрывал|плед/u'],
        ['Комплекты постельного белья', '/комплект постельн|постельное бель/u'],
        ['Одеяла', '/одеял/u'],
        ['Подушки', '/подушк/u'],
        ['Матрасы', '/матрас/u'],
        ['Халаты', '/халат/u'],
        ['Полотенца', '/полотен/u'],
        ['Коврики', '/коврик/u'],
        ['Салфетки', '/салфетк|осибори/u'],
        ['Скатерти', '/скатерт/u'],
        ['Ткани', '/ткань|полотно|полисатин|сатин\b|перкаль|бязь/u'],
        ['Кители', '/китель|кител/u'],
        ['Фартуки', '/фартук/u'],
        ['Жилеты', '/жилет/u'],
        ['Брюки', '/брюк/u'],
        ['Юбки', '/юбк/u'],
        ['Платья', '/плать/u'],
        ['Пиджаки и жакеты', '/пиджак|жакет/u'],
        ['Рубашки и блузы', '/рубашк|блуз|сорочк/u'],
        ['Свитеры и кардиганы', '/свитер|кардиган|джемпер|пуловер/u'],
        ['Головные уборы', '/кепк|колпак|бандан|шапочк|косынк|берет/u'],
        ['Аксессуары для формы', '/пуговиц|пукл|кнопк|галстук|бабочк|шейный|платок|ремень|пояс|нарукавник|бейдж/u'],
    ];

    /** Тип изделия (TIP_IZDELIYA) → «Тип», если по названию не определился. */
    public const TEXTILE_TIP_IZDELIYA = [
        'Брюки' => 'Брюки', 'Рубашка/блузка' => 'Рубашки и блузы', 'Фартук для официанта' => 'Фартуки',
        'Фартук для сомелье' => 'Фартуки', 'Поварской китель' => 'Кители', 'Пиджак' => 'Пиджаки и жакеты',
        'Аксессуары' => 'Аксессуары для формы', 'Жилет' => 'Жилеты', 'Простыня' => 'Простыни', 'Свитер' => 'Свитеры и кардиганы',
        'Пуговицы/пукли/кнопки' => 'Аксессуары для формы', 'Полотенце' => 'Полотенца', 'Юбка' => 'Юбки',
        'Пододеяльник' => 'Пододеяльники', 'Наволочка' => 'Наволочки', 'Подушка' => 'Подушки',
        'Платье' => 'Платья', 'Одеяло' => 'Одеяла',
    ];

    /** SORT вариантов «Типа» для текстиля (после оборудования, до аксессуаров кухни). */
    public const TEXTILE_TYPE_SORT = 600;

    /* ===================================================================== *
     *  5. «ТИП ПРЕДПРИЯТИЯ» — правила вместо «отмечено всё»
     * ===================================================================== */

    /**
     * Правила по «Типу» (значение TIP_TOVARA) и загрузке (кг). Для каждого типа объекта —
     * список условий; товар получает тип объекта, если подходит хотя бы под одно условие.
     * Условие: ['types' => [...TIP_TOVARA], 'min' => кг, 'max' => кг, 'width_max' => мм, 'aqua' => true].
     * Товары, чей «Тип» не упомянут ни в одном правиле (комплекты, вспомогательное), не трогаются.
     */
    public static function enterpriseRules(): array
    {
        $wash = ['Высокоскоростные стиральные машины', 'Низко- и среднескоростные стиральные машины'];
        $dry = ['Сушильные машины', 'Сушильные машины с функцией «Аквачистки»'];
        $smallCombo = ['Стирально-сушильные машины', 'Стирально-сушильные колонны (тандем)'];
        $iron = ['Гладильные катки', 'Гладильные каландры'];
        $finish = ['Пароманекены и парокабинеты', 'Гладильные столы', 'Гладильные прессы', 'Обработка фасонных изделий', 'Парогенераторы'];

        return [
            'Мини-прачечная' => [
                ['types' => $wash, 'max' => 25], ['types' => $dry, 'max' => 30], ['types' => $smallCombo],
                ['types' => ['Гладильные катки'], 'width_max' => 2100], ['types' => ['Гладильные столы']],
                ['types' => ['Центрифуги'], 'max' => 25], ['types' => ['Сушильные кабинеты']],
            ],
            'Прачечная самообслуживания' => [
                ['types' => $wash, 'max' => 20], ['types' => ['Сушильные машины'], 'max' => 25], ['types' => $smallCombo],
            ],
            'Прачечная гостиницы' => [
                ['types' => $wash, 'min' => 10, 'max' => 60], ['types' => $dry, 'min' => 10, 'max' => 60],
                ['types' => ['Барьерные стиральные машины'], 'max' => 60], ['types' => $iron, 'width_max' => 3300],
                ['types' => ['Складыватели и штабелеры', 'Подающие машины и сепараторы', 'Транспортировка белья', 'Гладильные столы']],
            ],
            'Прачечная больницы' => [
                ['types' => ['Барьерные стиральные машины']], ['types' => $wash, 'min' => 20, 'max' => 120],
                ['types' => $dry, 'min' => 20, 'max' => 120], ['types' => $iron],
                ['types' => ['Складыватели и штабелеры', 'Транспортировка белья', 'Сортировка белья', 'Туннельные стиральные машины']],
            ],
            'Промышленная прачечная' => [
                ['types' => $wash, 'min' => 40], ['types' => ['Барьерные стиральные машины'], 'min' => 40],
                ['types' => $dry, 'min' => 40], ['types' => ['Туннельные стиральные машины', 'Гладильные каландры',
                    'Складыватели и штабелеры', 'Подающие машины и сепараторы', 'Сортировка белья', 'Транспортировка белья']],
                ['types' => ['Центрифуги'], 'min' => 40],
            ],
            'Прачечная на предприятии' => [
                ['types' => $wash, 'min' => 20, 'max' => 110], ['types' => ['Барьерные стиральные машины'], 'max' => 110],
                ['types' => $dry, 'min' => 20, 'max' => 110], ['types' => ['Гладильные катки', 'Гладильные столы', 'Пароманекены и парокабинеты']],
            ],
            'Коммерческая прачечная' => [
                ['types' => $wash, 'min' => 10, 'max' => 60], ['types' => $dry, 'min' => 10, 'max' => 60],
                ['types' => $iron, 'width_max' => 3300], ['types' => ['Складыватели и штабелеры', 'Гладильные столы',
                    'Пароманекены и парокабинеты', 'Упаковочное оборудование', 'Транспортировка белья']],
            ],
            'Химчистка' => [
                ['types' => ['Машины химчистки', 'Пятновыводные столы и кабины', 'Упаковочное оборудование', 'Сушильные кабинеты']],
                ['types' => $finish], ['types' => array_merge($wash, $dry), 'aqua' => true],
            ],
            'Аквачистка' => [
                ['types' => array_merge($wash, $dry), 'aqua' => true], ['types' => ['Сушильные машины с функцией «Аквачистки»']],
                ['types' => ['Пароманекены и парокабинеты', 'Гладильные столы', 'Гладильные прессы', 'Пятновыводные столы и кабины']],
            ],
        ];
    }

    /** «Типы», к которым применяются правила (остальным «Тип предприятия» не пересчитывается). */
    public static function enterpriseRuleTypes(): array
    {
        $types = [];
        foreach (self::enterpriseRules() as $conditions) {
            foreach ($conditions as $c) {
                foreach ($c['types'] as $t) {
                    $types[$t] = true;
                }
            }
        }
        return array_keys($types);
    }

    /**
     * «Тип предприятия» кухонного оборудования (2026-10-21, согласовано с заказчиком): короткий ключ → значение.
     * Варианты живут в том же свойстве TIP_PREDPRIYATIYA, что и прачечные (сквозной фильтр); в фильтре
     * раздела видны только значения его товаров. Порядок — SORT вариантов (после прачечных 500).
     */
    public const KITCHEN_ENTERPRISE = [
        'R' => 'Для ресторана', 'H' => 'Для отеля', 'S' => 'Для столовой', 'F' => 'Для фаст-фуда', 'B' => 'Для больницы',
        'K' => 'Для кафе и кофейни', 'BAR' => 'Для бара', 'P' => 'Для пиццерии', 'C' => 'Для кейтеринга',
        'SH' => 'Для школы и детского сада', 'D' => 'Для частного дома',
    ];

    /**
     * Правила для кухни: код подраздела → ключи KITCHEN_ENTERPRISE. Товар получает объединение правил
     * всех своих подразделов (без «Популярного» и родителей без правила), затем правки по названию из
     * kitchenEnterpriseOverrides() — по порядку, '=' заменяет набор, '+' добавляет, '-' убирает.
     */
    public const KITCHEN_ENTERPRISE_SECTIONS = [
        'parokonvektomaty' => ['R', 'H', 'S', 'B', 'C', 'SH'],
        'parokonvektomaty-rational' => ['R', 'H', 'S', 'B', 'C', 'SH'],
        'aksessuary' => ['R', 'H', 'S', 'B', 'C', 'SH'],
        'elektroskovorody' => ['R', 'H', 'S', 'B', 'C', 'SH'],
        'teplovoe-oborudovanie' => ['H', 'S', 'B', 'C', 'SH'],
        'elektricheskie-plity' => ['R', 'H', 'S', 'B', 'C', 'SH', 'K'],
        'gazovye-plity' => ['R', 'H', 'S', 'B', 'C', 'SH', 'K'],
        'induktsionnye-plity' => ['R', 'H', 'S', 'B', 'C', 'SH', 'K'],
        'varochnye-poverhnosti' => ['R', 'H', 'K', 'D'],
        'induktsionnye-grili' => ['R', 'H', 'F', 'K'],
        'zharochnye-poverhnosti' => ['R', 'H', 'F', 'K'],
        'frityurnitsiy' => ['R', 'H', 'F', 'K'],
        'pechi-dlya-pitstsy' => ['P', 'R', 'K'],
        'holodilnye-i-morozilnye-stoly' => ['R', 'H', 'S', 'F', 'K', 'P', 'C'],
        'holodilnye-shkafy' => ['R', 'H', 'S', 'F', 'K', 'B', 'SH', 'C'],
        'shkafy-shokovoj-zamorozki' => ['R', 'H', 'S', 'B', 'C', 'SH'],
        'defrostery' => ['R', 'S', 'B', 'C'],
        'kuhonnye-ostrova-i-monobloki' => ['R', 'H', 'S', 'C'],
        'linii-razdachi' => ['S', 'B', 'SH', 'H', 'C'],
        'bufetnye-stantsii' => ['H', 'R', 'C'],
        'holodilnye-vitriny' => ['K', 'R', 'H'],
        'nastolnye-vitriny' => ['K', 'R', 'H', 'BAR'],
        'neytralnye-vitriny' => ['K', 'R', 'H', 'S'],
        'teplovye-vitriny' => ['H', 'S', 'K', 'F', 'R'],
        'vitriny-dlya-sushi' => ['R', 'K'],
        'mobilnye-kuhonnye-stantsii' => ['H', 'C', 'R'],
        'diet-pitanie-tablet-pitanie' => ['B', 'C'],
        'posudomoechnoe-oborudovanie' => ['R', 'H', 'S', 'B', 'SH', 'C'],
        'ldogeneratory' => ['BAR', 'R', 'H', 'K', 'F'],
        'barnye-stojki' => ['BAR', 'R', 'H', 'K'],
        'barnye-stantsii' => ['BAR', 'R', 'H', 'K'],
        'vinnye-biblioteki-komnaty-i-pogreba' => ['R', 'H', 'BAR'],
        'ventiliruemye-potolki' => ['R', 'H', 'S', 'B', 'SH', 'C'],
        'vytyazhnye-zonty' => ['R', 'H', 'S', 'B', 'SH', 'C', 'F'],
        // нейтральное оборудование (моечные ванны, производственные столы) — любая коммерческая кухня
        'neytralnoe-oborudovanie' => ['R', 'H', 'S', 'F', 'B', 'K', 'P', 'C', 'SH'],
        'moyushchie-vanny' => ['R', 'H', 'S', 'F', 'B', 'K', 'P', 'C', 'SH'],
        'proizvodstvennye-stoly' => ['R', 'H', 'S', 'F', 'B', 'K', 'P', 'C', 'SH'],
    ];

    /** Правки по названию товара: [регулярка, '='|'+'|'-', ключи]. */
    public static function kitchenEnterpriseOverrides(): array
    {
        return [
            // компактные пароконвектоматы и сковороды — для небольших кухонь
            ['/6-1\/1|6-2\/3|\bXS\b|Junior|Компактн|FlexiCombi (Classic|MagicPilot) 6\.1|SpaceCombi/u', '+', ['K', 'F']],
            // кондитерские шкафы и шкаф для мороженого — не для пищеблоков
            ['/Coldline (Wind|Clima|Ice)/u', '=', ['R', 'H', 'K']],
            ['/Coldline Pastry/u', '=', ['R', 'H', 'K', 'C']],
            ['/Saladett|Саладетт|Prep Station/u', '+', ['P', 'F']],
            // грили B-Fire: уличные/встраиваемые — и для частного дома, портативный — для выездных мероприятий
            ['/B-Fire/u', '+', ['D']],
            ['/Kutuzov Mini/u', '+', ['C']],
            ['/Саламандра/u', '-', ['K']],
            // острова: MOLTENI — ресторан и частная кухня, EMAINOX — ресторан/отель
            ['/MOLTENI/u', '=', ['R', 'H', 'D']],
            ['/Кухонный остров EMAINOX/u', '=', ['R', 'H']],
            // линии раздачи: шведский стол — отели/рестораны/кейтеринг; школьные прилавки; фаст-гурме
            ['/шведского стола|Widevision|Suprême|DOGE/u', '=', ['H', 'R', 'C']],
            ['/Kids/u', '=', ['SH', 'S']],
            ['/Fast Gourmet/u', '=', ['F', 'K', 'R']],
            ['/Mondrian/u', '=', ['K', 'R', 'H']],
            ['/ELECTROLUX (Fit System|Self-service|Server)|BLANCO MANHATTAN|B\.PRO BASIC/u', '=', ['S', 'SH', 'B', 'H']],
            // витрины SAYL: Grab & Go и компактные — фаст-фуд; встраиваемые/напольные — столовые
            ['/Grab & Go|COMPAK/u', '+', ['F']],
            ['/INTEGRA (DROP-IN|BASE|T)\b/u', '+', ['S']],
            ['/PORTÁTIL/u', '+', ['C']],
            // таблет-питание
            ['/MenuMobil/u', '=', ['B']],
            ['/Socamel/u', '=', ['B', 'H']],
            ['/Cambro/u', '=', ['C', 'SH', 'S', 'B']],
            // посудомойки по типу машины
            ['/стаканомоечн/ui', '=', ['BAR', 'R', 'K', 'H']],
            ['/фронтальн|подстольн/ui', '=', ['R', 'K', 'F', 'P', 'BAR']],
            ['/купольн/ui', '=', ['R', 'H', 'S', 'K', 'F']],
            ['/конвейерн|туннельн/ui', '=', ['S', 'B', 'SH', 'H', 'C']],
            // винные шкафы бытового формата — и для частного дома
            ['/LIEBHERR|BRERA/u', '+', ['D']],
        ];
    }

    /** Значения «Типа предприятия» для кухонного товара по его подразделам и названию. */
    public static function kitchenEnterprise(array $sectionCodes, string $name): array
    {
        $keys = [];
        foreach ($sectionCodes as $code) {
            foreach (self::KITCHEN_ENTERPRISE_SECTIONS[$code] ?? [] as $k) {
                $keys[$k] = true;
            }
        }
        if (!$keys) {
            return [];
        }
        foreach (self::kitchenEnterpriseOverrides() as [$re, $op, $list]) {
            if (!preg_match($re, $name)) {
                continue;
            }
            if ($op === '=') {
                $keys = [];
            }
            foreach ($list as $k) {
                if ($op === '-') {
                    unset($keys[$k]);
                } else {
                    $keys[$k] = true;
                }
            }
        }
        // в порядке словаря
        return array_values(array_intersect_key(self::KITCHEN_ENTERPRISE, $keys));
    }

    /* ===================================================================== *
     *  6. ФИЛЬТРЫ ПО РАЗДЕЛАМ
     * ===================================================================== */

    /**
     * Набор и порядок свойств умного фильтра по разделу. Ищется по коду раздела, затем по
     * родителям вверх; не найдено — фильтр как раньше (кухня и пр. не затрагиваются).
     * Пустые/одно-значные свойства фильтр всё равно не показывает.
     */
    public const FILTERS = [
        // корень — только общие фильтры; технические характеристики — в подразделах ниже (2026-10-20)
        'prachechnoe-oborudovanie' => ['COUNTRY', 'PROIZVODITEL', 'TIP_PREDPRIYATIYA'],
        'stiralnye-mashiny' => ['TIP_TOVARA', 'PROIZVODITEL', 'ZAGRUZKA_KG', 'TIP_PREDPRIYATIYA', 'TIP_NAGREVA', 'NAPRYAZHENIE_V', 'G_FACTOR', 'COUNTRY'],
        'sushilnoe-oborudovanie' => ['TIP_TOVARA', 'PROIZVODITEL', 'ZAGRUZKA_KG', 'TIP_NAGREVA', 'TIP_PREDPRIYATIYA', 'AKVACHISTKA', 'REVERS_BARABANA', 'NAPRYAZHENIE_V', 'COUNTRY'],
        'gladilnoe-oborudovanie' => ['TIP_TOVARA', 'PROIZVODITEL', 'SHIRINA_ZONY_GLAZHENIYA_MM', 'TIP_NAGREVA', 'TIP_PREDPRIYATIYA', 'MOSHHNOST_DVIGATELYA_KVT',
            'VYPARIVAYUSHAYA_SPOSOBNOST', 'NAPRYAZHENIE_V', 'COUNTRY'],
        'professionalnye-skladyvateli' => ['PROIZVODITEL', 'NAZNACHENIE', 'FUNKTSIYA_SHTABELIROVANIYA', 'TIP_PREDPRIYATIYA', 'COUNTRY'],
        'podayushhie-ustrojstva' => ['PROIZVODITEL', 'CHISLO_STANTSIJ_PODACHI', 'COUNTRY'],
        'finishnoe-oborudovanie' => ['TIP_TOVARA', 'PROIZVODITEL', 'NAZNACHENIE', 'ISTOCHNIK_PARA', 'TIP_NAGREVA', 'TIP_PREDPRIYATIYA', 'NAPRYAZHENIE_V', 'COUNTRY'],
        'gladilnye-stoly' => ['PROIZVODITEL', 'TIP_PLITY', 'GLAZHENIE', 'BOJLER', 'RUKAV', 'UTYUG_V_KOMPLEKTE', 'REGULIROVANIE_STOLESHNICY', 'NAPRYAZHENIE_V', 'COUNTRY'],
        'paromanekeny' => ['PROIZVODITEL', 'NAZNACHENIE', 'ISTOCHNIK_PARA', 'NAPRYAZHENIE_V', 'COUNTRY'],
        'parogeneratory' => ['PROIZVODITEL', 'NAPRYAZHENIE_V', 'COUNTRY'],
        'oborudovanie-dlya-himchistki' => ['TIP_TOVARA', 'PROIZVODITEL', 'ZAGRUZKA_KG', 'RASTVORITEL', 'KOLICHESTVO_BAKOV', 'UDALENIE_PYATEN', 'NAPRYAZHENIE_V', 'COUNTRY'],
        'vspomogatelnoe-oborudovanie' => ['TIP_TOVARA', 'PROIZVODITEL', 'NAZNACHENIE', 'COUNTRY'],
        'upakovochnoe-oborudovanie' => ['PROIZVODITEL', 'TIP_PREDPRIYATIYA', 'COUNTRY'],
        'komplekty-prachechnogo-oborudovaniya' => ['PROIZVODITEL', 'TIP_PREDPRIYATIYA'],
        'prachechnoe-oborudovanie-v-nalichii' => ['TIP_TOVARA', 'PROIZVODITEL', 'ZAGRUZKA_KG', 'TIP_NAGREVA'],
        'zapasnye-chasti' => ['TIP_TOVARA', 'PROIZVODITEL', 'MODELI_OBORUDOVANIYA', 'MATERIAL', 'COUNTRY'],
        'professionalnyj-tekstil' => ['TIP_TOVARA', 'PROIZVODITEL', 'MATERIAL', 'POVERHNOSTNAYA_PLOTNOST', 'TKAN', 'KOLLEKTSIYA', 'TIP_KROYA', 'TIP_POSADKI',
            'RAZMER_MUZHSKOJ', 'RAZMER_ZHENSKIJ', 'TSVET_KOSTYUMY', 'TSVET_TKANI', 'SOSTAV_TKANI_HLOPOK', 'LINEJNAYA_PLOTNOST', 'DIZAJN_TKANI',
            'RAZMERY_MATRASA', 'ZHESTKOST', 'TIP_MATRASA', 'COUNTRY'],
        'professionalnaya-himiya' => ['TIP_TOVARA', 'PROIZVODITEL', 'COUNTRY'],
    ];

    /**
     * Наборы только для самого раздела, без наследования подразделами (у подразделов кухни свои
     * технические фильтры по умолчанию). 2026-10-21: корень кухни — как корень прачечного.
     */
    public const FILTERS_SELF = [
        'oborudovanie-professionalnoj-kuhni' => ['COUNTRY', 'PROIZVODITEL', 'TIP_PREDPRIYATIYA'],
    ];

    /** Свернутые по умолчанию — после этого количества блоков фильтра. */
    public const FILTER_EXPANDED_COUNT = 4;

    /** Код раздела → список свойств фильтра (с учётом родителей) или null. */
    public static function filterCodes(string $sectionCode): ?array
    {
        if (isset(self::FILTERS_SELF[$sectionCode])) {
            return self::FILTERS_SELF[$sectionCode];
        }
        $code = $sectionCode;
        for ($i = 0; $i < 6 && $code !== ''; $i++) {
            if (isset(self::FILTERS[$code])) {
                return self::FILTERS[$code];
            }
            $code = self::parentCode($code);
        }
        return null;
    }

    public static function parentCode(string $sectionCode): string
    {
        static $cache = [];
        if (!array_key_exists($sectionCode, $cache)) {
            $cache[$sectionCode] = '';
            $iblockId = (int)GetIBlockIDByCode('catalog');
            $section = \CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $sectionCode], false, ['ID', 'IBLOCK_SECTION_ID'])->Fetch();
            if ($section && (int)$section['IBLOCK_SECTION_ID'] > 0) {
                $parent = \CIBlockSection::GetList([], ['ID' => (int)$section['IBLOCK_SECTION_ID']], false, ['CODE'])->Fetch();
                $cache[$sectionCode] = (string)($parent['CODE'] ?? '');
            }
        }
        return $cache[$sectionCode];
    }

    /* ===================================================================== *
     *  7. СТАРЫЕ АДРЕСА → 301
     * ===================================================================== */

    /**
     * Точные старые адреса (сайт на WordPress и прежняя структура) → новый адрес. Цель «tag:<раздел>/<тег>»
     * — тег, если он есть и активен, иначе раздел. Шаблонные адреса (countryproduct-*, manufacter-*,
     * вложенные пути, расформированные разделы) — в legacyRedirect().
     */
    public const LEGACY_URLS = [
        '/product-category/stiralnye-mashiny-100-kg/' => 'tag:stiralnye-mashiny/100-kg',
        '/product-category/stiralnye-mashiny-50-kg/' => 'tag:stiralnye-mashiny/50-kg',
        '/product-category/stiralnye-mashiny-25-kg/' => 'tag:stiralnye-mashiny/25-kg',
        '/product-category/stiralnye-mashiny-20-kg/' => 'tag:stiralnye-mashiny/20-kg',
        '/product-category/stiralnye-mashiny-10-kg/' => 'tag:stiralnye-mashiny/10-kg',
        '/product-category/alpha/' => '/brends/alpha/',
        '/product-category/prachechnoe-oborudovanie/po-tipam-predpriyatij/industrialnaya-prachechnaya/' => 'tag:prachechnoe-oborudovanie/dlya-promyshlennoj-prachechnoj',
        '/product-category/prachechnoe-oborudovanie/po-tipam-predpriyatij/akvachistka/' => 'tag:prachechnoe-oborudovanie/dlya-akvachistki',
        '/product-category/prachechnoe-oborudovanie/stiralnye-mashiny/vysokoskorostnye-stiralnye-mashiny-dlya-stirki-mopov/' => 'tag:vysokoskorostnye-stiralnye-mashiny/dlya-stirki-mopov',
        '/product-category/prachechnoe-oborudovanie/aksessuary/' => '/product-category/vspomogatelnoe-oborudovanie/',
        '/product-category/oborudovanie-professionalnoj-kuhni/kuhonnoe-oborudovanie-v-nalichii/' => '/product-category/oborudovanie-professionalnoj-kuhni/',
        '/product-category/tekstil/forma-dlya-personala/' => '/product-category/forma-dlya-personala/',
        '/product-category/oborudovanie-dlya-himchistki/' => null, // теперь это настоящий раздел — не редиректим
    ];

    /** Новый адрес для старого или null. */
    public static function legacyRedirect(string $path): ?string
    {
        if (!str_starts_with($path, '/product-category/')) {
            return null;
        }
        $path = rtrim($path, '/') . '/';
        if (array_key_exists($path, self::LEGACY_URLS)) {
            return self::resolveTarget(self::LEGACY_URLS[$path]);
        }
        $segments = array_values(array_filter(explode('/', substr($path, strlen('/product-category/')))));
        if (!$segments) {
            return null;
        }
        // расформированные разделы (с любым хвостом)
        if (isset(self::DISSOLVE_SECTIONS[$segments[0]])) {
            return self::DISSOLVE_SECTIONS[$segments[0]];
        }
        if (count($segments) < 2 || $segments[1] === 'f') {
            return null;
        }
        $last = end($segments);
        // старые подборки WordPress: /<раздел>/countryproduct-<страна>/, /<раздел>/manufacter-<бренд>/
        if (preg_match('/^(countryproduct|manufacter)-(.+)$/', $last, $m)) {
            $sectionCode = self::activeSectionCode($segments[count($segments) - 2]) ?? self::activeSectionCode($segments[0]);
            if ($sectionCode === null) {
                return null;
            }
            $prop = $m[1] === 'countryproduct' ? 'country' : 'proizvoditel';
            if (class_exists('SeoTags') && ($tag = SeoTags::forFilterValue($sectionCode, $prop, $m[2]))) {
                return SeoTags::url($tag);
            }
            return '/product-category/' . $sectionCode . '/';
        }
        // старые подборки загрузки WordPress: /<раздел>/zagruzka-kg-60/ → тег-диапазон («60 кг»), иначе раздел
        if (preg_match('/^zagruzka-kg-(\d+(?:-\d+)?)$/', $last, $m)) {
            $sectionCode = self::activeSectionCode($segments[count($segments) - 2]);
            if ($sectionCode === null) {
                return null;
            }
            if (class_exists('SeoTags') && ($tag = SeoTags::forNumericValue($sectionCode, 'ZAGRUZKA_KG', (float)str_replace('-', '.', $m[1])))) {
                return SeoTags::url($tag);
            }
            return '/product-category/' . $sectionCode . '/';
        }
        // вложенные пути прежней структуры: /prachechnoe-oborudovanie/finishnoe-oborudovanie/paromanekeny/ → раздел по последнему сегменту
        if (count($segments) >= 2 && !(class_exists('SeoTags') && count($segments) === 2 && SeoTags::find($segments[0], $segments[1]))) {
            for ($i = count($segments) - 1; $i >= 1; $i--) {
                if (($code = self::activeSectionCode($segments[$i])) !== null) {
                    return '/product-category/' . $code . '/';
                }
                if (isset(self::DISSOLVE_SECTIONS[$segments[$i]])) {
                    return self::DISSOLVE_SECTIONS[$segments[$i]];
                }
            }
            // старая вложенная структура WordPress, конечного раздела больше нет — в корневой раздел
            if (in_array($segments[0], self::LEGACY_ROOTS, true) && ($root = self::activeSectionCode($segments[0])) !== null) {
                return '/product-category/' . $root . '/';
            }
        }
        return null;
    }

    /** Корневые разделы, у которых на WordPress были вложенные адреса. */
    private const LEGACY_ROOTS = ['prachechnoe-oborudovanie', 'professionalnyj-tekstil', 'professionalnaya-himiya',
        'oborudovanie-professionalnoj-kuhni', 'zapasnye-chasti', 'mebel'];

    private static function resolveTarget(?string $target): ?string
    {
        if ($target === null) {
            return null;
        }
        if (str_starts_with($target, 'tag:')) {
            [$section, $slug] = explode('/', substr($target, 4), 2);
            if (class_exists('SeoTags') && ($tag = SeoTags::find($section, $slug))) {
                return SeoTags::url($tag);
            }
            return '/product-category/' . $section . '/';
        }
        return $target;
    }

    private static function activeSectionCode(string $code): ?string
    {
        static $cache = [];
        if (!array_key_exists($code, $cache)) {
            $s = \CIBlockSection::GetList([], ['IBLOCK_ID' => (int)GetIBlockIDByCode('catalog'), '=CODE' => $code, 'ACTIVE' => 'Y', 'GLOBAL_ACTIVE' => 'Y'], false, ['CODE'])->Fetch();
            $cache[$code] = $s ? $s['CODE'] : null;
        }
        return $cache[$code];
    }

    /* ===================================================================== *
     *  Утилиты
     * ===================================================================== */

    /** Слаг для XML_ID варианта списка / URL тега. */
    public static function slug(string $value): string
    {
        $value = str_replace(['/', '+', '×', '«', '»', '%'], [' ', ' plus ', 'x', '', '', ' pct'], $value);
        $slug = \CUtil::translit(trim($value), 'ru', ['replace_space' => '-', 'replace_other' => '-', 'max_len' => 80, 'change_case' => 'L']);
        $slug = trim(preg_replace('/-+/', '-', $slug), '-');
        return $slug !== '' ? $slug : 'v';
    }

    /** Число из строки «< 63,7», «13,9», «20-1450» (берётся максимум диапазона) или null. */
    public static function parseNumber(string $value): ?float
    {
        $value = str_replace([' ', ' '], '', $value);
        if (!preg_match_all('/\d+(?:[.,]\d+)?/', $value, $m)) {
            return null;
        }
        $numbers = array_map(static fn($n) => (float)str_replace(',', '.', $n), $m[0]);
        return max($numbers);
    }

    /** Группа запчасти по наименованию детали (TIP_ZAPCHASTI) и/или названию товара. */
    public static function partGroup(string $detail, string $productName): array
    {
        foreach ([$detail, $productName] as $text) {
            $text = mb_strtolower(trim($text));
            if ($text === '') {
                continue;
            }
            foreach (self::PART_GROUPS as [$group, $sort, $re]) {
                if (preg_match($re, $text)) {
                    return [$group, $sort];
                }
            }
        }
        return self::PART_GROUP_FALLBACK;
    }

    /** Тип текстиля по названию / «Типу изделия»; null — определять по разделу. */
    public static function textileType(string $productName, string $tipIzdeliya): ?string
    {
        $name = mb_strtolower($productName);
        foreach (self::TEXTILE_TYPES as [$type, $re]) {
            if (preg_match($re, $name)) {
                return $type;
            }
        }
        return self::TEXTILE_TIP_IZDELIYA[$tipIzdeliya] ?? null;
    }
}
