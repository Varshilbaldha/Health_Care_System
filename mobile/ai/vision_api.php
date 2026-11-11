<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| AI MEDICAL IMAGE ANALYZER
|--------------------------------------------------------------------------
| Smart Hospital
|
| FEATURES:
| - Patient authentication
| - Session protection
| - CSRF protection
| - Secure image validation
| - JPG / PNG / WEBP
| - Maximum 10 MB
| - NVIDIA Vision AI
| - Medical image analysis
| - Image preview
| - Structured AI analysis
| - Urgency detection
| - Copy analysis
| - Responsive mobile UI
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| PHP SETTINGS
|--------------------------------------------------------------------------
*/

@set_time_limit(180);


/*
|--------------------------------------------------------------------------
| LOAD MAIN CONFIG
|--------------------------------------------------------------------------
|
| This should provide:
| - $conn
| - session configuration
| - database configuration
|
*/

require_once dirname(__DIR__, 2)
    . DIRECTORY_SEPARATOR
    . 'includes'
    . DIRECTORY_SEPARATOR
    . 'config.php';


/*
|--------------------------------------------------------------------------
| START SESSION IF NOT ALREADY STARTED
|--------------------------------------------------------------------------
*/

if (
    session_status() === PHP_SESSION_NONE
) {

    session_start();

}


/*
|--------------------------------------------------------------------------
| LOAD NVIDIA CONFIG
|--------------------------------------------------------------------------
*/

require_once dirname(__DIR__, 2)
    . DIRECTORY_SEPARATOR
    . 'includes'
    . DIRECTORY_SEPARATOR
    . 'nvidia.php';


/*
|--------------------------------------------------------------------------
| PATIENT AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['patient_auth'])
    ||
    !is_array($_SESSION['patient_auth'])
    ||
    ($_SESSION['patient_auth']['logged_in'] ?? false) !== true
) {

    header(
        'Location: ../login.php'
    );

    exit;

}


/*
|--------------------------------------------------------------------------
| PATIENT ACCOUNT ID
|--------------------------------------------------------------------------
*/

$account_id =
    (int)(
        $_SESSION['patient_auth']['account_id']
        ?? 0
    );


if (
    $account_id <= 0
) {

    header(
        'Location: ../pages/login.php'
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
    !empty(
        $_SESSION['patient_auth']['first_name']
    )
) {

    $patient_name =
        (string)
        $_SESSION['patient_auth']['first_name'];

}


if (
    !empty(
        $_SESSION['patient_auth']['last_name']
    )
) {

    $patient_name .=
        ' '
        .
        (string)
        $_SESSION['patient_auth']['last_name'];

}


/*
|--------------------------------------------------------------------------
| ESCAPE PATIENT NAME
|--------------------------------------------------------------------------
*/

$safePatientName =
    htmlspecialchars(
        $patient_name,
        ENT_QUOTES,
        'UTF-8'
    );


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['vision_csrf_token'])
) {

    $_SESSION['vision_csrf_token'] =
        bin2hex(
            random_bytes(32)
        );

}


$csrfToken =
    $_SESSION['vision_csrf_token'];


/*
|--------------------------------------------------------------------------
| NVIDIA CONFIGURATION
|--------------------------------------------------------------------------
*/

$apiUrl =
    NVIDIA_VISION_API_URL;

$apiKey =
    NVIDIA_VISION_API_KEY;

$model =
    NVIDIA_VISION_MODEL;


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$result = '';

$error = '';

$uploadedImage = '';

$fileName = '';

$httpCode = 0;

$curlError = '';

$responseTime = 0;

$imageMime = '';

$imageWidth = 0;

$imageHeight = 0;

$analysisStarted = false;


/*
|--------------------------------------------------------------------------
| ALLOWED FILE TYPES
|--------------------------------------------------------------------------
*/

$allowedMimeTypes = [

    'image/jpeg',
    'image/png',
    'image/webp'

];


/*
|--------------------------------------------------------------------------
| MAX FILE SIZE
|--------------------------------------------------------------------------
*/

$maxFileSize =
    10 * 1024 * 1024;


