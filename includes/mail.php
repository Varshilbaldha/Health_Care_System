<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';


function sendOTP(
    string $email,
    string $name,
    string $otp,
    string $title = "OTP Verification"
): bool
{
    $mail = new PHPMailer(true);

    try {

        /*==================================================
            SMTP
        ==================================================*/

        $mail->isSMTP();
        $mail->SMTPDebug = 0;

        $mail->Host = 'smtp.gmail.com';

        $mail->SMTPAuth = true;

        /*
         * Gmail account
         */
        $mail->Username =
            'hospitalmanagement222@gmail.com';

        /*
         * IMPORTANT:
         * Put the 16-character Google APP PASSWORD here.
         *
         * DO NOT put normal Gmail password.
         *
         * Example:
         *
         * $mail->Password = 'abcdefghijklmnop';
         */

        $mail->Password =
            'getepkpmvcleftbp';


        /*
         * Gmail SMTPS
         */
        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_SMTPS;

        $mail->Port = 465;


        /*
         * Explicit authentication method
         */
        $mail->AuthType = 'LOGIN';


        /*
         * Timeout
         */
        $mail->Timeout = 30;


        /*==================================================
            SENDER
        ==================================================*/

        $mail->setFrom(
            'hospitalmanagement222@gmail.com',
            'Care Your Health'
        );


        /*==================================================
            RECIPIENT
        ==================================================*/

        $mail->addAddress(
            $email,
            $name
        );


        /*==================================================
            EMAIL
        ==================================================*/

        $mail->isHTML(true);

        $mail->CharSet = 'UTF-8';

        $mail->Subject = $title;


        /*==================================================
            HTML BODY
        ==================================================*/

        $safeName =
            htmlspecialchars(
                $name,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeOTP =
            htmlspecialchars(
                $otp,
                ENT_QUOTES,
                'UTF-8'
            );


        $mail->Body = "

        <div style='
            max-width:650px;
            margin:auto;
            font-family:Arial,Helvetica,sans-serif;
            background:#ffffff;
            border-radius:18px;
            padding:35px;
            border:1px solid #e5e7eb;
        '>

            <div style='
                text-align:center;
                margin-bottom:25px;
            '>

                <h2 style='
                    margin:0;
                    color:#3157d5;
                    font-size:28px;
                '>
                    Care Your Health
                </h2>

                <p style='
                    margin-top:8px;
                    color:#64748b;
                    font-size:14px;
                '>
                    Smart Care. Better Health.
                </p>

            </div>


            <h3 style='
                color:#172033;
                font-size:22px;
            '>
                Email Verification
            </h3>


            <p style='
                color:#475569;
                font-size:15px;
                line-height:1.7;
            '>

                Dear <b>{$safeName}</b>,

            </p>


            <p style='
                color:#475569;
                font-size:15px;
                line-height:1.7;
            '>

                Thank you for creating your
                Care Your Health patient account.

                Please use the following OTP
                to verify your email address.

            </p>


            <div style='
                text-align:center;
                padding:25px;
                background:#eef2ff;
                border-radius:16px;
                margin:28px 0;
            '>

                <div style='
                    color:#64748b;
                    font-size:13px;
                    margin-bottom:10px;
                    letter-spacing:1px;
                '>
                    VERIFICATION CODE
                </div>

                <div style='
                    font-size:34px;
                    font-weight:bold;
                    letter-spacing:9px;
                    color:#4f46e5;
                '>
                    {$safeOTP}
                </div>

            </div>


            <p style='
                color:#475569;
                font-size:14px;
            '>

                This OTP will expire in
                <b>10 minutes</b>.

            </p>


            <p style='
                color:#64748b;
                font-size:14px;
                line-height:1.7;
            '>

                If you did not request this OTP,
                please ignore this email.

            </p>


            <hr style='
                border:none;
                border-top:1px solid #e5e7eb;
                margin:28px 0;
            '>


            <div style='
                text-align:center;
                color:#94a3b8;
                font-size:12px;
            '>

                This is an automated email.

                <br>

                Care Your Health

            </div>

        </div>

        ";


        /*==================================================
            TEXT FALLBACK
        ==================================================*/

        $mail->AltBody =
            "Care Your Health\n\n" .
            "Dear {$name},\n\n" .
            "Your email verification OTP is: {$otp}\n\n" .
            "This OTP will expire in 10 minutes.\n\n" .
            "If you did not request this OTP, please ignore this email.";


        /*==================================================
            SEND
        ==================================================*/

        return $mail->send();

    }
    catch (Exception $e) {

        error_log(
            'Care Your Health OTP Error: ' .
            $mail->ErrorInfo
        );

        return false;
    }
}