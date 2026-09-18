<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$this->setFrameMode(true);

if (empty($arResult["ITEMS"])) return;

$profequipFilters = profequip_GetProjectsTypeFilters((int)$arParams["IBLOCK_ID"]);
$profequipBatch = array_slice($arResult["ITEMS"], 0, PROFEQUIP_PORTFOLIO_BATCH_SIZE);
$profequipHasMore = count($arResult["ITEMS"]) > PROFEQUIP_PORTFOLIO_BATCH_SIZE;
?>

        <!-- Фильтр проектов -->
        <div class="projects-list__filters">
            <button class="btn filter active" data-filter="0">
                Показать все
            </button>

            <? foreach ($profequipFilters as $profequipFilter): ?>
                <button class="btn filter" data-filter="<?=$profequipFilter['id']?>">
                    <?=htmlspecialchars($profequipFilter['name'])?>
                </button>
            <? endforeach; ?>
        </div>

        <div class="projects-list__content">
            <? foreach ($profequipBatch as $itemIndex => $item):
                $pictureId = 0;
                if (!empty($item["PREVIEW_PICTURE"]["ID"])) {
                    $pictureId = $item["PREVIEW_PICTURE"]["ID"];
                } elseif (!empty($item["DETAIL_PICTURE"]["ID"])) {
                    $pictureId = $item["DETAIL_PICTURE"]["ID"];
                }

                echo profequip_RenderProjectCard([
                    'NAME' => $item["NAME"],
                    'DETAIL_PAGE_URL' => $item["DETAIL_PAGE_URL"],
                    'PICTURE_ID' => $pictureId,
                ], $itemIndex);
            endforeach; ?>
        </div>

        <? if ($profequipHasMore): ?>
            <button
                type="button"
                class="btn projects-list__load-more"
                data-offset="<?=PROFEQUIP_PORTFOLIO_BATCH_SIZE?>"
            >
                Показать ещё
            </button>
        <? endif; ?>

    </div>
</div>