/*
|--------------------------------------------------------------------------
| HANDLE FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $analysisStarted = true;


    /*
    |--------------------------------------------------------------------------
    | CSRF CHECK
    |--------------------------------------------------------------------------
    */

    $submittedCsrf =
        (string)(
            $_POST['csrf_token']
            ?? ''
        );


    if (
        !hash_equals(
            $csrfToken,
            $submittedCsrf
        )
    ) {

        $error =
            'Security verification failed. Please refresh the page and try again.';

    }


    /*
    |--------------------------------------------------------------------------
    | API KEY CHECK
    |--------------------------------------------------------------------------
    */

    elseif (
        trim($apiKey) === ''
        ||
        $apiKey === 'YOUR_NVIDIA_API_KEY_HERE'
    ) {

        $error =
            'AI service is not configured. Please configure the NVIDIA API key.';

    }


    /*
    |--------------------------------------------------------------------------
    | FILE CHECK
    |--------------------------------------------------------------------------
    */

    elseif (
        !isset(
            $_FILES['medical_image']
        )
    ) {

        $error =
            'Please select a medical image.';

    }


    elseif (
        !is_array(
            $_FILES['medical_image']
        )
    ) {

        $error =
            'Invalid image upload.';

    }


    elseif (
        $_FILES['medical_image']['error']
        !== UPLOAD_ERR_OK
    ) {

        $error =
            'The image upload failed. Please try again.';

    }


    else {

        /*
        |--------------------------------------------------------------------------
        | FILE INFORMATION
        |--------------------------------------------------------------------------
        */

        $file =
            $_FILES['medical_image'];


        $fileName =
            (string)(
                $file['name']
                ?? 'medical-image'
            );


        /*
        |--------------------------------------------------------------------------
        | FILE SIZE
        |--------------------------------------------------------------------------
        */

        if (
            (int)$file['size']
            <= 0
        ) {

            $error =
                'The uploaded image is empty.';

        }


        elseif (
            (int)$file['size']
            >
            $maxFileSize
        ) {

            $error =
                'Image size must be less than 10 MB.';

        }


        /*
        |--------------------------------------------------------------------------
        | TEMP FILE CHECK
        |--------------------------------------------------------------------------
        */

        elseif (
            !isset(
                $file['tmp_name']
            )
            ||
            !is_uploaded_file(
                $file['tmp_name']
            )
        ) {

            $error =
                'Invalid uploaded file.';

        }


        /*
        |--------------------------------------------------------------------------
        | MIME TYPE
        |--------------------------------------------------------------------------
        */

        else {

            $finfo =
                new finfo(
                    FILEINFO_MIME_TYPE
                );


            $mime =
                $finfo->file(
                    $file['tmp_name']
                );


            if (
                !in_array(
                    $mime,
                    $allowedMimeTypes,
                    true
                )
            ) {

                $error =
                    'Only JPG, PNG and WEBP images are supported.';

            }

            else {

                $imageMime =
                    $mime;

            }

        }


        /*
        |--------------------------------------------------------------------------
        | IMAGE DIMENSIONS
        |--------------------------------------------------------------------------
        */

        if (
            $error === ''
        ) {

            $imageInfo =
                @getimagesize(
                    $file['tmp_name']
                );


            if (
                $imageInfo === false
            ) {

                $error =
                    'The uploaded file is not a valid image.';

            }

            else {

                $imageWidth =
                    (int)(
                        $imageInfo[0]
                        ?? 0
                    );


                $imageHeight =
                    (int)(
                        $imageInfo[1]
                        ?? 0
                    );


                if (
                    $imageWidth <= 0
                    ||
                    $imageHeight <= 0
                ) {

                    $error =
                        'Unable to determine image dimensions.';

                }

            }

        }


        /*
        |--------------------------------------------------------------------------
        | READ IMAGE
        |--------------------------------------------------------------------------
        */

        if (
            $error === ''
        ) {

            $imageData =
                file_get_contents(
                    $file['tmp_name']
                );


            if (
                $imageData === false
            ) {

                $error =
                    'Unable to read the uploaded image.';

            }

        }


        /*
        |--------------------------------------------------------------------------
        | CREATE DATA URL
        |--------------------------------------------------------------------------
        */

        if (
            $error === ''
        ) {

            $base64Image =
                base64_encode(
                    $imageData
                );


            $imageDataUrl =
                'data:'
                .
                $imageMime
                .
                ';base64,'
                .
                $base64Image;


            /*
            |--------------------------------------------------------------------------
            | IMAGE FOR PAGE
            |--------------------------------------------------------------------------
            */

            $uploadedImage =
                $imageDataUrl;

        }


        /*
        |--------------------------------------------------------------------------
        | SYSTEM PROMPT
        |--------------------------------------------------------------------------
        */

        if (
            $error === ''
        ) {

            $systemPrompt = <<<'PROMPT'

You are the Smart Hospital AI Medical Image Analysis Assistant.

You analyze uploaded medical images carefully and explain your observations in simple patient-friendly language.

IMPORTANT SAFETY RULES:

1. First determine whether the uploaded image appears to be:
   - medical image
   - X-ray
   - CT
   - MRI
   - ultrasound
   - medical scan
   - medical photograph
   - medical document/report
   - or non-medical image.

2. NEVER invent findings.

3. Only describe findings that can reasonably be observed from the image.

4. If image quality, resolution, cropping, positioning, lighting, artifacts or other limitations affect interpretation, clearly mention them.

5. If the image is not medical, explicitly say that it is not a medical image.

6. Never pretend to see something that cannot reasonably be seen.

7. Never provide a guaranteed diagnosis.

8. Possible conditions must always be described as possibilities or differential considerations.

9. Do not unnecessarily frighten the patient.

10. If potentially urgent findings could reasonably be present, clearly explain that professional medical evaluation may be needed.

11. Never replace a qualified radiologist, doctor or healthcare professional.

12. Do not provide treatment or medication instructions as if you are the patient's doctor.

13. Do not invent:
   - patient history
   - age
   - gender
   - symptoms
   - laboratory values
   - medications
   - previous diagnoses
   - vital signs
   - imaging results not visible in the image.

14. Use concise but useful explanations.

15. Avoid giant paragraphs.

16. Use headings, bullets and numbered steps when useful.

17. Maintain a professional, calm and patient-friendly tone.

RETURN EXACTLY THESE SECTION HEADINGS:

🩻 IMAGE OVERVIEW

🔎 DETAILED OBSERVATIONS

⚠️ IMPORTANT FINDINGS

🧠 WHAT THIS MAY MEAN

🩺 POSSIBLE CONDITIONS

📊 CONFIDENCE & LIMITATIONS

🚦 URGENCY

💬 QUESTIONS TO CONSIDER

👨‍⚕️ DOCTOR REVIEW

➡️ RECOMMENDED NEXT STEPS

PROMPT;


        /*
        |--------------------------------------------------------------------------
        | USER PROMPT
        |--------------------------------------------------------------------------
        */

        $userPrompt = <<<'PROMPT'

Analyze the uploaded image carefully.

Give a detailed but readable explanation.

If this is a medical image:

• Identify the image type if reasonably possible.
• Describe the visible anatomy or relevant structures.
• Describe important visible findings.
• Mention asymmetry, alignment, density, opacity, fractures, lesions,
  swelling, fluid or other abnormalities ONLY when genuinely visible.
• Distinguish normal-looking observations from concerning observations.
• Explain observations in simple patient-friendly language.
• Mention possible conditions only as possibilities.
• Explain confidence and limitations.
• Give an urgency level.
• Provide relevant questions that may help a doctor.
• Explain whether professional review is appropriate.
• Provide practical next steps.

If the image is not medical:

• Clearly state that it does not appear to be a medical image.
• Describe what is actually visible.
• Do not invent medical findings.
• Set urgency to Routine / Not applicable.
• Recommend uploading the appropriate medical image if medical analysis is intended.

Do not diagnose with certainty.

PROMPT;


        /*
        |--------------------------------------------------------------------------
        | API PAYLOAD
        |--------------------------------------------------------------------------
        */

        if (
            $error === ''
        ) {

            $payload = [

                'model' =>
                    $model,


                'messages' => [

                    [

                        'role' =>
                            'system',

                        'content' =>
                            $systemPrompt

                    ],


                    [

                        'role' =>
                            'user',

                        'content' => [

                            [

                                'type' =>
                                    'text',

                                'text' =>
                                    $userPrompt

                            ],


                            [

                                'type' =>
                                    'image_url',

                                'image_url' => [

                                    'url' =>
                                        $imageDataUrl

                                ]

                            ]

                        ]

                    ]

                ],


                'temperature' =>
                    0.2,


                'top_p' =>
                    0.7,


                'max_tokens' =>
                    1800,


                'stream' =>
                    false,


                'chat_template_kwargs' => [

                    'enable_thinking' =>
                        false

                ]

            ];


            /*
            |--------------------------------------------------------------------------
            | JSON
            |--------------------------------------------------------------------------
            */

            $jsonPayload =
                json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE
                    |
                    JSON_UNESCAPED_SLASHES
                );


            if (
                $jsonPayload === false
            ) {

                $error =
                    'Unable to prepare the AI request.';

            }

        }


        /*
        |--------------------------------------------------------------------------
        | CURL REQUEST
        |--------------------------------------------------------------------------
        */

        if (
            $error === ''
        ) {

            $startTime =
                microtime(true);


            $curl =
                curl_init(
                    $apiUrl
                );


            curl_setopt_array(

                $curl,

                [

                    CURLOPT_POST =>
                        true,


                    CURLOPT_RETURNTRANSFER =>
                        true,


                    CURLOPT_CONNECTTIMEOUT =>
                        20,


                    CURLOPT_TIMEOUT =>
                        180,


                    CURLOPT_SSL_VERIFYPEER =>
                        true,


                    CURLOPT_SSL_VERIFYHOST =>
                        2,


                    CURLOPT_HTTPHEADER => [

                        'Authorization: Bearer '
                        .
                        $apiKey,

                        'Content-Type: application/json',

                        'Accept: application/json'

                    ],


                    CURLOPT_POSTFIELDS =>
                        $jsonPayload

                ]

            );


            /*
            |--------------------------------------------------------------------------
            | SEND REQUEST
            |--------------------------------------------------------------------------
            |
            | NVIDIA can temporarily reject requests when its worker capacity is full.
            | Retry only capacity-related failures, with a small exponential backoff.
            | Do not retry authentication, validation or other permanent errors.
            */

            $response = false;
            $httpCode = 0;
            $curlError = '';

            $maxAttempts = 3;

            for (
                $attempt = 1;
                $attempt <= $maxAttempts;
                $attempt++
            ) {

                $response =
                    curl_exec(
                        $curl
                    );


                $httpCode =
                    (int)
                    curl_getinfo(
                        $curl,
                        CURLINFO_HTTP_CODE
                    );


                $curlError =
                    curl_error(
                        $curl
                    );


                $retryable = false;

                if (
                    $response !== false
                ) {

                    $responseData =
                        json_decode(
                            (string)$response,
                            true
                        );


                    $apiErrorMessage = '';

                    if (
                        is_array($responseData)
                        &&
                        isset($responseData['error'])
                    ) {

                        if (
                            is_string($responseData['error'])
                        ) {

                            $apiErrorMessage =
                                $responseData['error'];

                        }

                        elseif (
                            is_array($responseData['error'])
                            &&
                            isset($responseData['error']['message'])
                        ) {

                            $apiErrorMessage =
                                (string)
                                $responseData['error']['message'];

                        }

                    }


                    $lowerApiErrorMessage =
                        strtolower(
                            $apiErrorMessage
                        );


                    if (
                        strpos(
                            $lowerApiErrorMessage,
                            'resourceexhausted'
                        ) !== false
                        ||
                        strpos(
                            $lowerApiErrorMessage,
                            'worker local total request limit'
                        ) !== false
                        ||
                        $httpCode === 429
                        ||
                        $httpCode === 503
                        ||
                        $httpCode === 529
                    ) {

                        $retryable = true;

                    }

                }


                if (
                    !$retryable
                    ||
                    $attempt >= $maxAttempts
                ) {

                    break;

                }


                $delaySeconds =
                    2 ** ($attempt - 1);


                usleep(
                    $delaySeconds * 1000000
                );

            }


            curl_close(
                $curl
            );


            $responseTime =
                round(
                    microtime(true)
                    -
                    $startTime,
                    2
                );


            /*
            |--------------------------------------------------------------------------
            | CONNECTION ERROR
            |--------------------------------------------------------------------------
            */

            if (
                $response === false
            ) {

                $error =
                    'Unable to connect to the AI service.'
                    .
                    '<br><br>'
                    .
                    htmlspecialchars(
                        $curlError,
                        ENT_QUOTES,
                        'UTF-8'
                    );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | PROCESS RESPONSE
        |--------------------------------------------------------------------------
        */

        if (
            $error === ''
        ) {

            $data =
                json_decode(
                    (string)$response,
                    true
                );


            /*
            |--------------------------------------------------------------------------
            | INVALID JSON
            |--------------------------------------------------------------------------
            */

            if (
                !is_array($data)
            ) {

                $error =
                    'The AI service returned an invalid response.';

            }


            /*
            |--------------------------------------------------------------------------
            | API ERROR
            |--------------------------------------------------------------------------
            */

            elseif (
                $httpCode < 200
                ||
                $httpCode >= 300
            ) {

                $apiErrorMessage = '';


                if (
                    isset(
                        $data['error']
                    )
                ) {

                    if (
                        is_string(
                            $data['error']
                        )
                    ) {

                        $apiErrorMessage =
                            $data['error'];

                    }

                    elseif (
                        is_array(
                            $data['error']
                        )
                        &&
                        isset(
                            $data['error']['message']
                        )
                    ) {

                        $apiErrorMessage =
                            (string)
                            $data['error']['message'];

                    }

                }


                $lowerApiErrorMessage =
                    strtolower(
                        $apiErrorMessage
                    );


                if (
                    strpos(
                        $lowerApiErrorMessage,
                        'resourceexhausted'
                    ) !== false
                    ||
                    strpos(
                        $lowerApiErrorMessage,
                        'worker local total request limit'
                    ) !== false
                    ||
                    $httpCode === 429
                    ||
                    $httpCode === 503
                    ||
                    $httpCode === 529
                ) {

                    $error =
                        'The AI service is temporarily at capacity.'
                        .
                        '<br><br>'
                        .
                        'Please wait a short time and try again.';

                }

                else {

                    $error =
                        'The AI analysis request failed.';

                }

            }


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            else {

                $result =
                    $data['choices'][0]['message']['content']
                    ??
                    '';


                $result =
                    trim(
                        (string)$result
                    );


                if (
                    $result === ''
                ) {

                    $error =
                        'The AI returned an empty analysis.';

                }

            }

        }

    }

}
}


