<?php

use App\User\UserRegistration;
use App\User\EventSendHandler;
use Bitrix\Main\EventManager;

$eventManager = EventManager::getInstance();

// Перед регистрацией пользователя. Подмена логина на почту LOGIN = EMAIL
$eventManager->addEventHandler(
    "main",
    "OnBeforeUserRegister",
    [UserRegistration::class, 'registerUser']
);

// После регистрации добавляем в группы
$eventManager->addEventHandler(
    "main",
    "OnAfterUserAdd",
    [UserRegistration::class, 'setUserGroup']
);
