<?php
// function.php
/**
 *    0 0 0 0 0 0 0 0 guest
 *    1 1 1 1 1 1 1 1
 *    } | | | | | | + user
 *    | | | | | | + - cleaner
 *    | | | | | + - - monument
 *    | | | | + - - - church
 *    | | | + - - - - moderator
 *    | | + - - - - - webmaster
 *    | + - - - - - - creator
 *    + - - - - - - - .......
 */

const ROLE_GUEST     = 0b00000000; // 0
const ROLE_USER      = 0b00000001; // 1
const ROLE_CLEANER   = 0b00000010; // 2
const ROLE_MONUMENT  = 0b00000100; // 4
const ROLE_CHURCH    = 0b00001000; // 8
const ROLE_MODERATOR = 0b00010000; // 16
const ROLE_WEBMASTER = 0b00100000; // 32
const ROLE_CREATOR   = 0b01000000; // 64
const ROLE_SOMEBODY  = 0b10000000; // 128
const ROLE_ACCOUNTANT = 0b100000000; // 256

$rolesList = [
    ROLE_USER      => 'Користувач',
    ROLE_CLEANER   => 'Прибиральник',
    ROLE_MONUMENT  => 'Пам’ятники',
    ROLE_CHURCH    => 'Церква',
    ROLE_MODERATOR => 'Модератор',
    ROLE_WEBMASTER => 'Вебмайстер',
    ROLE_CREATOR   => 'Креатор',
    ROLE_ACCOUNTANT => 'Бухгалтер'
];

/**
 * Есть ли у пользователя роль
 */
function hasRole(int $status, int $role): bool
{
    return ($status & $role) === $role;
}


function hasAnyRole(int $status, array $roles): bool
{
    foreach ($roles as $role) {
        if (hasRole($status, $role)) {
            return true;
        }
    }
    return false;
}


function addRole(int $status, int $role): int
{
    return $status | $role;
}

function removeRole(int $status, int $role): int
{
    return $status & (~$role);
}

function setRoles(int ...$roles): int
{
    return array_reduce($roles, fn($s, $r) => $s | $r, 0);
}