/*
|--------------------------------------------------------------------------
| ESCAPE FUNCTION
|--------------------------------------------------------------------------
*/

function e(
    string $value
): string {

    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );

}


/*
|--------------------------------------------------------------------------
| PARSE AI SECTIONS
|--------------------------------------------------------------------------
*/

$sections = [

    '🩻 IMAGE OVERVIEW' => [

        'icon' =>
            '🩻',

        'title' =>
            'Image Overview',

        'class' =>
            'overview'

    ],


    '🔎 DETAILED OBSERVATIONS' => [

        'icon' =>
            '🔎',

        'title' =>
            'Detailed Observations',

        'class' =>
            'observations'

    ],


    '⚠️ IMPORTANT FINDINGS' => [

        'icon' =>
            '⚠️',

        'title' =>
            'Important Findings',

        'class' =>
            'findings'

    ],


    '🧠 WHAT THIS MAY MEAN' => [

        'icon' =>
            '🧠',

        'title' =>
            'What This May Mean',

        'class' =>
            'meaning'

    ],


    '🩺 POSSIBLE CONDITIONS' => [

        'icon' =>
            '🩺',

        'title' =>
            'Possible Conditions',

        'class' =>
            'conditions'

    ],


    '📊 CONFIDENCE & LIMITATIONS' => [

        'icon' =>
            '📊',

        'title' =>
            'Confidence & Limitations',

        'class' =>
            'confidence'

    ],


    '🚦 URGENCY' => [

        'icon' =>
            '🚦',

        'title' =>
            'Urgency',

        'class' =>
            'urgency'

    ],


    '💬 QUESTIONS TO CONSIDER' => [

        'icon' =>
            '💬',

        'title' =>
            'Questions to Consider',

        'class' =>
            'questions'

    ],


    '👨‍⚕️ DOCTOR REVIEW' => [

        'icon' =>
            '👨‍⚕️',

        'title' =>
            'Doctor Review',

        'class' =>
            'doctor'

    ],


    '➡️ RECOMMENDED NEXT STEPS' => [

        'icon' =>
            '➡️',

        'title' =>
            'Recommended Next Steps',

        'class' =>
            'steps'

    ]

];


