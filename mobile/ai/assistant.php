<?php

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

require_once dirname(__DIR__, 2) . '/includes/config.php';


/*
|--------------------------------------------------------------------------
| PATIENT LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['patient_auth']) ||
    !is_array($_SESSION['patient_auth']) ||
    ($_SESSION['patient_auth']['logged_in'] ?? false) !== true
) {

    header(
        'Location: /Hospital_Management_System/patient_portal/auth/login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| PATIENT ACCOUNT ID
|--------------------------------------------------------------------------
*/

$account_id = (int) (
    $_SESSION['patient_auth']['account_id'] ?? 0
);


if ($account_id <= 0) {

    header(
        'Location: /Hospital_Management_System/patient_portal/auth/login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| PATIENT NAME
|--------------------------------------------------------------------------
*/

$patient_name = 'Patient';

if (
    isset($_SESSION['patient_auth']['first_name']) &&
    $_SESSION['patient_auth']['first_name'] !== ''
) {

    $patient_name =
        (string) $_SESSION['patient_auth']['first_name'];
}

if (
    isset($_SESSION['patient_auth']['last_name']) &&
    $_SESSION['patient_auth']['last_name'] !== ''
) {

    $patient_name .=
        ' ' .
        (string) $_SESSION['patient_auth']['last_name'];
}

$patient_name = htmlspecialchars(
    $patient_name,
    ENT_QUOTES,
    'UTF-8'
);


/*
|--------------------------------------------------------------------------
| SELECTED CONVERSATION
|--------------------------------------------------------------------------
*/

$selected_conversation_id = (int) (
    $_GET['conversation_id'] ?? 0
);


/*
|--------------------------------------------------------------------------
| VERIFY SELECTED CONVERSATION
|--------------------------------------------------------------------------
*/

if ($selected_conversation_id > 0) {

    $verify_sql = "
        SELECT id
        FROM ai_conversations
        WHERE id = ?
        AND patient_id = ?
        LIMIT 1
    ";

    $verify_stmt = mysqli_prepare(
        $conn,
        $verify_sql
    );

    if ($verify_stmt) {

        mysqli_stmt_bind_param(
            $verify_stmt,
            'ii',
            $selected_conversation_id,
            $account_id
        );

        mysqli_stmt_execute(
            $verify_stmt
        );

        mysqli_stmt_bind_result(
            $verify_stmt,
            $verified_conversation_id
        );

        if (
            mysqli_stmt_fetch($verify_stmt) !== true
        ) {

            $selected_conversation_id = 0;
        }

        mysqli_stmt_close(
            $verify_stmt
        );
    }
}


/*
|--------------------------------------------------------------------------
| GET PATIENT CONVERSATIONS
|--------------------------------------------------------------------------
*/

$conversations = [];

$conversation_sql = "
    SELECT
        id,
        title,
        updated_at
    FROM ai_conversations
    WHERE patient_id = ?
    ORDER BY updated_at DESC
";

$conversation_stmt = mysqli_prepare(
    $conn,
    $conversation_sql
);

if ($conversation_stmt) {

    mysqli_stmt_bind_param(
        $conversation_stmt,
        'i',
        $account_id
    );

    mysqli_stmt_execute(
        $conversation_stmt
    );

    mysqli_stmt_bind_result(
        $conversation_stmt,
        $conversation_id_row,
        $conversation_title_row,
        $conversation_updated_row
    );

    while (
        mysqli_stmt_fetch(
            $conversation_stmt
        )
    ) {

        $conversations[] = [
            'id' =>
                (int) $conversation_id_row,
            'title' =>
                (string) $conversation_title_row,
            'updated_at' =>
                (string) $conversation_updated_row
        ];
    }

    mysqli_stmt_close(
        $conversation_stmt
    );
}


/*
|--------------------------------------------------------------------------
| LOAD SELECTED CONVERSATION
|--------------------------------------------------------------------------
*/

$old_messages = [];

if ($selected_conversation_id > 0) {

    $message_sql = "
        SELECT
            role,
            message
        FROM ai_messages
        WHERE conversation_id = ?
        ORDER BY id ASC
    ";

    $message_stmt = mysqli_prepare(
        $conn,
        $message_sql
    );

    if ($message_stmt) {

        mysqli_stmt_bind_param(
            $message_stmt,
            'i',
            $selected_conversation_id
        );

        mysqli_stmt_execute(
            $message_stmt
        );

        mysqli_stmt_bind_result(
            $message_stmt,
            $message_role_row,
            $message_text_row
        );

        while (
            mysqli_stmt_fetch(
                $message_stmt
            )
        ) {

            $old_messages[] = [
                'role' =>
                    (string) $message_role_row,
                'message' =>
                    (string) $message_text_row
            ];
        }

        mysqli_stmt_close(
            $message_stmt
        );
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<meta
    name="theme-color"
    content="#5146e5"
>

<title>
    AI Health Assistant
</title>


<style>

/* =========================================================
   RESET
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html,
body {
    width: 100%;
    height: 100%;
}


/* =========================================================
   BODY
========================================================= */

body {

    font-family:
        Inter,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Roboto,
        Arial,
        sans-serif;

    background:
        linear-gradient(
            135deg,
            #f3f5fb,
            #eef1f9
        );

    color: #182033;

}


/* =========================================================
   PAGE
========================================================= */

.ai-page {

    min-height: 100vh;

    padding: 18px;

    display: flex;

    justify-content: center;

    align-items: center;

}


/* =========================================================
   MAIN CONTAINER
========================================================= */

.ai-container {

    width: 100%;

    max-width: 1280px;

    height: calc(100vh - 36px);

    min-height: 620px;

    background: #ffffff;

    border-radius: 20px;

    overflow: hidden;

    display: flex;

    flex-direction: column;

    box-shadow:
        0 18px 55px
        rgba(26, 35, 65, 0.12);

}


/* =========================================================
   HEADER
========================================================= */

.ai-header {

    height: 76px;

    flex-shrink: 0;

    padding: 12px 20px;

    background:
        linear-gradient(
            135deg,
            #172052,
            #5143e8
        );

    color: #ffffff;

    display: flex;

    align-items: center;

    justify-content: space-between;

}


/* =========================================================
   HEADER LEFT
========================================================= */

.ai-header-left {

    display: flex;

    align-items: center;

    gap: 12px;

}


.ai-icon {

    width: 46px;

    height: 46px;

    border-radius: 14px;

    background:
        rgba(255,255,255,0.14);

    display: flex;

    justify-content: center;

    align-items: center;

    font-size: 23px;

}


.ai-title {

    font-size: 19px;

    font-weight: 750;

    letter-spacing: -0.2px;

}


.ai-subtitle {

    margin-top: 3px;

    font-size: 11px;

    opacity: .78;

}


/* =========================================================
   HEADER RIGHT
========================================================= */

.ai-header-actions {

    display: flex;

    align-items: center;

    gap: 12px;

}


.ai-status {

    display: flex;

    align-items: center;

    gap: 7px;

    font-size: 12px;

}


.status-dot {

    width: 8px;

    height: 8px;

    border-radius: 50%;

    background: #5dffa0;

    box-shadow:
        0 0 0 4px
        rgba(93,255,160,.12);

}


.clear-chat-button {

    border:
        1px solid
        rgba(255,255,255,.30);

    background:
        rgba(255,255,255,.10);

    color: #ffffff;

    padding: 8px 12px;

    border-radius: 9px;

    cursor: pointer;

    font-size: 12px;

    transition: .2s;

}


.clear-chat-button:hover {

    background:
        rgba(255,255,255,.20);

}


/* =========================================================
   BODY
========================================================= */

.ai-body {

    flex: 1;

    min-height: 0;

    display: flex;

}


/* =========================================================
   CHAT HISTORY
========================================================= */

.chat-history {

    width: 270px;

    flex-shrink: 0;

    background: #f8f9fd;

    border-right:
        1px solid #e5e8f0;

    display: flex;

    flex-direction: column;

}


/* =========================================================
   HISTORY HEADER
========================================================= */

.history-header {

    padding: 18px;

    border-bottom:
        1px solid #e5e8f0;

}


.history-title {

    font-size: 14px;

    font-weight: 750;

    margin-bottom: 12px;

}


.new-chat-button {

    width: 100%;

    height: 40px;

    border: none;

    border-radius: 10px;

    background: #6355f4;

    color: #ffffff;

    cursor: pointer;

    font-size: 13px;

    font-weight: 650;

}


.new-chat-button:hover {

    background: #4940df;

}


/* =========================================================
   HISTORY LIST
========================================================= */

.history-list {

    flex: 1;

    overflow-y: auto;

    padding: 10px;

}


.history-item {

    display: block;

    padding: 12px;

    margin-bottom: 5px;

    border-radius: 10px;

    text-decoration: none;

    color: #30384d;

}


.history-item:hover {

    background: #eceeff;

}


.history-item.active {

    background: #e5e5ff;

    color: #4e46d7;

}


.history-item-title {

    font-size: 13px;

    font-weight: 650;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;

}


.history-item-date {

    margin-top: 5px;

    color: #9298a8;

    font-size: 10px;

}


.empty-history {

    text-align: center;

    padding: 40px 15px;

    color: #9298a8;

    font-size: 12px;

    line-height: 1.7;

}


/* =========================================================
   CHAT SECTION
========================================================= */

.chat-section {

    flex: 1;

    min-width: 0;

    display: flex;

    flex-direction: column;

    background: #ffffff;

}


/* =========================================================
   CHAT AREA
========================================================= */

.chat-area {

    flex: 1;

    min-height: 0;

    overflow-y: auto;

    padding: 30px 32px;

    background:
        #ffffff;

    scroll-behavior: smooth;

}


/* =========================================================
   WELCOME
========================================================= */

.welcome-message {

    max-width: 650px;

    margin: 65px auto;

    text-align: center;

}


.welcome-icon {

    width: 70px;

    height: 70px;

    margin: auto auto 18px;

    border-radius: 22px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 38px;

    background:
        #f0efff;

}


.welcome-message h2 {

    font-size: 25px;

    margin-bottom: 10px;

    color: #171d31;

    letter-spacing: -0.4px;

}


.welcome-message p {

    color: #737b8d;

    font-size: 14px;

    line-height: 1.8;

}


/* =========================================================
   MESSAGE ROW
========================================================= */

.message-row {

    width: 100%;

    display: flex;

    margin-bottom: 20px;

}


.message-row.user {

    justify-content: flex-end;

}


.message-row.ai {

    justify-content: flex-start;

}


/* =========================================================
   MESSAGE BUBBLE
========================================================= */

.message-bubble {

    max-width: min(760px, 82%);

    padding: 15px 17px;

    border-radius: 16px;

    font-size: 14px;

    line-height: 1.75;

    word-break: break-word;

    overflow-wrap: anywhere;

}


/* =========================================================
   USER BUBBLE
========================================================= */

.message-row.user .message-bubble {

    background:
        linear-gradient(
            135deg,
            #6355f4,
            #5143e8
        );

    color: #ffffff;

    border-bottom-right-radius: 5px;

    box-shadow:
        0 5px 15px
        rgba(91,80,245,.16);

}


/* =========================================================
   AI BUBBLE
========================================================= */

.message-row.ai .message-bubble {

    background: #f7f8fc;

    color: #252d42;

    border:
        1px solid #e7e9f0;

    border-bottom-left-radius: 5px;

}


/* =========================================================
   MESSAGE TEXT
========================================================= */

.message-text {

    white-space: normal;

    line-height: 1.78;

}


.message-text p {

    margin: 0 0 12px;

}


.message-text p:last-child {

    margin-bottom: 0;

}


.message-text strong {

    font-weight: 750;

}


.message-text h3 {

    font-size: 16px;

    line-height: 1.4;

    margin:
        3px 0 11px;

}


.message-text ul,
.message-text ol {

    margin:
        7px 0 12px 20px;

}


.message-text li {

    margin-bottom: 6px;

    padding-left: 2px;

}


.message-text code {

    padding:
        2px 6px;

    border-radius: 5px;

    background:
        rgba(91,80,245,.09);

    color: #4b43d3;

    font-family:
        Consolas,
        monospace;

    font-size: 12px;

}


.message-text pre {

    margin-top: 12px;

    padding: 13px;

    overflow-x: auto;

    border-radius: 10px;

    background: #1e2433;

    color: #f4f5f7;

    font-family:
        Consolas,
        monospace;

    font-size: 12px;

    line-height: 1.6;

}


.message-text blockquote {

    margin:
        10px 0;

    padding:
        9px 12px;

    border-left:
        3px solid #6355f4;

    background:
        #eef0ff;

    color: #4c5265;

    border-radius:
        0 7px 7px 0;

}


/* =========================================================
   MEDICAL WARNING
========================================================= */

.message-text .medical-warning {

    margin-top: 12px;

    padding: 11px 12px;

    border-radius: 9px;

    background: #fff5f5;

    border:
        1px solid #ffdede;

    color: #8c3030;

}


/* =========================================================
   COPY BUTTON
========================================================= */

.copy-button {

    margin-top: 12px;

    padding: 5px 9px;

    border:
        1px solid #dfe3ec;

    border-radius: 7px;

    background: #ffffff;

    color: #626a7d;

    cursor: pointer;

    font-size: 11px;

}


.copy-button:hover {

    background: #f0f1f7;

    color: #4f46d8;

}


/* =========================================================
   TYPING
========================================================= */

.typing {

    display: none;

    margin-bottom: 17px;

}


.typing-bubble {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding: 12px 14px;

    background: #f7f8fc;

    border:
        1px solid #e7e9f0;

    border-radius: 14px;

}


.typing-bubble span {

    width: 7px;

    height: 7px;

    border-radius: 50%;

    background: #8c92a2;

    animation:
        typingAnimation 1.2s infinite;

}


.typing-bubble span:nth-child(2) {

    animation-delay: .15s;

}


.typing-bubble span:nth-child(3) {

    animation-delay: .30s;

}


@keyframes typingAnimation {

    0%,
    60%,
    100% {

        transform: translateY(0);

        opacity: .35;

    }

    30% {

        transform: translateY(-4px);

        opacity: 1;

    }

}


/* =========================================================
   INPUT
========================================================= */

.chat-input-area {

    padding: 13px 18px 12px;

    border-top:
        1px solid #e6e8ef;

    background: #ffffff;

}


.chat-form {

    display: flex;

    align-items: flex-end;

    gap: 9px;

}


/* =========================================================
   TEXT INPUT
========================================================= */

.message-input {

    flex: 1;

    min-height: 46px;

    max-height: 120px;

    resize: none;

    padding:
        12px 14px;

    border:
        1px solid #dfe3ec;

    border-radius: 12px;

    outline: none;

    font-family: inherit;

    font-size: 14px;

    line-height: 1.45;

    background: #fbfcff;

}


.message-input:focus {

    border-color: #6355f4;

    background: #ffffff;

    box-shadow:
        0 0 0 3px
        rgba(91,80,245,.08);

}


.send-button {

    width: 48px;

    height: 46px;

    flex-shrink: 0;

    border: none;

    border-radius: 12px;

    background:
        linear-gradient(
            135deg,
            #6355f4,
            #5143e8
        );

    color: #ffffff;

    cursor: pointer;

    font-size: 18px;

}


.send-button:hover {

    transform: translateY(-1px);

}


.send-button:disabled {

    opacity: .5;

    cursor: not-allowed;

    transform: none;

}


/* =========================================================
   DISCLAIMER
========================================================= */

.disclaimer {

    margin-top: 7px;

    text-align: center;

    color: #969cab;

    font-size: 9.5px;

    line-height: 1.4;

}


/* =========================================================
   MOBILE HISTORY BUTTON
========================================================= */

.history-toggle {

    display: none;

    border: none;

    background:
        rgba(255,255,255,.12);

    color: #ffffff;

    width: 38px;

    height: 38px;

    border-radius: 9px;

    cursor: pointer;

    font-size: 18px;

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 800px) {

    body {

        background: #ffffff;

    }


    .ai-page {

        padding: 0;

        min-height: 100dvh;

    }


    .ai-container {

        width: 100%;

        height: 100dvh;

        min-height: 100dvh;

        border-radius: 0;

        box-shadow: none;

    }


    .chat-history {

        position: fixed;

        z-index: 1000;

        top: 0;

        left: -290px;

        bottom: 0;

        width: 280px;

        box-shadow:
            5px 0 25px
            rgba(0,0,0,.14);

        transition:
            left .25s ease;

    }


    .chat-history.open {

        left: 0;

    }


    .history-toggle {

        display: block;

    }


    .ai-header {

        height: 68px;

        padding:
            10px 12px;

    }


    .ai-header-left {

        gap: 8px;

    }


    .ai-icon {

        width: 39px;

        height: 39px;

        border-radius: 12px;

        font-size: 19px;

    }


    .ai-title {

        font-size: 15px;

    }


    .ai-subtitle {

        font-size: 9px;

    }


    .ai-status {

        display: none;

    }


    .clear-chat-button {

        font-size: 10px;

        padding:
            7px 9px;

    }


    .chat-area {

        padding:
            18px 12px 15px;

    }


    .welcome-message {

        margin:
            55px 12px;

    }


    .welcome-icon {

        width: 60px;

        height: 60px;

        border-radius: 18px;

        font-size: 31px;

    }


    .welcome-message h2 {

        font-size: 21px;

    }


    .welcome-message p {

        font-size: 13px;

        line-height: 1.7;

    }


    .message-row {

        margin-bottom: 17px;

    }


    .message-bubble {

        max-width: 91%;

        padding:
            12px 14px;

        border-radius: 15px;

        font-size: 14px;

        line-height: 1.7;

    }


    .message-row.user .message-bubble {

        max-width: 84%;

    }


    .message-text h3 {

        font-size: 15px;

    }


    .message-text ul,
    .message-text ol {

        margin-left: 18px;

    }


    .chat-input-area {

        padding:
            9px 9px
            calc(9px + env(safe-area-inset-bottom));

    }


    .message-input {

        min-height: 44px;

        padding:
            11px 12px;

        font-size: 14px;

        border-radius: 11px;

    }


    .send-button {

        width: 44px;

        height: 44px;

        border-radius: 11px;

    }


    .disclaimer {

        font-size: 8.5px;

        margin-top: 5px;

    }

}


/* =========================================================
   SMALL MOBILE
========================================================= */

@media (max-width: 430px) {

    .clear-chat-button {

        font-size: 0;

        width: 34px;

        height: 34px;

        padding: 0;

    }


    .clear-chat-button::after {

        content: "×";

        font-size: 18px;

    }


    .message-bubble {

        max-width: 94%;

        font-size: 13.5px;

    }


    .message-row.user .message-bubble {

        max-width: 88%;

    }
    /* =========================================================
   AI RICH RESPONSE
========================================================= */

.ai-rich-response {
    width: 100%;
    color: #263047;
    line-height: 1.75;
}


/* MAIN TITLE */

.ai-rich-response h1 {
    font-size: 24px;
    line-height: 1.3;
    margin: 0 0 16px;
    color: #182052;
    font-weight: 800;
}

.ai-rich-response h2 {
    font-size: 19px;
    line-height: 1.4;
    margin: 22px 0 10px;
    color: #3036a5;
    font-weight: 800;
}

.ai-rich-response h3 {
    font-size: 16px;
    margin: 18px 0 8px;
    color: #3f46b8;
    font-weight: 700;
}


/* PARAGRAPH */

.ai-rich-response p {
    margin: 9px 0;
}


/* BOLD */

.ai-rich-response strong {
    color: #182052;
    font-weight: 800;
}


/* BULLETS */

.ai-rich-response ul {
    margin: 10px 0 16px;
    padding-left: 0;
    list-style: none;
}

.ai-rich-response ul li {
    position: relative;
    margin: 8px 0;
    padding: 11px 14px 11px 38px;
    background: #f7f8ff;
    border: 1px solid #e7e8fa;
    border-radius: 10px;
}

.ai-rich-response ul li::before {
    content: "✓";
    position: absolute;
    left: 13px;
    top: 10px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #6355f4;
    color: #ffffff;
    font-size: 12px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
}


/* NUMBERED LIST */

.ai-rich-response ol {
    counter-reset: ai-step;
    list-style: none;
    margin: 12px 0 18px;
    padding: 0;
}

.ai-rich-response ol li {
    counter-increment: ai-step;
    position: relative;
    margin: 10px 0;
    padding: 14px 16px 14px 52px;
    background: linear-gradient(
        135deg,
        #f8f9ff,
        #f1f2ff
    );
    border: 1px solid #e0e3ff;
    border-radius: 12px;
}

.ai-rich-response ol li::before {
    content: counter(ai-step);
    position: absolute;
    left: 14px;
    top: 12px;
    width: 28px;
    height: 28px;
    border-radius: 9px;
    background: #6355f4;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 13px;
}


/* INFORMATION BOX */

.ai-info-box {
    margin: 14px 0;
    padding: 16px 18px;
    border-radius: 14px;
    background: linear-gradient(
        135deg,
        #f5f7ff,
        #eef0ff
    );
    border: 1px solid #dfe3ff;
}


/* IMPORTANT BOX */

.ai-important-box {
    margin: 14px 0;
    padding: 16px 18px;
    border-radius: 14px;
    background: #fff9e9;
    border: 1px solid #f3df9b;
}


/* WARNING */

.ai-warning-box {
    margin: 14px 0;
    padding: 17px 18px;
    border-radius: 14px;
    background: #fff3f3;
    border: 1px solid #f1caca;
    color: #7d2929;
}


/* EMERGENCY */

.ai-emergency-box {
    margin: 16px 0;
    padding: 18px;
    border-radius: 15px;
    background: linear-gradient(
        135deg,
        #fff0f0,
        #ffe5e5
    );
    border: 2px solid #efb0b0;
    color: #7d2020;
}


/* SUCCESS */

.ai-success-box {
    margin: 14px 0;
    padding: 16px 18px;
    border-radius: 14px;
    background: #effbf4;
    border: 1px solid #c8ead6;
    color: #245c3a;
}


/* MEDICAL GRID */

.ai-medical-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin: 15px 0;
}

.ai-medical-card {
    padding: 15px;
    border-radius: 14px;
    background: #ffffff;
    border: 1px solid #e3e6ef;
    box-shadow: 0 5px 16px rgba(31, 45, 80, .06);
}

.ai-medical-card-title {
    font-size: 13px;
    font-weight: 800;
    color: #555ce5;
    margin-bottom: 6px;
}

.ai-medical-card-text {
    font-size: 13px;
    color: #50596d;
}


/* TABLE */

.ai-rich-response table {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
    overflow: hidden;
    border-radius: 12px;
    border: 1px solid #dfe3ec;
}

.ai-rich-response th {
    background: #5551e8;
    color: white;
    padding: 11px;
    text-align: left;
    font-size: 13px;
}

.ai-rich-response td {
    padding: 11px;
    border-top: 1px solid #e5e8ef;
    font-size: 13px;
}

.ai-rich-response tr:nth-child(even) {
    background: #f8f9fd;
}


/* CODE */

.ai-rich-response code {
    background: #eef0f7;
    padding: 2px 6px;
    border-radius: 5px;
    font-family: Consolas, monospace;
    font-size: 12px;
}

.ai-rich-response pre {
    padding: 15px;
    border-radius: 12px;
    background: #171b2e;
    color: #ffffff;
    overflow-x: auto;
    margin: 14px 0;
}


/* HORIZONTAL LINE */

.ai-rich-response hr {
    border: none;
    border-top: 1px solid #e2e5ee;
    margin: 20px 0;
}


/* FOLLOW-UP QUESTIONS */

.ai-followup-box {
    margin-top: 20px;
    padding: 17px;
    border-radius: 15px;
    background: linear-gradient(
        135deg,
        #f2f1ff,
        #e9edff
    );
    border: 1px solid #d9dcff;
}

.ai-followup-title {
    font-size: 15px;
    font-weight: 800;
    color: #3d42ae;
    margin-bottom: 10px;
}

.ai-followup-question {
    padding: 10px 12px;
    margin: 7px 0;
    background: #ffffff;
    border: 1px solid #dfe3f5;
    border-radius: 10px;
}


/* IMAGE */

.ai-response-image {
    width: 100%;
    max-width: 650px;
    display: block;
    margin: 15px auto;
    border-radius: 15px;
    border: 1px solid #dfe3ec;
    box-shadow: 0 8px 25px rgba(30, 40, 80, .10);
}


/* DISCLAIMER */

.ai-medical-disclaimer {
    margin-top: 18px;
    padding: 12px 14px;
    border-radius: 10px;
    background: #f7f8fb;
    border: 1px solid #e3e6ee;
    color: #777f91;
    font-size: 11px;
}


/* MOBILE */

@media (max-width: 700px) {

    .message-bubble {
        max-width: 94%;
    }

    .ai-rich-response h1 {
        font-size: 20px;
    }

    .ai-rich-response h2 {
        font-size: 17px;
    }

    .ai-rich-response h3 {
        font-size: 15px;
    }

    .ai-medical-grid {
        grid-template-columns: 1fr;
        gap: 9px;
    }

    .ai-medical-card {
        padding: 13px;
    }

    .ai-rich-response ul li,
    .ai-rich-response ol li {
        font-size: 13px;
        padding-top: 11px;
        padding-bottom: 11px;
    }

    .ai-rich-response table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }

    .ai-rich-response th,
    .ai-rich-response td {
        padding: 9px;
        font-size: 12px;
    }

    .ai-warning-box,
    .ai-emergency-box,
    .ai-important-box,
    .ai-success-box,
    .ai-info-box {
        padding: 13px;
    }
}

}


/* =========================================================
   AI CODE BLOCKS
========================================================= */

.ai-code-wrapper {
    width: 100%;
    margin: 14px 0 18px;
    overflow: hidden;
    border: 1px solid #2d3240;
    border-radius: 12px;
    background: #171a24;
    box-shadow: 0 6px 18px rgba(15,18,30,.08);
}

.ai-code-header {
    min-height: 36px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 5px 9px;
    color: #aeb4c3;
    background: #202430;
    border-bottom: 1px solid #303443;
    font-family: Consolas, monospace;
    font-size: 9px;
    text-transform: uppercase;
}

.ai-code-copy {
    min-height: 25px;
    padding: 3px 8px;
    border: 1px solid #414657;
    border-radius: 7px;
    color: #dce0e8;
    background: #292e3b;
    cursor: pointer;
    font-size: 9px;
}

.ai-code-copy:hover {
    background: #343a49;
}

.ai-code-wrapper pre {
    margin: 0;
    padding: 14px;
    overflow-x: auto;
    color: #edf0f7;
    background: #171a24;
    font-family: Consolas, "SFMono-Regular", monospace;
    font-size: 12px;
    line-height: 1.65;
    white-space: pre;
    tab-size: 4;
}

.ai-code-wrapper pre code {
    padding: 0;
    color: inherit;
    background: transparent;
    font-size: inherit;
}

@media (max-width: 700px) {
    .ai-code-wrapper pre {
        padding: 12px;
        font-size: 11px;
    }
}

</style>

<link rel="stylesheet" href="../css/common.css">
</head>


<body>


<div class="ai-page">

<div class="ai-container">


<!-- =====================================================
     HEADER
====================================================== -->

<header class="ai-header">

    <div class="ai-header-left">

        <button
            type="button"
            id="historyToggle"
            class="history-toggle"
            title="Chat History"
        >
            ☰
        </button>


        <div class="ai-icon">
            🤖
        </div>


        <div>

            <div class="ai-title">
                AI Health Assistant
            </div>

            <div class="ai-subtitle">
                Smart Hospital
            </div>

        </div>

    </div>


    <div class="ai-header-actions">

        <div class="ai-status">

            <span class="status-dot"></span>

            AI Online

        </div>


        <button
            type="button"
            id="clearChatButton"
            class="clear-chat-button"
        >
            Clear Chat
        </button>

    </div>

</header>


<!-- =====================================================
     BODY
====================================================== -->

<div class="ai-body">


<!-- =====================================================
     CHAT HISTORY
====================================================== -->

<aside
    class="chat-history"
    id="chatHistory"
>

    <div class="history-header">

        <div class="history-title">
            🕘 Chat History
        </div>


        <button
            type="button"
            class="new-chat-button"
            id="newChatButton"
        >
            ➕ New Chat
        </button>

    </div>


    <div class="history-list">

        <?php if (empty($conversations)): ?>

            <div class="empty-history">

                No conversations yet.

                <br><br>

                Start chatting to create your first conversation.

            </div>

        <?php else: ?>

            <?php foreach ($conversations as $conversation): ?>

                <a
                    href="assistant.php?conversation_id=<?= (int)$conversation['id']; ?>"
                    class="history-item <?= (
                        (int)$conversation['id']
                        === $selected_conversation_id
                    ) ? 'active' : ''; ?>"
                >

                    <div class="history-item-title">

                        <?= htmlspecialchars(
                            (string)$conversation['title'],
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>

                    </div>


                    <div class="history-item-date">

                        <?= htmlspecialchars(
                            (string)$conversation['updated_at'],
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>

                    </div>

                </a>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</aside>


<!-- =====================================================
     CHAT
====================================================== -->

<section class="chat-section">


<main
    class="chat-area"
    id="chatArea"
>


<?php if (empty($old_messages)): ?>

    <div
        class="welcome-message"
        id="welcomeMessage"
    >

        <div class="welcome-icon">
            🩺
        </div>

        <h2>
            How can I help you?
        </h2>

        <p>

            Hello <?= $patient_name; ?>.
            Ask me about health, education,
            programming, general knowledge,
            writing and more.

        </p>

    </div>

<?php else: ?>


    <?php foreach ($old_messages as $message): ?>

        <div
            class="message-row <?= (
                $message['role'] === 'user'
            ) ? 'user' : 'ai'; ?>"
        >

            <div class="message-bubble">

                <div
                    class="message-text"
                    data-message="<?= htmlspecialchars(
                        (string)$message['message'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                >

                    <?= nl2br(
                        htmlspecialchars(
                            (string)$message['message'],
                            ENT_QUOTES,
                            'UTF-8'
                        )
                    ); ?>

                </div>


                <?php if (
                    $message['role'] === 'assistant'
                ): ?>

                    <button
                        type="button"
                        class="copy-button"
                        onclick="copyExistingMessage(this)"
                    >
                        📋 Copy
                    </button>

                <?php endif; ?>

            </div>

        </div>

    <?php endforeach; ?>

<?php endif; ?>


<div
    class="typing"
    id="typingIndicator"
>

    <div class="typing-bubble">

        <span></span>
        <span></span>
        <span></span>

    </div>

</div>


</main>


<!-- =====================================================
     INPUT
====================================================== -->

<div class="chat-input-area">

    <form
        class="chat-form"
        id="chatForm"
    >

        <textarea
            id="messageInput"
            class="message-input"
            placeholder="Ask me anything..."
            maxlength="2000"
            rows="1"
            autocomplete="off"
        ></textarea>


        <button
            type="submit"
            class="send-button"
            id="sendButton"
            title="Send message"
        >
            ➤
        </button>

    </form>


    <div class="disclaimer">

        AI-generated information may not always be accurate.
        For medical concerns, consult a qualified healthcare professional.

    </div>

</div>


</section>

</div>

</div>

</div>


<script>

'use strict';

let currentConversationId =
    <?= $selected_conversation_id > 0
        ? $selected_conversation_id
        : 0; ?>;

let isSending = false;

const chatForm =
    document.getElementById('chatForm');

const messageInput =
    document.getElementById('messageInput');

const sendButton =
    document.getElementById('sendButton');

const chatArea =
    document.getElementById('chatArea');

const typingIndicator =
    document.getElementById('typingIndicator');

const clearChatButton =
    document.getElementById('clearChatButton');

const newChatButton =
    document.getElementById('newChatButton');

const historyToggle =
    document.getElementById('historyToggle');

const chatHistory =
    document.getElementById('chatHistory');

let codeStore = {};
let codeId = 0;


/* =========================================================
   HELPERS
========================================================= */

function escapeHTML(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}


function scrollToBottom(smooth = true) {
    chatArea.scrollTo({
        top: chatArea.scrollHeight,
        behavior: smooth ? 'smooth' : 'auto'
    });
}


function removeWelcome() {
    const welcome =
        document.getElementById('welcomeMessage');

    if (welcome) {
        welcome.remove();
    }
}


/* =========================================================
   MARKDOWN RENDERER
========================================================= */

function renderInlineMarkdown(value) {

    let text = escapeHTML(value);

    text = text.replace(
        /`([^`\n]+)`/g,
        '<code>$1</code>'
    );

    text = text.replace(
        /\*\*(.+?)\*\*/g,
        '<strong>$1</strong>'
    );

    text = text.replace(
        /(?<!\*)\*([^*\n]+)\*(?!\*)/g,
        '<em>$1</em>'
    );

    return text;
}


function makeCodeBlock(code, language) {

    const id =
        String(codeId++);

    codeStore[id] =
        code;

    return (
        '<div class="ai-code-wrapper">' +

            '<div class="ai-code-header">' +

                '<span>' +
                    escapeHTML(
                        language || 'code'
                    ) +
                '</span>' +

                '<button ' +
                    'type="button" ' +
                    'class="ai-code-copy" ' +
                    'data-code-id="' +
                        id +
                    '">' +
                    'Copy code' +
                '</button>' +

            '</div>' +

            '<pre><code>' +
                escapeHTML(code) +
            '</code></pre>' +

        '</div>'
    );
}


function renderAIMessage(raw) {

    let text =
        String(raw || '')
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n');

    const blocks = [];

    text = text.replace(
        /```([a-zA-Z0-9_+#.+-]*)\s*\n?([\s\S]*?)```/g,
        function(match, language, code) {

            const index =
                blocks.length;

            blocks.push({
                language:
                    language || 'code',
                code:
                    code.replace(
                        /^\n+|\n+$/g,
                        ''
                    )
            });

            return '\n@@CODE_' + index + '@@\n';
        }
    );

    const lines =
        text.split('\n');

    const output = [];

    let paragraph = [];
    let listType = null;
    let listItems = [];

    function flushParagraph() {

        if (!paragraph.length) {
            return;
        }

        output.push(
            '<p>' +
            paragraph
                .map(renderInlineMarkdown)
                .join('<br>') +
            '</p>'
        );

        paragraph = [];
    }

    function flushList() {

        if (!listItems.length) {
            return;
        }

        const tag =
            listType === 'ol'
                ? 'ol'
                : 'ul';

        output.push(
            '<' + tag + '>' +
            listItems
                .map(function(item) {
                    return (
                        '<li>' +
                        renderInlineMarkdown(item) +
                        '</li>'
                    );
                })
                .join('') +
            '</' + tag + '>'
        );

        listType = null;
        listItems = [];
    }

    lines.forEach(function(line) {

        const trimmed =
            line.trim();

        if (trimmed === '') {
            flushParagraph();
            flushList();
            return;
        }

        if (/^@@CODE_\d+@@$/.test(trimmed)) {
            flushParagraph();
            flushList();
            output.push(trimmed);
            return;
        }

        let match =
            trimmed.match(/^###\s+(.+)$/);

        if (match) {
            flushParagraph();
            flushList();
            output.push(
                '<h3>' +
                renderInlineMarkdown(match[1]) +
                '</h3>'
            );
            return;
        }

        match =
            trimmed.match(/^##\s+(.+)$/);

        if (match) {
            flushParagraph();
            flushList();
            output.push(
                '<h2>' +
                renderInlineMarkdown(match[1]) +
                '</h2>'
            );
            return;
        }

        match =
            trimmed.match(/^#\s+(.+)$/);

        if (match) {
            flushParagraph();
            flushList();
            output.push(
                '<h1>' +
                renderInlineMarkdown(match[1]) +
                '</h1>'
            );
            return;
        }

        if (/^---+$/.test(trimmed)) {
            flushParagraph();
            flushList();
            output.push('<hr>');
            return;
        }

        match =
            trimmed.match(/^>\s+(.+)$/);

        if (match) {
            flushParagraph();
            flushList();
            output.push(
                '<blockquote>' +
                renderInlineMarkdown(match[1]) +
                '</blockquote>'
            );
            return;
        }

        match =
            trimmed.match(/^\d+[.)]\s+(.+)$/);

        if (match) {
            flushParagraph();

            if (listType !== 'ol') {
                flushList();
                listType = 'ol';
            }

            listItems.push(match[1]);
            return;
        }

        match =
            trimmed.match(/^[-*•]\s+(.+)$/);

        if (match) {
            flushParagraph();

            if (listType !== 'ul') {
                flushList();
                listType = 'ul';
            }

            listItems.push(match[1]);
            return;
        }

        if (listType) {
            flushList();
        }

        paragraph.push(line);
    });

    flushParagraph();
    flushList();

    let result =
        output.join('');

    blocks.forEach(function(block, index) {

        result =
            result.replace(
                '@@CODE_' + index + '@@',
                makeCodeBlock(
                    block.code,
                    block.language
                )
            );
    });

    return result;
}


/* =========================================================
   COPY
========================================================= */

async function copyText(
    text,
    button,
    originalLabel
) {

    let copied = false;

    try {

        if (
            navigator.clipboard &&
            navigator.clipboard.writeText
        ) {

            await navigator.clipboard.writeText(
                text
            );

            copied = true;
        }

    }
    catch (error) {
        copied = false;
    }

    if (!copied) {

        const textarea =
            document.createElement('textarea');

        textarea.value =
            text;

        textarea.style.position =
            'fixed';

        textarea.style.left =
            '-9999px';

        document.body.appendChild(
            textarea
        );

        textarea.focus();
        textarea.select();

        try {
            copied =
                document.execCommand('copy');
        }
        catch (error) {
            copied = false;
        }

        textarea.remove();
    }

    if (copied) {

        button.textContent =
            '✓ Copied';

        setTimeout(function() {

            button.textContent =
                originalLabel;

        }, 1400);

    }
    else {

        button.textContent =
            'Copy failed';

        setTimeout(function() {

            button.textContent =
                originalLabel;

        }, 1400);

    }
}


function bindCopyButtons(root) {

    root
        .querySelectorAll('.ai-code-copy')
        .forEach(function(button) {

            if (
                button.dataset.bound === '1'
            ) {
                return;
            }

            button.dataset.bound =
                '1';

            button.addEventListener(
                'click',
                function() {

                    const id =
                        button.dataset.codeId || '';

                    copyText(
                        codeStore[id] || '',
                        button,
                        'Copy code'
                    );
                }
            );
        });


    root
        .querySelectorAll('.copy-button')
        .forEach(function(button) {

            if (
                button.dataset.bound === '1'
            ) {
                return;
            }

            button.dataset.bound =
                '1';

            button.addEventListener(
                'click',
                function() {

                    const bubble =
                        button.closest(
                            '.message-bubble'
                        );

                    if (!bubble) {
                        return;
                    }

                    const text =
                        bubble.querySelector(
                            '.message-text'
                        );

                    if (!text) {
                        return;
                    }

                    copyText(
                        text.dataset.message ||
                        text.innerText,
                        button,
                        '📋 Copy'
                    );
                }
            );
        });
}


/* =========================================================
   ADD MESSAGE
========================================================= */

function addMessage(
    message,
    type
) {

    removeWelcome();

    const row =
        document.createElement('div');

    row.className =
        'message-row ' + type;

    const bubble =
        document.createElement('div');

    bubble.className =
        'message-bubble';

    const text =
        document.createElement('div');

    text.className =
        'message-text';

    text.dataset.message =
        message;

    if (type === 'ai') {

        text.className =
            'message-text ai-rich-response';

        text.innerHTML =
            renderAIMessage(message);

    }
    else {

        text.textContent =
            message;
    }

    bubble.appendChild(text);

    if (type === 'ai') {

        const button =
            document.createElement('button');

        button.type =
            'button';

        button.className =
            'copy-button';

        button.textContent =
            '📋 Copy';

        bubble.appendChild(button);
    }

    row.appendChild(bubble);

    chatArea.insertBefore(
        row,
        typingIndicator
    );

    bindCopyButtons(row);

    scrollToBottom();
}


/* =========================================================
   TYPING
========================================================= */

function showTyping() {

    typingIndicator.style.display =
        'block';

    scrollToBottom();
}


function hideTyping() {

    typingIndicator.style.display =
        'none';
}


/* =========================================================
   HISTORY UPDATE WITHOUT PAGE RELOAD
========================================================= */

function updateHistory(
    conversationId,
    userMessage
) {

    if (!conversationId) {
        return;
    }

    const id =
        String(conversationId);

    let item =
        chatHistory.querySelector(
            '.history-item[data-conversation-id="' +
            id +
            '"]'
        );

    if (!item) {

        item =
            document.createElement('a');

        item.className =
            'history-item';

        item.dataset.conversationId =
            id;

        item.href =
            'assistant.php?conversation_id=' +
            encodeURIComponent(id);

        item.innerHTML =
            '<div class="history-item-title"></div>' +
            '<div class="history-item-date">Just now</div>';

        const empty =
            chatHistory.querySelector(
                '.empty-history'
            );

        if (empty) {
            empty.remove();
        }

        const list =
            chatHistory.querySelector(
                '.history-list'
            );

        if (list) {
            list.prepend(item);
        }
    }

    const title =
        userMessage
            .trim()
            .replace(/\s+/g, ' ')
            .slice(0, 60)
            || 'New Conversation';

    const titleElement =
        item.querySelector(
            '.history-item-title'
        );

    if (titleElement) {
        titleElement.textContent =
            title;
    }

    chatHistory
        .querySelectorAll('.history-item')
        .forEach(function(historyItem) {

            historyItem.classList.toggle(
                'active',
                historyItem.dataset.conversationId === id
            );
        });
}


/* =========================================================
   SEND
========================================================= */

chatForm.addEventListener(
    'submit',
    async function(event) {

        event.preventDefault();

        if (isSending) {
            return;
        }

        const message =
            messageInput.value.trim();

        if (!message) {
            messageInput.focus();
            return;
        }

        isSending =
            true;

        addMessage(
            message,
            'user'
        );

        messageInput.value =
            '';

        messageInput.style.height =
            'auto';

        sendButton.disabled =
            true;

        messageInput.disabled =
            true;

        showTyping();

        try {

            const response =
                await fetch(
                    'ai_api.php',
                    {
                        method:
                            'POST',

                        headers: {
                            'Content-Type':
                                'application/json',

                            'Accept':
                                'application/json'
                        },

                        body:
                            JSON.stringify({
                                action:
                                    'send',

                                message:
                                    message,

                                conversation_id:
                                    currentConversationId
                            })
                    }
                );

            const responseText =
                await response.text();

            let data;

            try {

                data =
                    JSON.parse(
                        responseText
                    );

            }
            catch (error) {

                throw new Error(
                    'The server returned an invalid response.'
                );
            }

            hideTyping();

            if (
                data.success &&
                data.message
            ) {

                addMessage(
                    data.message,
                    'ai'
                );

                if (
                    data.conversation_id
                ) {

                    currentConversationId =
                        parseInt(
                            data.conversation_id,
                            10
                        ) || 0;

                    updateHistory(
                        currentConversationId,
                        message
                    );
                }

            }
            else {

                addMessage(
                    data.message ||
                    'Sorry, I could not process your request. Please try again.',
                    'ai'
                );
            }

        }
        catch (error) {

            hideTyping();

            console.error(
                'AI Chat Error:',
                error
            );

            addMessage(
                'I could not complete that request right now. Please try again. If the problem continues, check that the hospital server and AI service are available.',
                'ai'
            );

        }
        finally {

            isSending =
                false;

            sendButton.disabled =
                false;

            messageInput.disabled =
                false;

            messageInput.focus();

            scrollToBottom();
        }
    }
);


/* =========================================================
   ENTER TO SEND
========================================================= */

messageInput.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Enter' &&
            !event.shiftKey
        ) {

            event.preventDefault();

            chatForm.requestSubmit();
        }
    }
);


/* =========================================================
   AUTO RESIZE
========================================================= */

messageInput.addEventListener(
    'input',
    function() {

        this.style.height =
            'auto';

        this.style.height =
            Math.min(
                this.scrollHeight,
                120
            ) + 'px';
    }
);


/* =========================================================
   NEW CHAT
========================================================= */

newChatButton.addEventListener(
    'click',
    function() {

        if (isSending) {
            return;
        }

        window.location.href =
            'assistant.php';
    }
);


/* =========================================================
   CLEAR CHAT
========================================================= */

clearChatButton.addEventListener(
    'click',
    async function() {

        if (isSending) {
            return;
        }

        const confirmed =
            confirm(
                'Are you sure you want to clear this conversation?'
            );

        if (!confirmed) {
            return;
        }

        clearChatButton.disabled =
            true;

        try {

            const response =
                await fetch(
                    'ai_api.php',
                    {
                        method:
                            'POST',

                        headers: {
                            'Content-Type':
                                'application/json',

                            'Accept':
                                'application/json'
                        },

                        body:
                            JSON.stringify({
                                action:
                                    'clear',

                                conversation_id:
                                    currentConversationId
                            })
                    }
                );

            const responseText =
                await response.text();

            let data;

            try {

                data =
                    JSON.parse(
                        responseText
                    );

            }
            catch (error) {

                throw new Error(
                    'Invalid server response.'
                );
            }

            if (data.success) {

                window.location.href =
                    'assistant.php';

            }
            else {

                alert(
                    data.message ||
                    'Unable to clear conversation.'
                );
            }

        }
        catch (error) {

            console.error(
                'Clear Chat Error:',
                error
            );

            alert(
                'Unable to clear conversation. Please try again.'
            );

        }
        finally {

            clearChatButton.disabled =
                false;
        }
    }
);


/* =========================================================
   MOBILE HISTORY
========================================================= */

historyToggle.addEventListener(
    'click',
    function() {

        chatHistory.classList.toggle(
            'open'
        );

    }
);


document
    .querySelectorAll('.history-item')
    .forEach(function(item) {

        item.addEventListener(
            'click',
            function() {

                chatHistory.classList.remove(
                    'open'
                );

            }
        );

    });


/* =========================================================
   INITIAL RENDER
========================================================= */

document
    .querySelectorAll(
        '.message-row.ai .message-text'
    )
    .forEach(function(element) {

        const original =
            element.dataset.message ||
            element.textContent ||
            '';

        element.innerHTML =
            renderAIMessage(
                original
            );

    });


bindCopyButtons(
    document
);

scrollToBottom(
    false
);

</script>


</body>

</html>