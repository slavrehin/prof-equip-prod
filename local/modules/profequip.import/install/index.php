<?php

use Bitrix\Main\ModuleManager;

class profequip_import extends CModule
{
    public $MODULE_ID = 'profequip.import';
    public $MODULE_NAME = 'ProfEquip: Импорт товаров';
    public $MODULE_DESCRIPTION = 'Внутренний инструмент импорта товаров каталога из CSV-файла. Добавляет пункт меню в разделе "Магазин".';

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
    }

    public function DoInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);
    }

    public function DoUninstall()
    {
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
}