/*
|--------------------------------------------------------------------------
| PARSE RESPONSE
|--------------------------------------------------------------------------
*/

$parsedSections = [];


if (
    $result !== ''
) {

    $currentSection = '';


    $lines =
        preg_split(
            '/\R/',
            $result
        );


    foreach (
        $lines as $line
    ) {

        $cleanLine =
            trim(
                $line
            );


        $matchedHeading =
            null;


        foreach (
            array_keys($sections)
            as $heading
        ) {

            if (
                stripos(
                    $cleanLine,
                    $heading
                ) === 0
            ) {

                $matchedHeading =
                    $heading;

                break;

            }

        }


        if (
            $matchedHeading !== null
        ) {

            $currentSection =
                $matchedHeading;


            $parsedSections[
                $currentSection
            ] = '';


            continue;

        }


        if (
            $currentSection !== ''
        ) {

            $parsedSections[
                $currentSection
            ] .=
                $line
                .
                "\n";

        }

    }

}


/*
|--------------------------------------------------------------------------
| IF MODEL DOES NOT RETURN HEADINGS
|--------------------------------------------------------------------------
*/

if (
    $result !== ''
    &&
    empty($parsedSections)
) {

    $parsedSections[
        '🩻 IMAGE OVERVIEW'
    ] =
        $result;

}


/*
|--------------------------------------------------------------------------
| FORMAT SECTION CONTENT
|--------------------------------------------------------------------------
*/

