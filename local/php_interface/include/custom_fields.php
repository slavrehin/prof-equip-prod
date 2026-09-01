<?
class GroupFilesHtml
{
    /**
     * Метод возвращает массив описания собственного типа свойств
     * @return array
     */
    public static function GetUserTypeDescription()
    {
        return array(
            "PROPERTY_TYPE"        => "S",
            "USER_TYPE"            => __CLASS__,
            "DESCRIPTION"          => "Текст + группы файлов",
            "GetPropertyFieldHtml" => array(__CLASS__, "GetPropertyFieldHtml"),
            "ConvertToDB" => array(__CLASS__, "ConvertToDB"),
            "ConvertFromDB" => array(__CLASS__, "ConvertFromDB"),
            "GetSettingsHTML" => array(__CLASS__, "GetSettingsHTML"),
            "PrepareSettings" => array(__CLASS__, "PrepareSettings"),
        );
    }
    public static function PrepareSettings($arFields)
    {
        $settings = array();

        if (is_array($arFields["USER_TYPE_SETTINGS"]))
        {
            $settings["DESC_NAME"] = $arFields["USER_TYPE_SETTINGS"]["DESC_NAME"];
        }
        return $settings;
    }

    public static function GetSettingsHTML($arProperty, $strHTMLControlName, &$arPropertyFields)
    {

        $arProperty["USER_TYPE_SETTINGS"] = is_array($arProperty["USER_TYPE_SETTINGS"])?$arProperty["USER_TYPE_SETTINGS"]:["DESC_NAME"=>"","DESC_DESC"=>""];

        $html = '';

        // Пример настройки: текстовое поле для ввода значения по умолчанию
        $html .= '
            <tr>
                <td>Название поля Строки:</td>
                <td>
                    <input type="text" name="' . htmlspecialcharsbx($strHTMLControlName["NAME"]) . '[DESC_NAME]" value="' . $arProperty["USER_TYPE_SETTINGS"]['DESC_NAME'].'">
                </td>
            </tr>
             
        ';

        return $html;
    }

    public static function prepareNameField($field){
        return preg_replace("/[^a-zA-Z0-9_:\.]/is", "_", $field);

    }


