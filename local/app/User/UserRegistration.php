<?php

namespace App\User;

use Bitrix\Main\UserGroupTable;
use Bitrix\Main\UserTable;

class UserRegistration
{
    const RETAIL_USERS_GROUP_ID = 7;
    const WHOLESALE_USERS_GROUP_ID = 8;
    const RETAIL_USER_TYPE_VALUE = 5;
    const WHOLESALE_USER_TYPE_VALUE = 6;


    public static function registerUser(&$regData): void
    {
        $regData['LOGIN'] = $regData['EMAIL'];
        if (empty($regData["UF_TYPE"])) {
            $regData["UF_TYPE"] = self::RETAIL_USER_TYPE_VALUE;
        }
    }

    public static function setUserGroup(&$regData): void
    {
        if ($regData["ID"] > 0) {
            if ($regData["UF_TYPE"] == self::WHOLESALE_USER_TYPE_VALUE) {
                //Оптовый пользователь
                \Bitrix\Main\UserGroupTable::add(array(
                    "USER_ID" => $regData["ID"],
                    "GROUP_ID" => self::WHOLESALE_USERS_GROUP_ID
                ));
                $user = new \CUser;
                $fields = [
                    "ACTIVE" => "N",
                ];
                $user->Update($regData["ID"], $fields);
            } else {
                //Розничный пользователь
                \Bitrix\Main\UserGroupTable::add(array(
                    "USER_ID" => $regData["ID"],
                    "GROUP_ID" => self::RETAIL_USERS_GROUP_ID
                ));
            }
        }
    }

}