function formatSectionContent(
    string $text
): string {

    $text =
        trim(
            $text
        );


    if (
        $text === ''
    ) {

        return
            '<p class="empty-text">'
            .
            'No additional information provided.'
            .
            '</p>';

    }


    /*
    |--------------------------------------------------------------------------
    | ESCAPE HTML
    |--------------------------------------------------------------------------
    */

    $text =
        htmlspecialchars(
            $text,
            ENT_QUOTES,
            'UTF-8'
        );


    /*
    |--------------------------------------------------------------------------
    | BOLD
    |--------------------------------------------------------------------------
    */

    $text =
        preg_replace(
            '/\*\*(.*?)\*\*/s',
            '<strong>$1</strong>',
            $text
        );


    /*
    |--------------------------------------------------------------------------
    | INLINE CODE
    |--------------------------------------------------------------------------
    */

    $text =
        preg_replace(
            '/`([^`]+)`/',
            '<code>$1</code>',
            $text
        );


    /*
    |--------------------------------------------------------------------------
    | LINES
    |--------------------------------------------------------------------------
    */

    $lines =
        preg_split(
            '/\R/',
            $text
        );


    $html =
        '';

    $inList =
        false;


    foreach (
        $lines as $line
    ) {

        $line =
            trim(
                $line
            );


        if (
            $line === ''
        ) {

            continue;

        }


        /*
        |--------------------------------------------------------------------------
        | BULLET
        |--------------------------------------------------------------------------
        */

        if (
            preg_match(
                '/^[-•*]\s+(.*)$/',
                $line,
                $matches
            )
        ) {

            if (
                !$inList
            ) {

                $html .=
                    '<ul class="ai-list">';

                $inList =
                    true;

            }


            $html .=
                '<li>'
                .
                $matches[1]
                .
                '</li>';

        }


        /*
        |--------------------------------------------------------------------------
        | NUMBERED LIST
        |--------------------------------------------------------------------------
        */

        elseif (
            preg_match(
                '/^\d+[\.\)]\s+(.*)$/',
                $line,
                $matches
            )
        ) {

            if (
                $inList
            ) {

                $html .=
                    '</ul>';

                $inList =
                    false;

            }


            $html .=
                '<div class="step-line">'
                .
                '<span class="step-number">'
                .
                htmlspecialchars(
                    substr(
                        $line,
                        0,
                        strpos(
                            $line,
                            '.'
                        )
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                )
                .
                '</span>'
                .
                '<span>'
                .
                $matches[1]
                .
                '</span>'
                .
                '</div>';

        }


        /*
        |--------------------------------------------------------------------------
        | NORMAL PARAGRAPH
        |--------------------------------------------------------------------------
        */

        else {

            if (
                $inList
            ) {

                $html .=
                    '</ul>';

                $inList =
                    false;

            }


            $html .=
                '<p>'
                .
                $line
                .
                '</p>';

        }

    }


    if (
        $inList
    ) {

        $html .=
            '</ul>';

    }


    return $html;

}


/*
|--------------------------------------------------------------------------
| URGENCY DETECTION
|--------------------------------------------------------------------------
*/

$urgencyLevel =
    'Routine';

$urgencyClass =
    'routine';

$urgencyIcon =
    '🟢';


if (
    $result !== ''
) {

    $lowerResult =
        strtolower(
            $result
        );


    if (
        strpos(
            $lowerResult,
            'red'
        ) !== false
        ||
        strpos(
            $lowerResult,
            'urgent'
        ) !== false
        ||
        strpos(
            $lowerResult,
            'emergency'
        ) !== false
        ||
        strpos(
            $lowerResult,
            'immediate medical'
        ) !== false
    ) {

        $urgencyLevel =
            'Urgent Medical Review';

        $urgencyClass =
            'urgent';

        $urgencyIcon =
            '🔴';

    }


    elseif (
        strpos(
            $lowerResult,
            'yellow'
        ) !== false
        ||
        strpos(
            $lowerResult,
            'soon'
        ) !== false
        ||
        strpos(
            $lowerResult,
            'prompt'
        ) !== false
        ||
        strpos(
            $lowerResult,
            'doctor review'
        ) !== false
    ) {

        $urgencyLevel =
            'Doctor Review Soon';

        $urgencyClass =
            'soon';

        $urgencyIcon =
            '🟡';

    }

}


/*
|--------------------------------------------------------------------------
| SAFE IMAGE
|--------------------------------------------------------------------------
*/

$safeUploadedImage =
    e(
        $uploadedImage
    );


/*
|--------------------------------------------------------------------------
| SAFE FILE NAME
|--------------------------------------------------------------------------
*/

$safeFileName =
    e(
        $fileName
    );

?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    AI Medical Image Analyzer
</title>


<style>

/*
|--------------------------------------------------------------------------
| GLOBAL
|--------------------------------------------------------------------------
*/

* {

    box-sizing:
        border-box;

}


html {

    scroll-behavior:
        smooth;

}


body {

    margin:
        0;

    padding:
        30px 18px;

    font-family:
        Inter,
        ui-sans-serif,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Arial,
        sans-serif;

    background:
        linear-gradient(
            135deg,
            #f4f7ff,
            #f8fbff
        );

    color:
        #17233b;

}


/*
|--------------------------------------------------------------------------
| MAIN
|--------------------------------------------------------------------------
*/

.container {

    width:
        min(1050px, 100%);

    margin:
        0 auto;

}


.card {

    background:
        rgba(255,255,255,.98);

    border:
        1px solid #e3e8f2;

    border-radius:
        24px;

    padding:
        34px;

    box-shadow:
        0 18px 55px
        rgba(31,45,75,.10);

}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.header {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        16px;

    margin-bottom:
        28px;

}


.header-left {

    display:
        flex;

    align-items:
        center;

    gap:
        16px;

}


.header-icon {

    width:
        58px;

    height:
        58px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        17px;

    background:
        linear-gradient(
            135deg,
            #eef0ff,
            #e8f4ff
        );

    font-size:
        30px;

}


.header h1 {

    margin:
        0;

    font-size:
        31px;

    letter-spacing:
        -.6px;

}


.header p {

    margin:
        6px 0 0;

    color:
        #71809a;

    font-size:
        15px;

}


.patient-badge {

    display:
        flex;

    align-items:
        center;

    gap:
        7px;

    padding:
        9px 13px;

    border-radius:
        30px;

    background:
        #f2f4ff;

    color:
        #4f46d8;

    font-size:
        12px;

    font-weight:
        700;

}


/*
|--------------------------------------------------------------------------
| UPLOAD
|--------------------------------------------------------------------------
*/

.upload-card {

    padding:
        26px;

    border:
        2px dashed #ccd6ea;

    border-radius:
        18px;

    background:
        linear-gradient(
            135deg,
            #fafcff,
            #f8f9ff
        );

    text-align:
        center;

    transition:
        .2s ease;

}


.upload-card.dragover {

    border-color:
        #5b50f5;

    background:
        #f1f0ff;

    transform:
        scale(1.01);

}


.upload-icon {

    font-size:
        42px;

    margin-bottom:
        8px;

}


.upload-card h2 {

    margin:
        0 0 7px;

    font-size:
        20px;

}


.upload-card p {

    color:
        #758198;

    margin:
        0 0 18px;

}


.file-input {

    width:
        100%;

    max-width:
        600px;

    padding:
        12px;

    border:
        1px solid #d9e0ec;

    border-radius:
        10px;

    background:
        #ffffff;

}


.file-info {

    display:
        none;

    margin:
        12px auto 0;

    max-width:
        600px;

    padding:
        10px 13px;

    border-radius:
        10px;

    background:
        #eef5ff;

    color:
        #365078;

    font-size:
        12px;

    text-align:
        left;

}


.analyze-button {

    margin-top:
        15px;

    padding:
        13px 24px;

    border:
        0;

    border-radius:
        11px;

    background:
        linear-gradient(
            135deg,
            #5b50f5,
            #635bff
        );

    color:
        #ffffff;

    font-size:
        15px;

    font-weight:
        700;

    cursor:
        pointer;

    box-shadow:
        0 8px 20px
        rgba(91,80,245,.20);

}


.analyze-button:hover {

    transform:
        translateY(-1px);

}


.reset-button {

    margin-left:
        8px;

    padding:
        12px 18px;

    border:
        1px solid #dce2ed;

    border-radius:
        11px;

    background:
        #ffffff;

    color:
        #59657a;

    cursor:
        pointer;

}


/*
|--------------------------------------------------------------------------
| IMAGE PREVIEW
|--------------------------------------------------------------------------
*/

.image-preview-card {

    margin-top:
        24px;

    padding:
        22px;

    background:
        #ffffff;

    border:
        1px solid #e0e6f0;

    border-radius:
        18px;

}


.image-preview-title {

    display:
        flex;

    align-items:
        center;

    gap:
        9px;

    font-weight:
        700;

    font-size:
        17px;

    margin-bottom:
        15px;

}


.image-preview {

    width:
        100%;

    max-height:
        560px;

    object-fit:
        contain;

    display:
        block;

    margin:
        auto;

    border-radius:
        14px;

    background:
        #f5f7fb;

}


/*
|--------------------------------------------------------------------------
| SUCCESS STATUS
|--------------------------------------------------------------------------
*/

.status-row {

    display:
        flex;

    flex-wrap:
        wrap;

    align-items:
        center;

    gap:
        10px;

    margin-top:
        22px;

}


.status {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        7px;

    padding:
        9px 15px;

    border-radius:
        30px;

    font-size:
        13px;

    font-weight:
        700;

}


.status-success {

    background:
        #eafaf2;

    color:
        #087443;

}


.analysis-time {

    color:
        #7a8597;

    font-size:
        12px;

}


/*
|--------------------------------------------------------------------------
| ANALYSIS HEADER
|--------------------------------------------------------------------------
*/

.analysis-header {

    margin:
        30px 0 18px;

    display:
        flex;

    align-items:
        flex-end;

    justify-content:
        space-between;

    gap:
        15px;

}


.analysis-header h2 {

    margin:
        0;

    font-size:
        24px;

}


.analysis-header p {

    margin:
        5px 0 0;

    color:
        #71809a;

}


.copy-all-button {

    padding:
        10px 14px;

    border:
        1px solid #dce2ed;

    border-radius:
        10px;

    background:
        #ffffff;

    color:
        #566176;

    cursor:
        pointer;

    white-space:
        nowrap;

}


.copy-all-button:hover {

    background:
        #f7f8fc;

}


/*
|--------------------------------------------------------------------------
| SECTION GRID
|--------------------------------------------------------------------------
*/

.sections {

    display:
        grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap:
        18px;

}


/*
|--------------------------------------------------------------------------
| SECTION
|--------------------------------------------------------------------------
*/

.section {

    background:
        #ffffff;

    border:
        1px solid #e0e6f0;

    border-radius:
        17px;

    overflow:
        hidden;

    box-shadow:
        0 5px 18px
        rgba(31,45,75,.045);

    transition:
        transform .2s ease,
        box-shadow .2s ease;

}


.section:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 10px 28px
        rgba(31,45,75,.08);

}


