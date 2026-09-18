<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$this->setFrameMode(true);

if (empty($arResult["ITEMS"])) return;

// Раньше <picture><source type="image/webp"> указывал на те же .png/.jpg-файлы,
// что и обычный <img> — реального WebP не было (браузер декодировал PNG под
// видом webp по сигнатуре файла, экономии в весе не было вообще). Здесь WebP
// генерируется по-настоящему из уже отрезайженного PNG/JPG и кэшируется рядом
// с resize_cache, в отдельной upload/webp_cache/ (resize_cache на test3
// смонтирован read-only из прода, писать в него нельзя).
if (!function_exists('profequipGetWebpVariant')) {
    function profequipGetWebpVariant(string $sourceRelUrl): ?string
    {
        // Источник обязан быть уже отрезайженным файлом из resize_cache — если
        // CFile::ResizeImageGet не смог создать нужный размер (например, у
        // /upload/iblock/ нет прав на запись — так и есть на test3, где эта
        // папка смонтирована read-only из прода) и откатился на оригинал,
        // $sourceRelUrl будет указывать прямо в /upload/iblock/. Писать .webp
        // рядом с оригиналом в чужую папку — не только неправильно по месту
        // хранения, но и гарантированно упадёт там же, где не пишет resize_cache.
        if (strpos($sourceRelUrl, '/resize_cache/') === false) {
            return null;
        }

        $sourceAbsPath = $_SERVER['DOCUMENT_ROOT'] . $sourceRelUrl;
        if (!is_file($sourceAbsPath)) {
            return null;
        }

        $webpRelUrl = preg_replace('#/resize_cache/#', '/webp_cache/', $sourceRelUrl, 1);
        $webpRelUrl = preg_replace('/\.(png|jpe?g)$/i', '.webp', $webpRelUrl);
        if ($webpRelUrl === null || $webpRelUrl === $sourceRelUrl) {
            return null;
        }

        $webpAbsPath = $_SERVER['DOCUMENT_ROOT'] . $webpRelUrl;

        if (is_file($webpAbsPath)) {
            return $webpRelUrl;
        }

        $webpDir = dirname($webpAbsPath);
        if (!is_dir($webpDir) && !mkdir($webpDir, 0755, true) && !is_dir($webpDir)) {
            return null;
        }

        $imageInfo = @getimagesize($sourceAbsPath);
        if (!$imageInfo) {
            return null;
        }

        switch ($imageInfo[2]) {
            case IMAGETYPE_PNG:
                $im = @imagecreatefrompng($sourceAbsPath);
                break;
            case IMAGETYPE_JPEG:
                $im = @imagecreatefromjpeg($sourceAbsPath);
                break;
            default:
                return null;
        }

        if (!$im) {
            return null;
        }

        imagepalettetotruecolor($im);
        imagealphablending($im, true);
        imagesavealpha($im, true);

        $ok = @imagewebp($im, $webpAbsPath, 82);
        imagedestroy($im);

        return $ok ? $webpRelUrl : null;
    }
}
?>
  
        <?
        $filterTypes = array();
        $filterTypes[0] = 'Показать все'; // Добавляем опцию "Все"
        
        if (!empty($arResult["ITEMS"])) {
            foreach ($arResult["ITEMS"] as $item) {
                // Получаем значение свойства TYPE
                $typeValue = $item["PROPERTIES"]["TYPE"]["VALUE"];
                
                if (!empty($typeValue)) {
                    if (is_array($typeValue)) {
                        foreach ($typeValue as $key => $val) {
                            $desc = is_array($typeDesc) ? $typeDesc[$key] : $val;
                            $filterTypes[$val] = $desc;
                        }
                    }
                }
            }
        }

        $filterTypes = array_unique($filterTypes);
        ?>

        <!-- Фильтр проектов -->
        <div class="projects-list__filters">
            <button class="btn filter active" data-filter="0">
                Показать все
            </button>
            
            <? foreach ($filterTypes as $typeId => $typeName): ?>
                <? if ($typeId !== 0 && !empty($typeName)): ?>
                    <button class="btn filter" data-filter="<?=$typeId?>">
                        <?=htmlspecialchars($typeName)?>
                    </button>
                <? endif; ?>
            <? endforeach; ?>
        </div>

        <div class="projects-list__content">
            <? foreach ($arResult["ITEMS"] as $itemIndex => $item): ?>
                <?
                // Формируем data-filter атрибут с ID типов
                $filterData = "0";
                $typeValue = $item["PROPERTIES"]["TYPE"]["VALUE"];
                
                if (!empty($typeValue)) {
                    if (is_array($typeValue)) {
                        $filterData .= " " . implode(" ", $typeValue);
                    } else {
                        $filterData .= " " . $typeValue;
                    }
                }
                
                $detailUrl = $item["DETAIL_PAGE_URL"];

                $pictureId = 0;
                if (!empty($item["PREVIEW_PICTURE"]["ID"])) {
                    $pictureId = $item["PREVIEW_PICTURE"]["ID"];
                } elseif (!empty($item["DETAIL_PICTURE"]["ID"])) {
                    $pictureId = $item["DETAIL_PICTURE"]["ID"];
                }
                
                // Те же два размера, что и раньше (1000/2000 — уже прогреты в
                // resize_cache, новых размеров не добавляем: на 135 карточках
                // холодная генерация новых пиксельных вариантов дала бы заметную
                // просадку первого запроса после очистки кэша). Раньше это были
                // "1x"/"2x"-дескрипторы — теперь ширины + sizes ниже, чтобы
                // retina-телефон с маленьким экраном не тянул 2000px картинку
                // только из-за высокого DPR.
                $srcsetSizes = [
                    array('width' => 1000, 'height' => 1000),
                    array('width' => 2000, 'height' => 2000),
                ];

                $imgWidth = 0;
                $imgHeight = 0;
                $imgSrc = '';
                $rasterSrcsetParts = [];
                $webpSrcsetParts = [];

                if ($pictureId > 0) {
                    foreach ($srcsetSizes as $i => $size) {
                        $arImage = CFile::ResizeImageGet(
                            $pictureId,
                            $size,
                            BX_RESIZE_IMAGE_PROPORTIONAL,
                            true
                        );
                        if (empty($arImage['src'])) {
                            continue;
                        }

                        $realWidth = (int)$arImage['width'];
                        $rasterSrcsetParts[] = $arImage['src'] . ' ' . $realWidth . 'w';

                        // Не все размеры обязаны сгенерироваться webp-версией (см.
                        // profequipGetWebpVariant) — берём в <source> только то, что
                        // реально получилось, а не выбрасываем весь webp целиком
                        // из-за одного не собравшегося размера.
                        $webpUrl = profequipGetWebpVariant($arImage['src']);
                        if ($webpUrl) {
                            $webpSrcsetParts[] = $webpUrl . ' ' . $realWidth . 'w';
                        }

                        // Первый (меньший) размер — как src для браузеров без
                        // поддержки srcset и как база для width/height (резервирует
                        // место под фото).
                        if ($i === 0) {
                            $imgSrc = $arImage['src'];
                            $imgWidth = $realWidth;
                            $imgHeight = (int)$arImage['height'];
                        }
                    }
                }

                $rasterSrcset = implode(', ', $rasterSrcsetParts);
                $webpSrcset = implode(', ', $webpSrcsetParts);
                $imgSizesAttr = ' sizes="(max-width: 576px) 100vw, 50vw"';

                $itemName = htmlspecialchars($item["NAME"]);

                // Первые карточки видны без скролла сразу после загрузки страницы —
                // грузим их сразу (без loading="lazy"), первую ещё и с приоритетом,
                // чтобы не откладывать LCP. Остальные остаются ленивыми.
                $isAboveFold = $itemIndex < 4;
                $imgLoadingAttr = $isAboveFold ? '' : ' loading="lazy"';
                $imgFetchPriorityAttr = $itemIndex === 0 ? ' fetchpriority="high"' : '';
                $imgDimsAttr = ($imgWidth > 0 && $imgHeight > 0)
                    ? ' width="' . $imgWidth . '" height="' . $imgHeight . '"'
                    : '';
                ?>

                <a class="project__item"
                   href="<?=$detailUrl?>"
                   data-filter="<?=$filterData?>">
                    <picture>
                        <? if ($webpSrcset !== ''): ?>
                        <source srcset="<?=$webpSrcset?>"<?=$imgSizesAttr?> type="image/webp">
                        <? endif; ?>
                        <img src="<?=$imgSrc?>"<?=$imgDimsAttr?><?=$imgLoadingAttr?><?=$imgFetchPriorityAttr?>
                             srcset="<?=$rasterSrcset?>"<?=$imgSizesAttr?>
                             alt="<?=$itemName?>"
                             >
                    </picture>
                    <p class="project__item__title"><?=$item["NAME"];?></p>
                </a>
            <? endforeach; ?>
        </div>
        
    </div>
</div>