    public static function GetPropertyFieldHtml($arProperty, $value, $strHTMLControlName)
    {
        /** Получаем название строки из настроек пользовательского типа*/
        $stringName = $arProperty['USER_TYPE_SETTINGS']['DESC_NAME'] ?? 'Строка';

        // Инициализируем описание, если оно не задано
        if (!$value['DESCRIPTION']) {
            $value['DESCRIPTION'] = [ 'IMG' => ''];
        } elseif (is_string($value['DESCRIPTION'])) {
            $value['DESCRIPTION'] = unserialize($value['DESCRIPTION']);
        }
        if (!$value['VALUE']) {
            $value['VALUE'] = ['STRING_LINK' => '', 'STRING_TITLE' => '', 'GPOUP' => '','STRING_DESCR' => '','TEXT' => ''];
        } else {
            $value['VALUE'] = ($value['VALUE']);
        }
        /** Подключаем модуль fileman*/
        CModule::IncludeModule('fileman');

        /** Начинаем формировать HTML*/
        $html = '<div class="drop-item js-toggle-parent toggle-parent hidden-all" style="background-color: #e7eff1;border: 1px solid #bbc4cd;padding: 20px;" >';

        global $APPLICATION;

        if (!$_REQUEST['propedit']) {



            ob_start();
 
            /** Добавляем поле ввода строки*/
            $html .= '
            <div style="padding: 20px 0;display: flex;justify-content: flex-start;align-items: center;" class="js-toggle-open toggle-open">
                <span class="drop-item__label" style="margin-right: 10px">Заголовок</span>
                <input size="50" class="js-sort-name" type="text" name="' . ($strHTMLControlName["DESCRIPTION"] . "[STRING_TITLE]") . '" value="' . $value["VALUE"]['STRING_TITLE'] . '" />
            </div>
            ';

            $html .= '
            <div style="padding: 20px 0;display: flex;justify-content: flex-start;align-items: center;" >
                <span class="drop-item__label" style="margin-right: 10px">Группа</span>
                <input size="50" type="text" class="js-sort-name" name="' . ($strHTMLControlName["DESCRIPTION"] . "[GPOUP]") . '" value="' . $value["VALUE"]['GPOUP'] . '" />
            </div>
            ';
            $html .= ob_get_clean();

            ob_start();

            /** Параметры для компонента загрузки файла*/
            $params = [
                "INPUT_NAME"       => $strHTMLControlName['DESCRIPTION'] . "[IMG]",
                "MULTIPLE"         => "N",
                "MODULE_ID"        => "iblock",
                "ALLOW_UPLOAD"     => "F",
                "ALLOW_UPLOAD_EXT" => "",
                "INPUT_CAPTION"    => "Добавить иконку",
                "INPUT_VALUE"      => $value['DESCRIPTION']["IMG"] ?? []
            ];

            /** Включаем компонент загрузки файла*/
            $APPLICATION->IncludeComponent(
                "renart:main.file.input",
                "prop",
                $params,
                false
            );

            $html .= ob_get_clean();



            $html .= '
            <div style="padding: 20px 0;display: flex;justify-content: flex-start;align-items: center;">
                <span class="drop-item__label" style="margin-right: 10px">Описание файла</span>
                <input size="50" class="js-sort-name" type="text" name="' . ($strHTMLControlName["DESCRIPTION"] . "[STRING_DESCR]") . '" value="' . $value['VALUE']["STRING_DESCR"] .'" />
            </div>
            
            <div style="padding: 20px 0;display: flex;justify-content: flex-start;align-items: center;" class="js-toggle-open toggle-open">
                <span class="drop-item__label" style="margin-right: 10px">Название файла</span>
                <input size="50" class="js-sort-name" type="text" name="' . ($strHTMLControlName["DESCRIPTION"] . "[STRING_LINK]") . '" value="' . $value['VALUE']["STRING_LINK"] .'" />
            </div>

                <span class="drop-item__label" style="margin-right: 10px">Текстовый блок</span>

            ';

            ob_start();
            
            /** Добавляем HTML-редактор*/
            CFileMan::AddHTMLEditorFrame(
                self::prepareNameField($strHTMLControlName["VALUE"] . "[TEXT]"),
                $value['VALUE']["TEXT"],
                self::prepareNameField($strHTMLControlName["VALUE"] . "[TEXT_TYPE]"),
                "html",
                [
                    'height' => 100,
                    'width' => '100%'
                ],
                "N",
                0,
                "",
                "",
                false,
                true,
                false,
                [
                    'toolbarConfig' => CFileMan::GetEditorToolbarConfig("light"),
                    'saveEditorKey' => $strHTMLControlName["VALUE"] . "[TEXT]"
                ]
            );

            $html .= ob_get_clean();


        }

        $html .= '</div><div class="btn-toggle js-btn-toggle" style="margin-bottom: 20px; text-align: center;"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 512 512" fill="none">
<path d="M93.5 174.5L256.5 337.5L419.5 174.5" stroke="#4b6267" stroke-width="24" stroke-linecap="round"/>
</svg></div>';

        return $html;

    }


    public static function ConvertToDB($arProperty, $value)
    {
        $return = array();

        if ((isset($value["VALUE"]["TEXT"]) || isset($value["DESCRIPTION"]["STRING_LINK"]) || isset($value["DESCRIPTION"]["STRING_TITLE"])) && !empty($value['VALUE']["TEXT"] || $value['DESCRIPTION']["STRING_LINK"] || $value['DESCRIPTION']["STRING_TITLE"]))
        {

            if(is_array($value["VALUE"])){
                $return["VALUE"]["STRING_TITLE"] = $value["DESCRIPTION"]["STRING_TITLE"];
                $return["VALUE"]["GPOUP"] = $value["DESCRIPTION"]["GPOUP"];
                $return["VALUE"]["STRING_DESCR"] = $value["DESCRIPTION"]["STRING_DESCR"];
                $return["VALUE"]["STRING_LINK"] = $value["DESCRIPTION"]["STRING_LINK"];
                $return["VALUE"]["TEXT"] = $value["VALUE"]["TEXT"];
            }
            $return["VALUE"] = serialize($return["VALUE"]);

            if(is_array($value["DESCRIPTION"])) {
                $return["DESCRIPTION"]['IMG'] = $value["DESCRIPTION"]['IMG'];
                unset($value["DESCRIPTION"]["STRING_TITLE"]);
                unset($value["DESCRIPTION"]["STRING_DESCR"]);
                unset($value["DESCRIPTION"]["STRING_LINK"]);
            }
            $return["DESCRIPTION"] = serialize($value["DESCRIPTION"]);
        }

        return $return;
    }