.section.full {

    grid-column:
        1 / -1;

}


.section-header {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

    padding:
        15px 18px;

    border-bottom:
        1px solid #e8ecf3;

}


.section-icon {

    width:
        36px;

    height:
        36px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        10px;

    background:
        #f0f2ff;

    font-size:
        18px;

}


.section-header h3 {

    margin:
        0;

    font-size:
        16px;

}


.section-body {

    padding:
        18px;

    color:
        #46536a;

    line-height:
        1.75;

    font-size:
        14px;

}


.section-body p {

    margin:
        0 0 11px;

}


.section-body p:last-child {

    margin-bottom:
        0;

}


.section-body strong {

    color:
        #1c2941;

}


.section-body code {

    padding:
        2px 5px;

    border-radius:
        5px;

    background:
        #f0f2f7;

    font-size:
        12px;

}


.ai-list {

    margin:
        0;

    padding-left:
        21px;

}


.ai-list li {

    margin-bottom:
        8px;

}


.ai-list li:last-child {

    margin-bottom:
        0;

}


.step-line {

    display:
        flex;

    gap:
        11px;

    align-items:
        flex-start;

    margin-bottom:
        12px;

    padding:
        10px 12px;

    border-radius:
        10px;

    background:
        #f8f9fd;

}


.step-number {

    min-width:
        26px;

    height:
        26px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        50%;

    background:
        #5b50f5;

    color:
        #ffffff;

    font-size:
        11px;

    font-weight:
        700;

}


.empty-text {

    color:
        #8b96a8;

    font-style:
        italic;

}


/*
|--------------------------------------------------------------------------
| SPECIAL SECTION ICONS
|--------------------------------------------------------------------------
*/

.section.findings .section-icon {

    background:
        #fff3e6;

}


.section.urgency .section-icon {

    background:
        #eefaf2;

}


.section.conditions .section-icon {

    background:
        #f2efff;

}


.section.doctor .section-icon {

    background:
        #eaf5ff;

}


.section.steps .section-icon {

    background:
        #edf8f5;

}


.section.confidence .section-icon {

    background:
        #fff8e8;

}


/*
|--------------------------------------------------------------------------
| URGENCY
|--------------------------------------------------------------------------
*/

.urgency-box {

    margin-top:
        20px;

    padding:
        20px;

    border-radius:
        17px;

    display:
        flex;

    align-items:
        center;

    gap:
        15px;

}


.urgency-box.routine {

    background:
        #ecfdf3;

    border:
        1px solid #b7ebc9;

}


.urgency-box.soon {

    background:
        #fff8e6;

    border:
        1px solid #f2d58e;

}


.urgency-box.urgent {

    background:
        #fff1f1;

    border:
        1px solid #ffc5c5;

}


.urgency-icon {

    font-size:
        30px;

}


.urgency-title {

    font-size:
        17px;

    font-weight:
        800;

}


