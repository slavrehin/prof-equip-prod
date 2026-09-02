<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Application;

class BrandsComplexComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль инфоблоков не установлен');
            return;
        }

        $componentPage = $this->getPage();

        // Определяем страницу
        $componentPage = $this->getPage();

        // Если страница не определена — 404
        if (!$componentPage) {
            $this->handle404();
            return;
        }

        // Сохраняем переменные для шаблона
        $this->arResult['VARIABLES'] = $this->arResult['VARIABLES'] ?? [];

        // Подключаем шаблон
        $this->IncludeComponentTemplate($componentPage);
    }

protected function getPage()
{
    $request = Application::getInstance()->getContext()->getRequest();
    $curPage = $request->getRequestUri();
    $curPage = strtok($curPage, '?');
    
    
    $folder = rtrim($this->arParams['SEF_FOLDER'] ?? '/brends/', '/');
    
    if (strpos($curPage, $folder) === 0) {
        $relativePath = substr($curPage, strlen($folder));
    } else {
        $relativePath = $curPage;
    }
    
    $relativePath = trim($relativePath, '/');
    
    $pathParts = explode('/', $relativePath);
    
    // Список
    if (empty($relativePath)) {
        return 'news';
    }
    
    // Фильтр
    if (in_array('f', $pathParts)) {
        $key = array_search('f', $pathParts);
        if ($key > 0) {
            // Код элемента — всё до /f/
            $elementCode = implode('/', array_slice($pathParts, 0, $key));
            // Путь фильтра — всё после /f/
            $filterParts = array_slice($pathParts, $key + 1);
            $filterPath = implode('/', $filterParts);
            
            // Сохраняем В ОБА МЕСТА для надёжности
            $this->arResult['VARIABLES']['ELEMENT_CODE'] = $elementCode;
            $this->arResult['VARIABLES']['SMART_FILTER_PATH'] = $filterPath;
            
            // Дублируем в корень arResult для удобства
            $this->arResult['ELEMENT_CODE'] = $elementCode;
            $this->arResult['SMART_FILTER_PATH'] = $filterPath;
            
            // Также передаём в глобальные REQUEST для совместимости
            $_REQUEST['ELEMENT_CODE'] = $elementCode;
            $_REQUEST['SMART_FILTER_PATH'] = $filterPath;
            
            if ($this->checkElementExists($elementCode)) {
                return 'detail_with_filter';
            }
        }
        return false;
    }
    
    // Детальная страница (один сегмент)
    if (count($pathParts) == 1) {
        $elementCode = $pathParts[0];
        
        $this->arResult['VARIABLES']['ELEMENT_CODE'] = $elementCode;
        
        // Проверяем существование
        $exists = $this->checkElementExists($elementCode);
        
        if ($exists) {
            return 'detail';
        } else {
            return false;
        }
    }

    // 2+ сегментов без '/f/' не соответствуют ни одному реальному шаблону
    // страницы (news/detail/detail_with_filter) — раньше здесь по ошибке
    // возвращался 'news', и любой такой URL молча показывал список брендов
    // вместо 404.
    return false;
}


    protected function checkElementExists($code)
    {
        if (empty($code) || empty($this->arParams['IBLOCK_ID'])) {
            return false;
        }

        $exists = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
                'CODE' => $code,
                'ACTIVE' => 'Y'
            ],
            false,
            ['nTopCount' => 1],
            ['ID']
        )->Fetch();

        return !empty($exists);
    }

    protected function handle404()
    {
        // Страница уже открыта через /brends/index.php, которое подключает
        // bitrix/header.php ДО вызова компонента. Раньше здесь включали
        // /404.php целиком (он сам ещё раз подключает header.php и footer.php)
        // и сразу die() — вложенный второй header.php внутри уже открытой
        // страницы ломал буфер вывода и обрезал ответ ДО того, как
        // CHTTP::SetStatus фактически применялся: клиент получал пустое тело
        // со статусом 200 вместо страницы 404. Вместо этого рендерим тот же
        // блок, что и /404.php, прямо здесь и даём странице договорить до
        // /bitrix/footer.php как обычно — так же, как это делают штатные
        // компоненты (bitrix:news.detail и т.п.) при SET_STATUS_404.
        if ($this->arParams['SET_STATUS_404'] === 'Y') {
            \CHTTP::SetStatus("404 Not Found");
        }

        define('ERROR_404', 'Y');

        if ($this->arParams['SHOW_404'] === 'Y') {
            global $APPLICATION;
            $APPLICATION->SetTitle('404 Not Found');
            ?>
            <section class="not-found-content">
                <div class="not-found-content__inner container">
                    <div class="title-block">
                        <h1 class="title-block__title">Страница не найдена</h1>
                        <?$APPLICATION->IncludeComponent(
                            "bitrix:breadcrumb",
                            "",
                            array(
                                "START_FROM" => "0",
                                "PATH" => "",
                                "SITE_ID" => "s1"
                            )
                        ); ?>
                    </div>
                    <form class="not-found-content__form" action="/search/">
                        <h4 class="form__title">Ой! Эта страница не найдена.</h4>
                        <p class="form__descr">It looks like nothing was found at this location. Try using the search box below:</p>
                        <div class="input-wrapper"><input name="search" placeholder="Для поиска нажмите Enter …" name="s"><button class="btn search__btn"><svg>
                                    <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#serch2"></use>
                                </svg></button></div>
                    </form>
                </div>
            </section>
            <?
        }
    }
}