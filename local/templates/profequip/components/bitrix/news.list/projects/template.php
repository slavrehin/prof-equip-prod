<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$this->setFrameMode(true);

if (empty($arResult["ITEMS"])) return;
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
            <div class="grid-sizer"></div>
            
            <? foreach ($arResult["ITEMS"] as $item): ?>
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
                
                if ($pictureId > 0) {
                    $arImage = CFile::ResizeImageGet(
                        $pictureId,
                        array('width' => 1000, 'height' => 1000),
                        BX_RESIZE_IMAGE_PROPORTIONAL,
                        true
                    );
                    $imgSrc = $arImage['src'];
                    
                    $arImage2x = CFile::ResizeImageGet(
                        $pictureId,
                        array('width' => 2000, 'height' => 2000),
                        BX_RESIZE_IMAGE_PROPORTIONAL,
                        true
                    );
                    $imgSrc2x = $arImage2x['src'];

                    
                }
                
                $itemName = htmlspecialchars($item["NAME"]);
                ?>
                
                <a class="project__item grid-item" 
                   href="<?=$detailUrl?>" 
                   data-filter="<?=$filterData?>">
                    <picture>
                        <source srcset="<?=$imgSrc?> 1x, <?=$imgSrc2x?> 2x" type="image/webp">
                        <img src="<?=$imgSrc?>"  loading="lazy"
                             srcset="<?=$imgSrc?> 1x, <?=$imgSrc2x?> 2x" 
                             alt="<?=$itemName?>"
                             >
                    </picture>
                    <p class="project__item__title"><?=$item["NAME"];?></p>
                </a>
            <? endforeach; ?>
        </div>
        
    </div>
</div>