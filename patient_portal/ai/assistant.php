<?php

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

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

        $verify_result =
            mysqli_stmt_get_result(
                $verify_stmt
            );

        if (
            !$verify_result ||
            mysqli_num_rows($verify_result) === 0
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

    $conversation_result =
        mysqli_stmt_get_result(
            $conversation_stmt
        );

    if ($conversation_result) {

        while (
            $row =
                mysqli_fetch_assoc(
                    $conversation_result
                )
        ) {

            $conversations[] = $row;
        }
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

        $message_result =
            mysqli_stmt_get_result(
                $message_stmt
            );

        if ($message_result) {

            while (
                $row =
                    mysqli_fetch_assoc(
                        $message_result
                    )
            ) {

                $old_messages[] = $row;
            }
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
            #5146e5
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

    background: #5b50f5;

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
            #5b50f5,
            #5146e5
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
        3px solid #5b50f5;

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

    border-color: #5b50f5;

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
            #5b50f5,
            #5146e5
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

}

</style>

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

/* =========================================================
   CURRENT CONVERSATION
========================================================= */

let currentConversationId =
    <?= $selected_conversation_id > 0
        ? $selected_conversation_id
        : 0; ?>;


/* =========================================================
   ELEMENTS
========================================================= */

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


/* =========================================================
   SCROLL
========================================================= */

function scrollToBottom() {

    chatArea.scrollTop =
        chatArea.scrollHeight;

}

scrollToBottom();


/* =========================================================
   FORMAT AI RESPONSE
   Safe lightweight Markdown-like formatting
========================================================= */

function formatAIMessage(text) {

    if (!text) {
        return '';
    }


    let safe =
        text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');


    /*
    |----------------------------------------------------------
    | CODE BLOCK
    |----------------------------------------------------------
    */

    const codeBlocks = [];

    safe = safe.replace(
        /```([\s\S]*?)```/g,
        function(match, code) {

            const index =
                codeBlocks.length;

            codeBlocks.push(
                '<pre>' +
                code.trim() +
                '</pre>'
            );

            return `@@CODEBLOCK${index}@@`;
        }
    );


    /*
    |----------------------------------------------------------
    | BOLD
    |----------------------------------------------------------
    */

    safe = safe.replace(
        /\*\*(.*?)\*\*/g,
        '<strong>$1</strong>'
    );


    /*
    |----------------------------------------------------------
    | INLINE CODE
    |----------------------------------------------------------
    */

    safe = safe.replace(
        /`([^`]+)`/g,
        '<code>$1</code>'
    );


    /*
    |----------------------------------------------------------
    | HEADINGS
    |----------------------------------------------------------
    */

    safe = safe.replace(
        /^### (.+)$/gm,
        '<h3>$1</h3>'
    );

    safe = safe.replace(
        /^## (.+)$/gm,
        '<h3>$1</h3>'
    );

    safe = safe.replace(
        /^# (.+)$/gm,
        '<h3>$1</h3>'
    );


    /*
    |----------------------------------------------------------
    | BULLET LIST
    |----------------------------------------------------------
    */

    safe = safe.replace(
        /(?:^|\n)([-•]) (.+)(?=\n|$)/g,
        '<li>$2</li>'
    );


    safe = safe.replace(
        /(<li>.*?<\/li>)(?:\n|$)+/gs,
        '<ul>$1</ul>'
    );


    /*
    |----------------------------------------------------------
    | NUMBERED LIST
    |----------------------------------------------------------
    */

    safe = safe.replace(
        /(?:^|\n)\d+\.\s+(.+)(?=\n|$)/g,
        '<li>$1</li>'
    );


    /*
    |----------------------------------------------------------
    | PARAGRAPHS
    |----------------------------------------------------------
    */

    const parts =
        safe.split(/\n{2,}/);

    safe =
        parts.map(function(part) {

            part = part.trim();

            if (!part) {
                return '';
            }

            if (
                part.startsWith('<h3>') ||
                part.startsWith('<ul>') ||
                part.startsWith('<pre>') ||
                part.startsWith('@@CODEBLOCK')
            ) {

                return part;
            }

            return '<p>' +
                part.replace(/\n/g, '<br>') +
                '</p>';

        }).join('');


    /*
    |----------------------------------------------------------
    | RESTORE CODE BLOCKS
    |----------------------------------------------------------
    */

    codeBlocks.forEach(
        function(block, index) {

            safe =
                safe.replace(
                    `@@CODEBLOCK${index}@@`,
                    block
                );

        }
    );


    return safe;
}


/* =========================================================
   ADD MESSAGE
========================================================= */

function addMessage(
    message,
    type
) {

    const row =
        document.createElement('div');

    row.className =
        'message-row ' + type;


    const bubble =
        document.createElement('div');

    bubble.className =
        'message-bubble';


    const messageText =
        document.createElement('div');

    messageText.className =
        'message-text';


    if (type === 'ai') {

        messageText.innerHTML =
            formatAIMessage(message);

    }
    else {

        messageText.textContent =
            message;

    }


    bubble.appendChild(
        messageText
    );


    /*
    |----------------------------------------------------------
    | COPY
    |----------------------------------------------------------
    */

    if (type === 'ai') {

        const copyButton =
            document.createElement('button');

        copyButton.type =
            'button';

        copyButton.className =
            'copy-button';

        copyButton.textContent =
            '📋 Copy';


        copyButton.addEventListener(
            'click',
            async function() {

                await copyText(
                    message,
                    copyButton
                );

            }
        );


        bubble.appendChild(
            copyButton
        );

    }


    row.appendChild(
        bubble
    );


    chatArea.insertBefore(
        row,
        typingIndicator
    );


    scrollToBottom();

}


/* =========================================================
   FORMAT OLD DATABASE MESSAGES
========================================================= */

document
    .querySelectorAll(
        '.message-row.ai .message-text'
    )
    .forEach(
        function(element) {

            const text =
                element.dataset.message || '';

            element.innerHTML =
                formatAIMessage(text);

        }
    );


/* =========================================================
   COPY
========================================================= */

async function copyText(
    text,
    button
) {

    try {

        await navigator.clipboard.writeText(
            text
        );

        button.textContent =
            '✓ Copied';

        setTimeout(
            function() {

                button.textContent =
                    '📋 Copy';

            },
            1500
        );

    }
    catch (error) {

        const textarea =
            document.createElement('textarea');

        textarea.value =
            text;

        textarea.style.position =
            'fixed';

        textarea.style.opacity =
            '0';

        document.body.appendChild(
            textarea
        );

        textarea.select();

        try {

            document.execCommand('copy');

            button.textContent =
                '✓ Copied';

            setTimeout(
                function() {

                    button.textContent =
                        '📋 Copy';

                },
                1500
            );

        }
        catch (fallbackError) {

            button.textContent =
                'Copy failed';

        }

        document.body.removeChild(
            textarea
        );
    }

}


/* =========================================================
   COPY EXISTING
========================================================= */

function copyExistingMessage(button) {

    const bubble =
        button.closest(
            '.message-bubble'
        );

    if (!bubble) {
        return;
    }


    const messageText =
        bubble.querySelector(
            '.message-text'
        );

    if (!messageText) {
        return;
    }


    const originalText =
        messageText.dataset.message ||
        messageText.innerText;


    copyText(
        originalText,
        button
    );

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
   SEND MESSAGE
========================================================= */

chatForm.addEventListener(
    'submit',
    async function(event) {

        event.preventDefault();


        const message =
            messageInput.value.trim();


        if (!message) {

            messageInput.focus();

            return;
        }


        /*
        |------------------------------------------------------
        | Add user message
        |------------------------------------------------------
        */

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

                        method: 'POST',

                        headers: {

                            'Content-Type':
                                'application/json',

                            'Accept':
                                'application/json'

                        },

                        body:
                            JSON.stringify({

                                message:
                                    message,

                                conversation_id:
                                    currentConversationId

                            })

                    }
                );


            const data =
                await response.json();


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
                        );

                }


                refreshHistory();

            }
            else {

                addMessage(
                    data.message ||
                    'Sorry, I could not process your request.',
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
        'AI API Error: ' + error.message,
        'ai'
    );
}


        sendButton.disabled =
            false;

        messageInput.disabled =
            false;

        messageInput.focus();

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

                        method: 'POST',

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


            const data =
                await response.json();


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
                'Unable to clear conversation.'
            );

        }


        clearChatButton.disabled =
            false;

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
    .querySelectorAll(
        '.history-item'
    )
    .forEach(
        function(item) {

            item.addEventListener(
                'click',
                function() {

                    chatHistory.classList.remove(
                        'open'
                    );

                }
            );

        }
    );


/* =========================================================
   REFRESH HISTORY
========================================================= */

function refreshHistory() {

    setTimeout(
        function() {

            window.location.reload();

        },
        500
    );

}


/* =========================================================
   INITIAL FOCUS
========================================================= */

messageInput.focus();

</script>


</body>

</html>