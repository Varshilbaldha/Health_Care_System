<?php



/*
|--------------------------------------------------------------------------
| Validate Mobile Number
|--------------------------------------------------------------------------
*/

function validateMobile(string $mobile): bool
{
    return preg_match(
        "/^[6-9][0-9]{9}$/",
        $mobile
    ) === 1;
}


/*
|--------------------------------------------------------------------------
| Validate Email
|--------------------------------------------------------------------------
*/

function validateEmail(string $email): bool
{
    return filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    ) !== false;
}


/*
|--------------------------------------------------------------------------
| Validate Password
|--------------------------------------------------------------------------
|
| Minimum 8 Characters
| At least 1 Uppercase
| At least 1 Lowercase
| At least 1 Number
| At least 1 Special Character
|
|--------------------------------------------------------------------------
*/

function validatePassword(string $password): bool
{
    return preg_match(
        "/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/",
        $password
    ) === 1;
}


/*
|--------------------------------------------------------------------------
| Validate OTP
|--------------------------------------------------------------------------
*/

function validateOTP(string $otp): bool
{
    return preg_match(
        "/^[0-9]{6}$/",
        $otp
    ) === 1;
}


/*
|--------------------------------------------------------------------------
| Validate UUID
|--------------------------------------------------------------------------
*/

function validateUUID(string $uuid): bool
{
    return preg_match(
        "/^[0-9a-fA-F-]{36}$/",
        $uuid
    ) === 1;
}