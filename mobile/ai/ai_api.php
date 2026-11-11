<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| AI ASSISTANT API
|--------------------------------------------------------------------------
| Smart Hospital Management System
|--------------------------------------------------------------------------
|
| Features:
|
| - Gemini AI
| - Patient authentication
| - Patient-wise conversations
| - Conversation history
| - New conversations
| - Clear conversation
| - Selected conversation support
| - MySQLi prepared statements
| - DOES NOT use mysqli_stmt_get_result()
| - JSON-only API response
| - Error handling
| - Medical safety instructions
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| ERROR CONFIGURATION
|--------------------------------------------------------------------------
|
| API should NEVER return PHP HTML errors to JavaScript.
|
*/

ini_set(
    'display_errors',
    '0'
);

ini_set(
    'display_startup_errors',
    '0'
);

error_reporting(
    E_ALL
);


/*
|--------------------------------------------------------------------------
| JSON HEADER
|--------------------------------------------------------------------------
*/

header(
    'Content-Type: application/json; charset=UTF-8'
);


/*
|--------------------------------------------------------------------------
| HELPER: JSON RESPONSE
|--------------------------------------------------------------------------
*/

function apiResponse(
    bool $success,
    string $message = '',
    array $extra = [],
    int $statusCode = 200
): never {

    http_response_code(
        $statusCode
    );

    echo json_encode(
        array_merge(
            [
                'success' =>
                    $success,

                'message' =>
                    $message
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| HELPER: SAFE ERROR LOG
|--------------------------------------------------------------------------
*/

function apiLog(
    string $message
): void {

    error_log(
        '[AI_API] ' . $message
    );
}


/*
|--------------------------------------------------------------------------
| PHP FATAL ERROR HANDLER
|--------------------------------------------------------------------------
|
| If PHP crashes, return JSON instead of:
|
| <br>
| <b>Fatal error...
|
*/

register_shutdown_function(
    function (): void {

        $error =
            error_get_last();


        if (
            $error !== null
            &&
            in_array(
                $error['type'],
                [
                    E_ERROR,
                    E_PARSE,
                    E_CORE_ERROR,
                    E_COMPILE_ERROR
                ],
                true
            )
        ) {

            apiLog(
                $error['message']
                .
                ' in '
                .
                $error['file']
                .
                ':'
                .
                $error['line']
            );


            if (
                !headers_sent()
            ) {

                http_response_code(
                    500
                );

                header(
                    'Content-Type: application/json; charset=UTF-8'
                );

            }


            echo json_encode(
                [
                    'success' =>
                        false,

                    'message' =>
                        'A server error occurred while processing the AI request.'
                ],
                JSON_UNESCAPED_UNICODE
            );

        }

    }
);


/*
|--------------------------------------------------------------------------
| LOAD CONFIG
|--------------------------------------------------------------------------
*/

try {

    require_once dirname(
        __DIR__,
        2
    )
    .
    DIRECTORY_SEPARATOR
    .
    'includes'
    .
    DIRECTORY_SEPARATOR
    .
    'config.php';


    require_once dirname(
        __DIR__,
        2
    )
    .
    DIRECTORY_SEPARATOR
    .
    'includes'
    .
    DIRECTORY_SEPARATOR
    .
    'gemini.php';

}
catch (
    Throwable $e
) {

    apiLog(
        'Configuration error: '
        .
        $e->getMessage()
    );


    apiResponse(
        false,
        'Unable to load AI configuration.',
        [],
        500
    );

}


/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
|
| config.php normally starts the session.
| We make sure it is started.
|
*/

if (
    session_status()
    !== PHP_SESSION_ACTIVE
) {

    session_start();

}


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
) {

    apiResponse(
        false,
        'Invalid request method.',
        [],
        405
    );

}


/*
|--------------------------------------------------------------------------
| DATABASE CHECK
|--------------------------------------------------------------------------
*/

if (
    !isset($conn)
    ||
    !($conn instanceof mysqli)
) {

    apiResponse(
        false,
        'Database connection is not available.',
        [],
        500
    );

}


/*
|--------------------------------------------------------------------------
| PATIENT LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (
    !isset(
        $_SESSION['patient_auth']
    )
    ||
    !is_array(
        $_SESSION['patient_auth']
    )
    ||
    (
        $_SESSION['patient_auth']['logged_in']
        ??
        false
    ) !== true
) {

    apiResponse(
        false,
        'Please login first.',
        [],
        401
    );

}


/*
|--------------------------------------------------------------------------
| ACCOUNT ID
|--------------------------------------------------------------------------
*/

$account_id =
    (int)(
        $_SESSION['patient_auth']['account_id']
        ??
        0
    );


if (
    $account_id <= 0
) {

    apiResponse(
        false,
        'Patient account could not be identified.',
        [],
        401
    );

}


/*
|--------------------------------------------------------------------------
| READ REQUEST BODY
|--------------------------------------------------------------------------
*/

$raw_input =
    file_get_contents(
        'php://input'
    );


$data =
    json_decode(
        $raw_input ?: '',
        true
    );


if (
    !is_array($data)
) {

    apiResponse(
        false,
        'Invalid request data.',
        [],
        400
    );

}


/*
|--------------------------------------------------------------------------
| REQUEST ACTION
|--------------------------------------------------------------------------
*/

$action =
    trim(
        (string)(
            $data['action']
            ??
            ''
        )
    );


/*
|--------------------------------------------------------------------------
| REQUESTED CONVERSATION ID
|--------------------------------------------------------------------------
*/

$requested_conversation_id =
    (int)(
        $data['conversation_id']
        ??
        0
    );


/*
|--------------------------------------------------------------------------
| CLEAR CONVERSATION
|--------------------------------------------------------------------------
*/

if (
    $action === 'clear'
) {

    /*
    |--------------------------------------------------------------------------
    | Prefer conversation ID sent by frontend
    |--------------------------------------------------------------------------
    */

    $conversation_id =
        $requested_conversation_id;


    /*
    |--------------------------------------------------------------------------
    | If not provided, use session conversation
    |--------------------------------------------------------------------------
    */

    if (
        $conversation_id <= 0
    ) {

        $conversation_id =
            (int)(
                $_SESSION['ai_conversation_id']
                ??
                0
            );

    }


    /*
    |--------------------------------------------------------------------------
    | Delete conversation
    |--------------------------------------------------------------------------
    */

    if (
        $conversation_id > 0
    ) {

        $delete_sql = "
            DELETE FROM ai_conversations
            WHERE id = ?
            AND patient_id = ?
        ";


        $delete_stmt =
            mysqli_prepare(
                $conn,
                $delete_sql
            );


        if (
            !$delete_stmt
        ) {

            apiLog(
                'Clear conversation prepare failed: '
                .
                mysqli_error($conn)
            );


            apiResponse(
                false,
                'Unable to clear conversation.',
                [],
                500
            );

        }


        mysqli_stmt_bind_param(
            $delete_stmt,
            'ii',
            $conversation_id,
            $account_id
        );


        if (
            !mysqli_stmt_execute(
                $delete_stmt
            )
        ) {

            apiLog(
                'Clear conversation execute failed: '
                .
                mysqli_stmt_error(
                    $delete_stmt
                )
            );


            mysqli_stmt_close(
                $delete_stmt
            );


            apiResponse(
                false,
                'Unable to clear conversation.',
                [],
                500
            );

        }


        mysqli_stmt_close(
            $delete_stmt
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Remove session conversation
    |--------------------------------------------------------------------------
    */

    unset(
        $_SESSION['ai_conversation_id']
    );


    apiResponse(
        true,
        'Conversation cleared.'
    );

}


/*
|--------------------------------------------------------------------------
| USER MESSAGE
|--------------------------------------------------------------------------
*/

$user_message =
    trim(
        (string)(
            $data['message']
            ??
            ''
        )
    );


/*
|--------------------------------------------------------------------------
| EMPTY MESSAGE
|--------------------------------------------------------------------------
*/

if (
    $user_message === ''
) {

    apiResponse(
        false,
        'Please enter a message.',
        [],
        400
    );

}


/*
|--------------------------------------------------------------------------
| MESSAGE LENGTH
|--------------------------------------------------------------------------
*/

if (
    mb_strlen(
        $user_message
    ) > 2000
) {

    apiResponse(
        false,
        'Message is too long. Please keep it under 2000 characters.',
        [],
        400
    );

}


/*
|--------------------------------------------------------------------------
| FIND CONVERSATION
|--------------------------------------------------------------------------
|
| If frontend provides conversation_id,
| verify that it belongs to the logged-in patient.
|
*/

$conversation_id =
    $requested_conversation_id;


/*
|--------------------------------------------------------------------------
| FALLBACK TO SESSION CONVERSATION
|--------------------------------------------------------------------------
*/

if (
    $conversation_id <= 0
) {

    $conversation_id =
        (int)(
            $_SESSION['ai_conversation_id']
            ??
            0
        );

}


/*
|--------------------------------------------------------------------------
| VERIFY CONVERSATION
|--------------------------------------------------------------------------
*/

if (
    $conversation_id > 0
) {

    $verify_sql = "
        SELECT id
        FROM ai_conversations
        WHERE id = ?
        AND patient_id = ?
        LIMIT 1
    ";


    $verify_stmt =
        mysqli_prepare(
            $conn,
            $verify_sql
        );


    if (
        !$verify_stmt
    ) {

        apiLog(
            'Conversation verification prepare failed: '
            .
            mysqli_error($conn)
        );


        apiResponse(
            false,
            'Unable to verify conversation.',
            [],
            500
        );

    }


    mysqli_stmt_bind_param(
        $verify_stmt,
        'ii',
        $conversation_id,
        $account_id
    );


    if (
        !mysqli_stmt_execute(
            $verify_stmt
        )
    ) {

        apiLog(
            'Conversation verification execute failed: '
            .
            mysqli_stmt_error(
                $verify_stmt
            )
        );


        mysqli_stmt_close(
            $verify_stmt
        );


        apiResponse(
            false,
            'Unable to verify conversation.',
            [],
            500
        );

    }


    /*
    |--------------------------------------------------------------------------
    | IMPORTANT:
    | Do NOT use mysqli_stmt_get_result()
    |--------------------------------------------------------------------------
    */

    mysqli_stmt_bind_result(
        $verify_stmt,
        $verified_id
    );


    $conversation_exists =
        mysqli_stmt_fetch(
            $verify_stmt
        );


    mysqli_stmt_close(
        $verify_stmt
    );


    if (
        $conversation_exists !== true
    ) {

        $conversation_id =
            0;

    }

}


/*
|--------------------------------------------------------------------------
| CREATE NEW CONVERSATION
|--------------------------------------------------------------------------
*/

if (
    $conversation_id <= 0
) {

    /*
    |--------------------------------------------------------------------------
    | Generate title
    |--------------------------------------------------------------------------
    */

    $title =
        trim(
            preg_replace(
                '/\s+/',
                ' ',
                $user_message
            )
        );


    if (
        mb_strlen(
            $title
        ) > 60
    ) {

        $title =
            mb_substr(
                $title,
                0,
                57
            )
            .
            '...';

    }


    if (
        $title === ''
    ) {

        $title =
            'New Conversation';

    }


    /*
    |--------------------------------------------------------------------------
    | INSERT CONVERSATION
    |--------------------------------------------------------------------------
    */

    $insert_sql = "
        INSERT INTO ai_conversations
        (
            patient_id,
            title
        )
        VALUES
        (
            ?,
            ?
        )
    ";


    $insert_stmt =
        mysqli_prepare(
            $conn,
            $insert_sql
        );


    if (
        !$insert_stmt
    ) {

        apiLog(
            'Conversation insert prepare failed: '
            .
            mysqli_error($conn)
        );


        apiResponse(
            false,
            'Unable to create AI conversation.',
            [],
            500
        );

    }


    mysqli_stmt_bind_param(
        $insert_stmt,
        'is',
        $account_id,
        $title
    );


    if (
        !mysqli_stmt_execute(
            $insert_stmt
        )
    ) {

        apiLog(
            'Conversation insert failed: '
            .
            mysqli_stmt_error(
                $insert_stmt
            )
        );


        mysqli_stmt_close(
            $insert_stmt
        );


        apiResponse(
            false,
            'Unable to create AI conversation.',
            [],
            500
        );

    }


    $conversation_id =
        (int)(
            mysqli_insert_id(
                $conn
            )
        );


    mysqli_stmt_close(
        $insert_stmt
    );


    if (
        $conversation_id <= 0
    ) {

        apiResponse(
            false,
            'Unable to create AI conversation.',
            [],
            500
        );

    }

}


/*
|--------------------------------------------------------------------------
| SAVE CURRENT CONVERSATION IN SESSION
|--------------------------------------------------------------------------
*/

$_SESSION['ai_conversation_id'] =
    $conversation_id;


/*
|--------------------------------------------------------------------------
| GET PREVIOUS MESSAGES
|--------------------------------------------------------------------------
|
| Latest 12 messages.
|
*/

$history_sql = "
    SELECT
        role,
        message
    FROM ai_messages
    WHERE conversation_id = ?
    ORDER BY id DESC
    LIMIT 12
";


$history_stmt =
    mysqli_prepare(
        $conn,
        $history_sql
    );


$history_messages = [];


if (
    !$history_stmt
) {

    apiLog(
        'History prepare failed: '
        .
        mysqli_error($conn)
    );


    apiResponse(
        false,
        'Unable to load conversation history.',
        [],
        500
    );

}


mysqli_stmt_bind_param(
    $history_stmt,
    'i',
    $conversation_id
);


if (
    !mysqli_stmt_execute(
        $history_stmt
    )
) {

    apiLog(
        'History execute failed: '
        .
        mysqli_stmt_error(
            $history_stmt
        )
    );


    mysqli_stmt_close(
        $history_stmt
    );


    apiResponse(
        false,
        'Unable to load conversation history.',
        [],
        500
    );

}


/*
|--------------------------------------------------------------------------
| IMPORTANT:
| bind_result instead of get_result
|--------------------------------------------------------------------------
*/

mysqli_stmt_bind_result(
    $history_stmt,
    $history_role,
    $history_message
);


while (
    mysqli_stmt_fetch(
        $history_stmt
    )
) {

    $role =
        (string)$history_role;


    $message =
        (string)$history_message;


    if (
        !in_array(
            $role,
            [
                'user',
                'assistant'
            ],
            true
        )
    ) {

        continue;

    }


    if (
        trim($message) === ''
    ) {

        continue;

    }


    $history_messages[] = [

        'role' =>
            $role,

        'content' =>
            $message

    ];

}


mysqli_stmt_close(
    $history_stmt
);


/*
|--------------------------------------------------------------------------
| Reverse history
|--------------------------------------------------------------------------
|
| SQL gives newest → oldest.
| Gemini needs oldest → newest.
|
*/

$history_messages =
    array_reverse(
        $history_messages
    );


/*
|--------------------------------------------------------------------------
| SYSTEM MESSAGE
|--------------------------------------------------------------------------
*/

$system_message = <<<SYSTEM

You are the AI Health Assistant inside the "Care Your Health" Smart Hospital Management System.

Your job is to be a highly capable, natural, reliable and practical conversational AI assistant.

You can help the user with:
- General questions
- Health education
- Medical information
- Hospital-related guidance
- Appointment-related guidance when information is available
- Programming and software development
- PHP, MySQL, HTML, CSS, JavaScript, SQL, Kotlin, Android and APIs
- Debugging
- Project architecture
- Writing and rewriting
- Study and educational questions
- General knowledge
- Everyday conversations

IMPORTANT:
You are an AI assistant, not a human doctor, hospital employee, programmer or administrator.
Never claim to have performed an action that you cannot actually perform.
Never claim to have accessed a database, patient record, file, API, device, server, internet or hospital system unless that information is actually provided to you in the conversation or available through the current request.

======================================================================
1. UNDERSTAND THE USER FIRST
======================================================================

Before answering, determine what the user is actually trying to accomplish.

Possible intents include:
- Asking a question
- Asking for an explanation
- Asking for instructions
- Asking for code
- Debugging an error
- Modifying existing code
- Designing a UI
- Asking about health
- Asking about a hospital or appointment
- Asking for an opinion or comparison
- Continuing a previous discussion
- Casual conversation

Answer the actual request instead of responding to keywords alone.

If the user gives enough information to answer, answer directly.

Do not ask unnecessary clarification questions.

If critical information is missing and the answer genuinely depends on it, ask the minimum useful question.

Do not repeatedly ask for information that already exists in the conversation.

======================================================================
2. CONVERSATION MEMORY AND CONTEXT
======================================================================

Use the previous conversation context intelligently.

If the user says things such as:
- "same problem"
- "continue"
- "this code"
- "previous code"
- "above code"
- "make it better"
- "fix this"
- "do the same"
- "now add..."
- "what about this?"
- "it is not working"

interpret the request using the relevant previous context.

Do not make the user repeat information unnecessarily.

If the user corrects previous information, use the corrected information from that point onward.

Do not stubbornly continue using information that the user has corrected.

Maintain consistency throughout the conversation.

======================================================================
3. NATURAL CONVERSATION
======================================================================

Talk naturally, like a capable professional AI assistant.

Do not sound robotic.

Do not begin every response with:
- "Sure!"
- "Certainly!"
- "Absolutely!"
- "Of course!"

Use these only when they genuinely fit the conversation.

Do not use unnecessary filler.

Do not repeat the user's question unnecessarily.

Do not force a question at the end of every answer.

If no follow-up is needed, simply finish the answer.

If the user is casually talking, respond naturally rather than turning everything into a formal report.

Match the user's communication style when appropriate.

If the user writes in Hinglish, Hindi or another language, respond naturally in that language unless they request another language.

======================================================================
4. ANSWER LENGTH
======================================================================

Adapt the answer length to the user's request.

Simple question:
- Give a concise answer.

Moderate question:
- Give a clear explanation with the necessary details.

Complex technical or educational question:
- Give a structured and sufficiently detailed answer.

Do not make short questions unnecessarily long.

Do not oversimplify complex technical problems.

Prioritize useful information over unnecessary words.

======================================================================
5. ACCURACY AND HONESTY
======================================================================

Accuracy is more important than sounding confident.

Never invent:
- Facts
- Patient information
- Medical history
- Diagnoses
- Test results
- Vital signs
- Medications
- Database records
- Database columns
- File names
- API endpoints
- Configuration values
- Programming libraries
- Functions
- Error messages
- Sources
- URLs
- Server responses
- System capabilities

If something is unknown, say that it is unknown.

If something is uncertain, clearly communicate the uncertainty.

Never pretend that an assumption is a fact.

When making an assumption, clearly identify it as an assumption.

======================================================================
6. PROGRAMMING AND SOFTWARE DEVELOPMENT
======================================================================

When the user asks for programming help, behave like an experienced software engineer.

First understand:
- What the user wants
- What currently exists
- What is actually broken
- What should remain unchanged

Then provide the smallest reliable solution that solves the actual problem.

Supported development topics may include:
- PHP
- MySQL / MySQLi
- HTML
- CSS
- JavaScript
- SQL
- Kotlin
- Android
- Android Studio
- APIs
- JSON
- AJAX / Fetch
- Sessions
- Authentication
- WebView
- REST APIs
- Database design
- UI/UX
- Debugging
- Security

When modifying existing code:

1. Preserve the user's existing architecture whenever possible.
2. Preserve existing filenames.
3. Preserve existing routes.
4. Preserve existing database table names.
5. Preserve existing variable names when practical.
6. Preserve existing functionality.
7. Do not rename things unnecessarily.
8. Do not remove working features without a reason.
9. Do not invent missing files or database structures.
10. Do not rewrite the entire project when a smaller change is sufficient.

If the user says:

"only change this"

then change only that part unless another change is technically required.

If the user says:

"don't change anything else"

strictly preserve everything else.

======================================================================
7. COMPLETE CODE REQUIREMENT
======================================================================

When the user asks for:
- "full code"
- "complete code"
- "full file"
- "complete file"
- "replacement code"

provide the complete requested file.

Never write:

"rest of the code remains the same"

when the user explicitly requested a complete file.

Never replace important code with:

...

or:

// existing code

unless the user specifically asked for a partial snippet.

When giving code, make sure:
- Brackets are balanced.
- Quotes are balanced.
- PHP syntax is consistent.
- JavaScript syntax is consistent.
- CSS braces are correct.
- Variables are defined before use.
- Function names are consistent.
- HTML tags are properly nested.
- SQL statements are internally consistent.

======================================================================
8. DEBUGGING
======================================================================

When debugging a problem, use this structure when useful:

## Problem
Clearly identify the actual problem.

## Why It Happens
Explain the real cause briefly.

## Fix
Explain exactly what needs to change.

## Complete Code
Provide the complete replacement file if requested.

## Test
Give practical steps to verify the fix.

Do not blindly guess the cause.

If multiple causes are possible, explain the most likely causes and what information would distinguish them.

If the user provides an exact error message, use that exact error as evidence.

======================================================================
9. DATABASE SAFETY
======================================================================

For database-related programming:

- Prefer prepared statements.
- Do not recommend SQL injection-prone string concatenation when a prepared statement is appropriate.
- Preserve existing schema unless the user explicitly asks for schema changes.
- Never invent a database column.
- Never claim a record exists unless the system actually provides that record.
- Keep patient-specific data isolated by the authenticated patient/account identifier.
- Do not expose another patient's information.

If a database change is actually required, clearly state which table/column/change is required.

======================================================================
10. SECURITY
======================================================================

Prefer secure development practices.

Pay attention to:
- Authentication
- Authorization
- CSRF protection
- SQL injection
- XSS
- Password hashing
- Input validation
- File upload validation
- API key protection
- Sensitive information exposure

Never expose secrets in responses.

Never reveal or reproduce:
- API keys
- Passwords
- App passwords
- Authentication tokens
- Session secrets
- Private credentials

If the user accidentally provides a secret, do not repeat it unnecessarily.

======================================================================
11. CARE YOUR HEALTH PROJECT CONTEXT
======================================================================

Understand that this assistant is part of a Smart Hospital Management System.

Relevant concepts may include:
- Patients
- Doctors
- Hospitals
- Departments
- Appointments
- Patient profiles
- Medical information
- AI assistance
- Medical image analysis
- Hospital services
- Notifications
- Authentication
- Patient conversations

However, project context does NOT mean that you automatically have access to real hospital data.

Never fabricate:
- Doctor availability
- Appointment availability
- Hospital records
- Patient records
- Medical reports
- Diagnoses
- Laboratory values
- Imaging findings

Only use such information when it is actually supplied by the application/request.

======================================================================
12. MEDICAL QUESTIONS
======================================================================

For medical questions, provide useful general health information while maintaining appropriate medical safety.

Do not diagnose with certainty.

Never say:

"You definitely have..."
"You certainly have..."
"This proves that you have..."

Prefer:

"This can be caused by..."
"Possible causes include..."
"This may be consistent with..."
"More information would be needed to determine the cause..."

Explain relevant possibilities clearly without creating unnecessary fear.

For potentially serious symptoms, explain appropriate warning signs and when urgent medical evaluation may be appropriate.

Do not create panic.

Do not minimize potentially serious symptoms.

======================================================================
13. MEDICATIONS
======================================================================

For medication-related questions:

- Explain general purpose when appropriate.
- Explain common precautions when relevant.
- Do not invent dosage information.
- Do not prescribe medication.
- Do not tell the user to stop a prescribed medication without appropriate professional guidance.
- If dosage depends on age, weight, condition, kidney/liver function or another factor, explain that individualized medical advice may be required.

======================================================================
14. MEDICAL IMAGES
======================================================================

If an actual medical image is provided and image analysis is supported:

- Describe only what can reasonably be observed.
- Separate observation from interpretation.
- State limitations.
- Do not pretend an image was analyzed if no image was actually provided.
- Do not claim certainty from an image alone.
- Encourage appropriate professional evaluation when clinically relevant.

Never invent imaging findings.

======================================================================
15. CURRENT OR LIVE INFORMATION
======================================================================

Do not pretend to have live information if it is not available.

Do not claim that something is:
- currently available
- currently open
- currently working
- currently in stock
- currently scheduled
- currently updated

unless the current information is actually available.

When current information is unavailable, say so clearly.

======================================================================
16. FOLLOW-UP QUESTIONS
======================================================================

Ask a follow-up question only when it meaningfully improves the answer.

Good follow-up:
- Requests missing information that is genuinely required.
- Helps diagnose a programming problem.
- Clarifies an ambiguous technical requirement.
- Helps distinguish between important medical possibilities.

Bad follow-up:
- Asking something the user already answered.
- Asking a question just to continue the conversation.
- Asking unnecessary personal information.
- Asking several questions when one is enough.

Answer first whenever it is safely possible.

======================================================================
17. FORMATTING
======================================================================

Use Markdown naturally when it improves readability.

You may use:
- Headings
- Bold text
- Bullet lists
- Numbered lists
- Tables
- Short paragraphs
- Fenced code blocks

Do not over-format simple answers.

Do not create unnecessary headings for a one-sentence answer.

For code, always use fenced code blocks with the appropriate language when possible.

For large code responses, keep formatting clean and easy to copy.

======================================================================
18. UI / DESIGN REQUESTS
======================================================================

When the user asks for UI or UX improvements:

Prioritize:
- Mobile responsiveness
- Clean spacing
- Visual hierarchy
- Consistent typography
- Touch-friendly controls
- Smooth interaction
- Accessibility
- Professional appearance
- Existing project consistency

Do not unnecessarily change the user's complete design system when they only request one component.

When the user asks for "best UI", improve the actual usability, not only colors and decoration.

======================================================================
19. ERROR HANDLING
======================================================================

If an operation fails:

- Do not pretend it succeeded.
- Explain what failed.
- Give the most likely cause.
- Give the practical fix.
- If the exact cause cannot be determined, say what information is needed.

Do not generate fake successful results.

======================================================================
20. USER CORRECTIONS
======================================================================

If the user says something is wrong:

- Accept the correction.
- Re-evaluate the previous answer.
- Do not defend an incorrect answer.
- Fix the actual problem.
- Preserve working parts whenever possible.

======================================================================
21. NO ARTIFICIAL RESPONSE TAGS
======================================================================

Do not automatically add artificial labels such as:

[IMPORTANT]
[INFO]
[SUCCESS]
[WARNING]
[EMERGENCY]
[NOTE]

Use normal Markdown headings and natural language instead.

Only use such tags if the user explicitly requests them.

======================================================================
22. FINAL RESPONSE QUALITY CHECK
======================================================================

Before producing the answer, internally verify:

1. Did I understand the user's actual request?
2. Did I use relevant previous conversation context?
3. Did I avoid unnecessary clarification questions?
4. Did I avoid unsupported claims?
5. Did I avoid inventing project/database information?
6. If code was requested, is it complete when required?
7. Is the code internally consistent?
8. Did I preserve the user's architecture and filenames?
9. Did I avoid changing unrelated functionality?
10. Is the response practical and easy to understand?
11. For medical topics, did I remain appropriately cautious?
12. Did I avoid pretending to have capabilities or information I do not have?

The final answer should be useful, honest, technically consistent and natural.

Also give answer of Date of Asked by user


SYSTEM;


/*
|--------------------------------------------------------------------------
| BUILD GEMINI CONTENTS
|--------------------------------------------------------------------------
*/

$contents = [];


/*
|--------------------------------------------------------------------------
| CONVERSATION HISTORY
|--------------------------------------------------------------------------
|
| Gemini uses:
|
| user  -> user
| assistant -> model
|
|--------------------------------------------------------------------------
*/

foreach (
    $history_messages
    as $history_message
) {

    $history_role =
        $history_message['role'];

    $history_content =
        $history_message['content'];


    /*
    |--------------------------------------------------------------------------
    | Convert database role to Gemini role
    |--------------------------------------------------------------------------
    */

    if (
        $history_role === 'assistant'
    ) {

        $gemini_role =
            'model';

    }
    else {

        $gemini_role =
            'user';

    }


    $contents[] = [

        'role' =>
            $gemini_role,

        'parts' => [

            [
                'text' =>
                    $history_content
            ]

        ]

    ];

}


/*
|--------------------------------------------------------------------------
| CURRENT USER MESSAGE
|--------------------------------------------------------------------------
*/

$contents[] = [

    'role' =>
        'user',

    'parts' => [

        [
            'text' =>
                $user_message
        ]

    ]

];


/*
|--------------------------------------------------------------------------
| GEMINI PAYLOAD
|--------------------------------------------------------------------------
*/

$payload = [

    /*
    |--------------------------------------------------------------------------
    | SYSTEM INSTRUCTION
    |--------------------------------------------------------------------------
    */

    'systemInstruction' => [

        'parts' => [

            [
                'text' =>
                    $system_message
            ]

        ]

    ],


    /*
    |--------------------------------------------------------------------------
    | CONVERSATION CONTENT
    |--------------------------------------------------------------------------
    */

    'contents' =>
        $contents,


    /*
    |--------------------------------------------------------------------------
    | GENERATION CONFIG
    |--------------------------------------------------------------------------
    */

    'generationConfig' => [

        'temperature' =>
            0.7,

        'topP' =>
            0.9,

        'maxOutputTokens' =>
            2500

    ]

];


/*
|--------------------------------------------------------------------------
| ENCODE PAYLOAD
|--------------------------------------------------------------------------
*/

$json_payload =
    json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );


if (
    $json_payload === false
) {

    apiLog(
        'JSON encode failed: '
        .
        json_last_error_msg()
    );


    apiResponse(
        false,
        'Unable to prepare AI request.',
        [],
        500
    );

}


/*
|--------------------------------------------------------------------------
| CHECK GEMINI CONFIG
|--------------------------------------------------------------------------
*/

if (
    !defined(
        'GEMINI_API_URL'
    )
    ||
    !defined(
        'GEMINI_API_KEY'
    )
    ||
    !defined(
        'GEMINI_MODEL'
    )
) {

    apiLog(
        'Gemini configuration constants are missing.'
    );


    apiResponse(
        false,
        'AI service configuration is incomplete.',
        [],
        500
    );

}


/*
|--------------------------------------------------------------------------
| CURL INITIALIZATION
|--------------------------------------------------------------------------
*/

$curl =
    curl_init(
        GEMINI_API_URL
    );


if (
    $curl === false
) {

    apiResponse(
        false,
        'Unable to initialize AI service connection.',
        [],
        502
    );

}


/*
|--------------------------------------------------------------------------
| CURL OPTIONS
|--------------------------------------------------------------------------
*/

curl_setopt_array(
    $curl,
    [

        CURLOPT_POST =>
            true,

        CURLOPT_RETURNTRANSFER =>
            true,

        CURLOPT_CONNECTTIMEOUT =>
            10,

        CURLOPT_TIMEOUT =>
            60,

        CURLOPT_HTTP_VERSION =>
            CURL_HTTP_VERSION_1_1,

        CURLOPT_HTTPHEADER => [

            'x-goog-api-key: '
            .
            GEMINI_API_KEY,

            'Content-Type: application/json',

            'Accept: application/json'

        ],

        CURLOPT_POSTFIELDS =>
            $json_payload

    ]
);


/*
|--------------------------------------------------------------------------
| SEND REQUEST
|--------------------------------------------------------------------------
*/

$response =
    curl_exec(
        $curl
    );


$http_code =
    (int)(
        curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        )
    );


$curl_error =
    curl_error(
        $curl
    );


$curl_errno =
    curl_errno(
        $curl
    );


curl_close(
    $curl
);


/*
|--------------------------------------------------------------------------
| CURL ERROR
|--------------------------------------------------------------------------
*/

if (
    $response === false
) {

    apiLog(
        'Gemini cURL error '
        .
        $curl_errno
        .
        ': '
        .
        $curl_error
    );


    apiResponse(
        false,
        'Unable to connect to the AI service. Please try again.',
        [],
        502
    );

}


/*
|--------------------------------------------------------------------------
| EMPTY GEMINI RESPONSE
|--------------------------------------------------------------------------
*/

if (
    trim(
        (string)$response
    ) === ''
) {

    apiLog(
        'Gemini returned an empty response. HTTP: '
        .
        $http_code
    );


    apiResponse(
        false,
        'The AI service returned an empty response.',
        [],
        502
    );

}


/*
|--------------------------------------------------------------------------
| DECODE GEMINI RESPONSE
|--------------------------------------------------------------------------
*/

$gemini_data =
    json_decode(
        $response,
        true
    );


if (
    !is_array(
        $gemini_data
    )
) {

    apiLog(
        'Invalid Gemini JSON response. HTTP: '
        .
        $http_code
        .
        ' Response: '
        .
        mb_substr(
            (string)$response,
            0,
            1000
        )
    );


    apiResponse(
        false,
        'The AI service returned an invalid response.',
        [],
        502
    );

}


/*
|--------------------------------------------------------------------------
| GEMINI API ERROR
|--------------------------------------------------------------------------
*/

if (
    $http_code < 200
    ||
    $http_code >= 300
) {

    $error_message =
        'The AI service could not process your request.';


    if (
        isset(
            $gemini_data['error']
        )
    ) {

        if (
            is_string(
                $gemini_data['error']
            )
        ) {

            $error_message =
                $gemini_data['error'];

        }
        elseif (
            is_array(
                $gemini_data['error']
            )
            &&
            isset(
                $gemini_data['error']['message']
            )
        ) {

            $error_message =
                (string)
                $gemini_data['error']['message'];

        }

    }


    apiLog(
        'Gemini API error HTTP '
        .
        $http_code
        .
        ': '
        .
        $error_message
    );


    /*
    |--------------------------------------------------------------------------
    | FRIENDLY ERROR MESSAGES
    |--------------------------------------------------------------------------
    */

    if (
        $http_code === 429
    ) {

        $error_message =
            'The AI service is temporarily busy. Please try again in a moment.';

    }


    elseif (
        $http_code === 503
    ) {

        $error_message =
            'The AI service is temporarily unavailable. Please try again later.';

    }


    elseif (
        $http_code === 401
        ||
        $http_code === 403
    ) {

        $error_message =
            'The AI service authentication failed. Please check the Gemini API configuration.';

    }


    apiResponse(
        false,
        $error_message,
        [],
        $http_code >= 400
            ? $http_code
            : 502
    );

}


/*
|--------------------------------------------------------------------------
| EXTRACT GEMINI RESPONSE
|--------------------------------------------------------------------------
*/

$ai_message =
    '';


if (
    isset(
        $gemini_data['candidates'][0]['content']['parts'][0]['text']
    )
) {

    $ai_message =
        (string)
        $gemini_data['candidates'][0]['content']['parts'][0]['text'];

}


/*
|--------------------------------------------------------------------------
| CLEAN EXCESSIVE BLANK LINES
|--------------------------------------------------------------------------
*/

$ai_message =
    preg_replace(
        "/\n{3,}/",
        "\n\n",
        (string)$ai_message
    );


$ai_message =
    trim(
        (string)$ai_message
    );


/*
|--------------------------------------------------------------------------
| EMPTY AI RESPONSE
|--------------------------------------------------------------------------
*/

if (
    $ai_message === ''
) {

    apiLog(
        'Gemini response did not contain usable AI message.'
    );


    apiResponse(
        false,
        'The AI returned an empty response. Please try again.',
        [],
        502
    );

}


/*
|--------------------------------------------------------------------------
| SAVE USER MESSAGE
|--------------------------------------------------------------------------
*/

$save_user_sql = "
    INSERT INTO ai_messages
    (
        conversation_id,
        role,
        message
    )
    VALUES
    (
        ?,
        'user',
        ?
    )
";


$save_user_stmt =
    mysqli_prepare(
        $conn,
        $save_user_sql
    );


if (
    !$save_user_stmt
) {

    apiLog(
        'User message prepare failed: '
        .
        mysqli_error($conn)
    );


    apiResponse(
        false,
        'AI response received, but the message could not be saved.',
        [],
        500
    );

}


mysqli_stmt_bind_param(
    $save_user_stmt,
    'is',
    $conversation_id,
    $user_message
);


if (
    !mysqli_stmt_execute(
        $save_user_stmt
    )
) {

    apiLog(
        'User message save failed: '
        .
        mysqli_stmt_error(
            $save_user_stmt
        )
    );


    mysqli_stmt_close(
        $save_user_stmt
    );


    apiResponse(
        false,
        'AI response received, but the message could not be saved.',
        [],
        500
    );

}


mysqli_stmt_close(
    $save_user_stmt
);


/*
|--------------------------------------------------------------------------
| SAVE AI MESSAGE
|--------------------------------------------------------------------------
*/

$save_ai_sql = "
    INSERT INTO ai_messages
    (
        conversation_id,
        role,
        message
    )
    VALUES
    (
        ?,
        'assistant',
        ?
    )
";


$save_ai_stmt =
    mysqli_prepare(
        $conn,
        $save_ai_sql
    );


if (
    !$save_ai_stmt
) {

    apiLog(
        'AI message prepare failed: '
        .
        mysqli_error($conn)
    );


    apiResponse(
        false,
        'AI response was generated but could not be saved.',
        [],
        500
    );

}


mysqli_stmt_bind_param(
    $save_ai_stmt,
    'is',
    $conversation_id,
    $ai_message
);


if (
    !mysqli_stmt_execute(
        $save_ai_stmt
    )
) {

    apiLog(
        'AI message save failed: '
        .
        mysqli_stmt_error(
            $save_ai_stmt
        )
    );


    mysqli_stmt_close(
        $save_ai_stmt
    );


    apiResponse(
        false,
        'AI response was generated but could not be saved.',
        [],
        500
    );

}


mysqli_stmt_close(
    $save_ai_stmt
);


/*
|--------------------------------------------------------------------------
| UPDATE CONVERSATION
|--------------------------------------------------------------------------
*/

$update_sql = "
    UPDATE ai_conversations
    SET updated_at = CURRENT_TIMESTAMP
    WHERE id = ?
    AND patient_id = ?
";


$update_stmt =
    mysqli_prepare(
        $conn,
        $update_sql
    );


if (
    $update_stmt
) {

    mysqli_stmt_bind_param(
        $update_stmt,
        'ii',
        $conversation_id,
        $account_id
    );


    mysqli_stmt_execute(
        $update_stmt
    );


    mysqli_stmt_close(
        $update_stmt
    );

}


/*
|--------------------------------------------------------------------------
| FINAL SUCCESS RESPONSE
|--------------------------------------------------------------------------
*/

apiResponse(
    true,
    $ai_message,
    [
        'conversation_id' =>
            $conversation_id
    ]
);