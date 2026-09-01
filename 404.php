<?
include_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/urlrewrite.php');

CHTTP::SetStatus("404 Not Found");
@define("ERROR_404","Y");

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$APPLICATION->SetTitle("404 Not Found");?>
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
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>