.urgency-description {

    margin-top:
        4px;

    font-size:
        13px;

    color:
        #667085;

}


/*
|--------------------------------------------------------------------------
| SAFETY
|--------------------------------------------------------------------------
*/

.safety {

    margin-top:
        25px;

    padding:
        18px;

    border:
        1px solid #f0d38b;

    background:
        #fff9e9;

    border-radius:
        15px;

    color:
        #6d5200;

    line-height:
        1.65;

    font-size:
        13px;

}


.safety-title {

    font-weight:
        800;

    margin-bottom:
        5px;

}


/*
|--------------------------------------------------------------------------
| ERROR
|--------------------------------------------------------------------------
*/

.error {

    margin-top:
        22px;

    padding:
        19px;

    background:
        #fff1f1;

    border:
        1px solid #ffcaca;

    border-radius:
        14px;

    color:
        #b42318;

    line-height:
        1.7;

}


/*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
*/

.footer {

    text-align:
        center;

    margin-top:
        25px;

    color:
        #8b95a7;

    font-size:
        12px;

}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (
    max-width: 760px
) {

    body {

        padding:
            10px;

    }


    .card {

        padding:
            15px;

        border-radius:
            18px;

    }


    .header {

        align-items:
            flex-start;

        flex-direction:
            column;

    }


    .header-left {

        width:
            100%;

    }


    .header h1 {

        font-size:
            23px;

    }


    .header p {

        font-size:
            13px;

    }


    .patient-badge {

        width:
            fit-content;

    }


    .upload-card {

        padding:
            20px 14px;

    }


    .sections {

        grid-template-columns:
            1fr;

    }


    .section.full {

        grid-column:
            auto;

    }


    .analysis-header {

        align-items:
            flex-start;

        flex-direction:
            column;

    }


    .analysis-header h2 {

        font-size:
            21px;

    }


    .copy-all-button {

        width:
            100%;

    }


    .image-preview {

        max-height:
            420px;

    }


    .analyze-button,
    .reset-button {

        width:
            100%;

        margin:
            8px 0 0;

    }


    .urgency-box {

        align-items:
            flex-start;

    }


}


/*
|--------------------------------------------------------------------------
| VERY SMALL MOBILE
|--------------------------------------------------------------------------
*/

@media (
    max-width: 420px
) {

    .header-icon {

        width:
            48px;

        height:
            48px;

        font-size:
            25px;

    }


    .upload-icon {

        font-size:
            35px;

    }


    .section-body {

        padding:
            15px;

        font-size:
            13px;

    }


    .section-header {

        padding:
            13px 15px;

    }


    .patient-badge {

        font-size:
            11px;

    }

}

</style>

</head>


<body>


<div class="container">


<div class="card">


<!--
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
-->

<div class="header">


    <div class="header-left">


        <div class="header-icon">

            🩻

        </div>


        <div>

            <h1>
                AI Medical Image Analyzer
            </h1>

            <p>
                Understand your medical image in simple language
            </p>

        </div>


    </div>


    <div class="patient-badge">

        👤

        <?= $safePatientName; ?>

    </div>


</div>


<!--
|--------------------------------------------------------------------------
| UPLOAD
|--------------------------------------------------------------------------
-->

<div
    class="upload-card"
    id="uploadCard"
>


    <div class="upload-icon">

        📤

    </div>


    <h2>

        Upload Medical Image

    </h2>


    <p>

        Upload an X-ray, scan or other supported medical image.

    </p>


    <form
        method="POST"
        enctype="multipart/form-data"
        id="uploadForm"
    >


        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($csrfToken); ?>"
        >


        <input
            class="file-input"
            type="file"
            name="medical_image"
            id="medicalImage"
            accept="image/jpeg,image/png,image/webp"
            required
        >


        <div
            class="file-info"
            id="fileInfo"
        ></div>


        <br>


        <button
            class="analyze-button"
            type="submit"
            id="analyzeButton"
        >

            🔍 Analyze Image

        </button>


        <button
            class="reset-button"
            type="button"
            id="resetButton"
        >

            🔄 Reset

        </button>


    </form>


</div>


<?php if (
    $uploadedImage !== ''
): ?>


<!--
|--------------------------------------------------------------------------
| UPLOADED IMAGE
|--------------------------------------------------------------------------
-->

<div class="image-preview-card">


    <div class="image-preview-title">

        🖼️

        <span>

            Uploaded Image

        </span>

    </div>


    <img
        class="image-preview"
        src="<?= $safeUploadedImage; ?>"
        alt="Uploaded medical image"
    >


    <div class="file-info"
         style="display:block;margin-top:12px;">

        📄

        <strong>
            File:
        </strong>

        <?= $safeFileName; ?>

        <?php if (
            $imageWidth > 0
            &&
            $imageHeight > 0
        ): ?>

            &nbsp; • &nbsp;

            📐

            <?= $imageWidth; ?>
            ×
            <?= $imageHeight; ?>
            px

        <?php endif; ?>

    </div>


</div>


<?php endif; ?>


<?php if (
    $error === ''
    &&
    $result !== ''
): ?>


<!--
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
-->

<div class="status-row">


    <div class="status status-success">

        ✅

        <span>

            Analysis Completed

        </span>

    </div>


    <?php if (
        $responseTime > 0
    ): ?>

        <div class="analysis-time">

            ⚡ Completed in
            <?= e((string)$responseTime); ?>
            seconds

        </div>

    <?php endif; ?>


</div>


<!--
|--------------------------------------------------------------------------
| ANALYSIS HEADER
|--------------------------------------------------------------------------
-->

<div class="analysis-header">


    <div>

        <h2>

            🤖 Analysis Results

        </h2>


        <p>

            A clear explanation of the information observed in your image.

        </p>

    </div>


    <button
        type="button"
        class="copy-all-button"
        id="copyAllButton"
    >

        📋 Copy Analysis

    </button>


</div>


<!--
|--------------------------------------------------------------------------
| SECTION RESULTS
|--------------------------------------------------------------------------
-->

<div
    class="sections"
    id="analysisSections"
>


<?php

$sectionCount = 0;

