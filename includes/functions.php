<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Clean Input
|--------------------------------------------------------------------------
*/

function cleanInput(string $data): string
{
    return htmlspecialchars(
        trim($data),
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

function redirect(string $page): void
{
    header("Location: " . $page);
    exit();
}

/*
|--------------------------------------------------------------------------
| Generate OTP
|--------------------------------------------------------------------------
*/

function generateOTP(): int
{
    return random_int(
        100000,
        999999
    );
}

/*
|--------------------------------------------------------------------------
| Generate UUID
|--------------------------------------------------------------------------
*/

function generateUUID(): string
{
    $data = random_bytes(16);

    $data[6] = chr(
        (ord($data[6]) & 0x0f) | 0x40
    );

    $data[8] = chr(
        (ord($data[8]) & 0x3f) | 0x80
    );

    return vsprintf(
        '%s%s-%s-%s-%s-%s%s%s',
        str_split(
            bin2hex($data),
            4
        )
    );
}