    public static function ConvertFromDB(&$arProperty, $value)
    {
        $return = array();

        if ($arProperty["DESCRIPTION"]||$value["DESCRIPTION"])
        {
            $return['DESCRIPTION'] = unserialize($value["DESCRIPTION"]);

        }
        if (isset($value["VALUE"]))
        {
            $return['VALUE'] = unserialize($value["VALUE"]);
        }

        return $return;
    }



}


AddEventHandler('iblock', 'OnIBlockPropertyBuildList', ['GroupFilesHtml', 'GetUserTypeDescription']);


class ElementWithDescriptionBinding
{
    public static function GetIBlockPropertyDescription()
    {
        return array(
            "PROPERTY_TYPE" => "E", // Прототип типа свойства - привязка к элементам
            "USER_TYPE" => "ElementWithDescriptionBinding",
            "DESCRIPTION" => "Привязка к элементам с описанием", //Название нового типа свойства
            'GetPropertyFieldHtml' => array(__CLASS__, 'GetPropertyFieldHtml'),
            "ConvertToDB" => array(__CLASS__,"ConvertToDB"),
            "ConvertFromDB" => array(__CLASS__,"ConvertFromDB"),
        );
    }

    public static function GetPropertyFieldHtml($arProperty, $value, $strHTMLControlName)
    {


        $arItem = Array(
            "ID" => 0,
            "IBLOCK_ID" => 0,
            "NAME" => ""
        );

        if(intval($value["VALUE"]) > 0)
        {
            $arFilter = Array(
                "ID" => intval($value["VALUE"]),
                "IBLOCK_ID" => $arProperty["LINK_IBLOCK_ID"],
            );
            $arItem = \CIBlockElement::GetList(Array(), $arFilter, false, false, Array("ID", "IBLOCK_ID", "NAME"))->Fetch();
        }

        $html = '<input name="'.$strHTMLControlName["VALUE"].'" id="'.$strHTMLControlName["VALUE"].'" value="'.htmlspecialcharsex($value["VALUE"]).'" size="5" type="text">';
        $html .= ' <span id="sp_'.md5($strHTMLControlName["VALUE"]).'_'.$key.'">'.$arItem["NAME"].'</span>';
        $html .= '<input type="button" value="Выбрать" onclick="jsUtils.OpenWindow(\'/bitrix/admin/iblock_element_search.php?lang='.LANG.'&IBLOCK_ID='.$arProperty["LINK_IBLOCK_ID"].'&n='.$strHTMLControlName["VALUE"].'\', 600, 500);">';
        $html .= ' Описание:<input type="text" id="quan" name="'.$strHTMLControlName["DESCRIPTION"].'" value="'.htmlspecialcharsex($value["DESCRIPTION"]).'">';
        return  $html;
    }

    public static function GetAdminListViewHTML($arProperty, $value, $strHTMLControlName)
    {
        return;
    }

    public static function ConvertToDB($arProperty, $value)
    {
        $return = false;

        if( is_array($value) && array_key_exists("VALUE", $value) && ($value['VALUE'] > 0))
        {
            $return = array(
                "VALUE" => $value["VALUE"],
                "DESCRIPTION" => $value["DESCRIPTION"],
            );
        }

        return $return;
    }

    public static function ConvertFromDB($arProperty, $value)
    {
        $return = false;

        if(!is_array($value["VALUE"]))
        {
            $return = array(
                "VALUE" => $value["VALUE"],
            );
        }

        if(!is_array($value["DESCRIPTION"]))
        {
            $return["DESCRIPTION"] = $value["DESCRIPTION"];
        }

        if ($return['VALUE'] > 0):
            return $return;
        endif;
    }

}

AddEventHandler('iblock', 'OnIBlockPropertyBuildList', ['ElementWithDescriptionBinding', 'GetIBlockPropertyDescription']);