foreach (
    $sections as $heading => $info
):

    if (
        !isset(
            $parsedSections[$heading]
        )
    ) {

        continue;

    }


    $sectionCount++;


    $content =
        $parsedSections[$heading];

?>


<div
    class="section <?= e($info['class']); ?>
    <?=
        in_array(
            $info['class'],
            [
                'observations',
                'meaning',
                'steps'
            ],
            true
        )
        ? 'full'
        : ''
    ?>"
>


    <div class="section-header">


        <div class="section-icon">

            <?= e($info['icon']); ?>

        </div>


        <h3>

            <?= e($info['title']); ?>

        </h3>


    </div>


    <div class="section-body">

        <?= formatSectionContent($content); ?>

    </div>


</div>


<?php endforeach; ?>


</div>


<!--
|--------------------------------------------------------------------------
| URGENCY
|--------------------------------------------------------------------------
-->

<div
    class="urgency-box <?= e($urgencyClass); ?>"
>


    <div class="urgency-icon">

        <?= e($urgencyIcon); ?>

    </div>


    <div>


        <div class="urgency-title">

            <?= e($urgencyLevel); ?>

        </div>


        <div class="urgency-description">

            This level is an AI-generated guidance indicator,
            not a confirmed clinical decision.

        </div>


    </div>


</div>


<?php endif; ?>


<?php if (
    $error !== ''
): ?>


<!--
|--------------------------------------------------------------------------
| ERROR
|--------------------------------------------------------------------------
-->

<div class="error">

    ❌

    <?= $error; ?>

</div>


<?php endif; ?>


<?php if (
    $result !== ''
    &&
    $error === ''
): ?>


<!--
|--------------------------------------------------------------------------
| SAFETY
|--------------------------------------------------------------------------
-->

<div class="safety">


    <div class="safety-title">

        ⚠️ Medical Safety Notice

    </div>


    This analysis is generated by an AI system and is intended
    for informational and decision-support purposes only.
    It is not a confirmed diagnosis and does not replace
    examination or interpretation by a qualified healthcare
    professional.


</div>


<?php endif; ?>


<!--
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
-->

<div class="footer">

    🔒 Secure Smart Hospital AI Medical Analysis

    <br>

    Patient authentication is required to use this service.

</div>


</div>


</div>


<script>

/*
|--------------------------------------------------------------------------
| ELEMENTS
|--------------------------------------------------------------------------
*/

const uploadForm =
    document.getElementById(
        'uploadForm'
    );


const medicalImage =
    document.getElementById(
        'medicalImage'
    );


const uploadCard =
    document.getElementById(
        'uploadCard'
    );


const fileInfo =
    document.getElementById(
        'fileInfo'
    );


const analyzeButton =
    document.getElementById(
        'analyzeButton'
    );


const resetButton =
    document.getElementById(
        'resetButton'
    );


const copyAllButton =
    document.getElementById(
        'copyAllButton'
    );


/*
|--------------------------------------------------------------------------
| FILE PREVIEW INFORMATION
|--------------------------------------------------------------------------
*/

if (
    medicalImage
) {

    medicalImage.addEventListener(
        'change',
        function() {

            if (
                !this.files
                ||
                !this.files.length
            ) {

                fileInfo.style.display =
                    'none';

                fileInfo.textContent =
                    '';

                return;

            }


            const file =
                this.files[0];


            const sizeMB =
                (
                    file.size
                    /
                    1024
                    /
                    1024
                ).toFixed(2);


            fileInfo.style.display =
                'block';


            fileInfo.innerHTML =
                '📄 <strong>'
                +
                file.name
                +
                '</strong> &nbsp; • &nbsp; '
                +
                sizeMB
                +
                ' MB';


        }
    );

}


/*
|--------------------------------------------------------------------------
| DRAG & DROP
|--------------------------------------------------------------------------
*/

if (
    uploadCard
) {

    uploadCard.addEventListener(
        'dragover',
        function(event) {

            event.preventDefault();

            uploadCard.classList.add(
                'dragover'
            );

        }
    );


    uploadCard.addEventListener(
        'dragleave',
        function() {

            uploadCard.classList.remove(
                'dragover'
            );

        }
    );


    uploadCard.addEventListener(
        'drop',
        function(event) {

            event.preventDefault();


            uploadCard.classList.remove(
                'dragover'
            );


            if (
                event.dataTransfer.files.length
            ) {

                medicalImage.files =
                    event.dataTransfer.files;


                medicalImage.dispatchEvent(
                    new Event(
                        'change'
                    )
                );

            }

        }
    );

}


/*
|--------------------------------------------------------------------------
| FORM SUBMIT
|--------------------------------------------------------------------------
*/

if (
    uploadForm
) {

    uploadForm.addEventListener(
        'submit',
        function() {

            analyzeButton.disabled =
                true;


            analyzeButton.innerHTML =
                '⏳ Analyzing Image...';


            resetButton.disabled =
                true;

        }
    );

}


/*
|--------------------------------------------------------------------------
| RESET
|--------------------------------------------------------------------------
*/

if (
    resetButton
) {

    resetButton.addEventListener(
        'click',
        function() {

            medicalImage.value =
                '';

            fileInfo.style.display =
                'none';

            fileInfo.textContent =
                '';

        }
    );

}


/*
|--------------------------------------------------------------------------
| COPY ALL ANALYSIS
|--------------------------------------------------------------------------
*/

if (
    copyAllButton
) {

    copyAllButton.addEventListener(
        'click',
        async function() {

            const sections =
                document.querySelector(
                    '#analysisSections'
                );


            if (
                !sections
            ) {

                return;

            }


            const text =
                sections.innerText.trim()
                +
                '\n\n'
                +
                'Urgency: '
                +
                '<?= e($urgencyLevel); ?>';


            try {

                await navigator.clipboard.writeText(
                    text
                );


                copyAllButton.innerHTML =
                    '✓ Copied';


                setTimeout(
                    function() {

                        copyAllButton.innerHTML =
                            '📋 Copy Analysis';

                    },
                    1600
                );

            }
            catch (
                error
            ) {

                copyAllButton.innerHTML =
                    'Copy failed';

            }

        }
    );

}

</script>


</body>

</html>