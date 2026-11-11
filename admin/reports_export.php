<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/hospital_context.php';

/*
 * Reports export endpoint.
 * - PDF: generates a small, dependency-free PDF directly in PHP.
 * - Excel: generates Excel-compatible SpreadsheetML (.xls).
 * - CSV: generates UTF-8 CSV for an individual report.
 */

function export_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function export_query(mysqli $db, string $sql): array
{
    $result = mysqli_query($db, $sql);
    if (!$result) {
        throw new RuntimeException(mysqli_error($db));
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_free_result($result);
    return $rows;
}

function csv_download(string $filename, array $headers, array $rows): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function excel_download(string $filename, array $headers, array $rows): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
    echo '<Styles><Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#E9E7FF" ss:Pattern="Solid"/></Style></Styles>';
    echo '<Worksheet ss:Name="Report"><Table>';
    echo '<Row ss:StyleID="Header">';
    foreach ($headers as $header) {
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$header, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
    }
    echo '</Row>';
    foreach ($rows as $row) {
        echo '<Row>';
        foreach ($row as $value) {
            $value = (string)$value;
            $numeric = is_numeric($value) && !preg_match('/^0\d+/', $value);
            $type = $numeric ? 'Number' : 'String';
            echo '<Cell><Data ss:Type="' . $type . '">' . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
        }
        echo '</Row>';
    }
    echo '</Table></Worksheet></Workbook>';
    exit;
}

function pdf_escape(string $text): string
{
    $text = str_replace(["\\", "(", ")"], ["\\\\", "\\(", "\\)"], $text);
    $text = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
    return $text;
}

function pdf_build(array $lines, string $title): string
{
    $pageWidth = 595;
    $pageHeight = 842;
    $margin = 42;
    $fontSize = 10;
    $leading = 15;

    $wrapped = [];
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '') {
            $wrapped[] = '';
            continue;
        }
        $parts = str_split($line, 92);
        foreach ($parts as $part) {
            $wrapped[] = $part;
        }
    }

    $pages = array_chunk($wrapped, 48);
    if (!$pages) {
        $pages = [[]];
    }

    $objects = [];
    $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';

    $pageObjectNumbers = [];
    $nextObject = 3;
    foreach ($pages as $pageIndex => $_page) {
        $pageObjectNumbers[] = $nextObject;
        $nextObject += 2;
    }

    $kids = implode(' ', array_map(fn($n) => $n . ' 0 R', $pageObjectNumbers));
    $objects[] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($pageObjectNumbers) . ' >>';

    foreach ($pages as $pageIndex => $pageLines) {
        $pageNo = $pageObjectNumbers[$pageIndex];
        $contentNo = $pageNo + 1;

        $content = "BT\n/F1 18 Tf\n" . $margin . " " . ($pageHeight - 55) . " Td\n(" . pdf_escape($title) . ") Tj\n";
        $content .= "/F1 9 Tf\n0 -22 Td\n(Generated: " . pdf_escape(date('d M Y H:i')) . ") Tj\n";
        $content .= "/F1 " . $fontSize . " Tf\n0 -24 Td\n";

        foreach ($pageLines as $line) {
            if ($line === '') {
                $content .= "0 -{$leading} Td\n";
            } else {
                $content .= '(' . pdf_escape($line) . ") Tj\n0 -{$leading} Td\n";
            }
        }
        $content .= "ET";

        $objects[$pageNo - 1] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $pageWidth . ' ' . $pageHeight . '] /Resources << /Font << /F1 ' . ($nextObject + 1) . ' 0 R >> >> /Contents ' . $contentNo . ' 0 R >>';
        $objects[$contentNo - 1] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
    }

    $fontNo = $nextObject + 1;
    $objects[$fontNo - 1] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

    // Re-number array indexes into PDF object numbers.
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    $maxObject = count($objects);
    for ($i = 1; $i <= $maxObject; $i++) {
        $offsets[$i] = strlen($pdf);
        $pdf .= $i . " 0 obj\n" . ($objects[$i - 1] ?? '') . "\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . ($maxObject + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $maxObject; $i++) {
        $pdf .= sprintf('%010d 00000 n \n', $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    return $pdf;
}

try {
    $format = strtolower(trim((string)($_GET['format'] ?? 'csv')));
    $report = trim((string)($_GET['report'] ?? 'All Reports'));

    $patients = export_query($conn, "SELECT phm.hospital_patient_code, pa.first_name, pa.last_name, pa.mobile, pa.email, phm.patient_status, phm.created_at FROM patient_hospital_mapping phm INNER JOIN patient_accounts pa ON pa.account_id=phm.account_id WHERE phm.hospital_id=" . (int)$hospital_id . " ORDER BY phm.created_at DESC");

    $appointments = export_query($hospital_conn, "SELECT a.appointment_no, phm.hospital_patient_code, CONCAT(COALESCE(pa.first_name,''),' ',COALESCE(pa.last_name,'')) patient_name, d.doctor_name, s.service_name, a.appointment_date, a.appointment_time, a.created_at booked_at, a.appointment_type, a.consultation_mode, a.appointment_status FROM appointments a LEFT JOIN doctors d ON d.doctor_id=a.doctor_id LEFT JOIN services s ON s.service_id=a.service_id LEFT JOIN `hospital_management`.patient_hospital_mapping phm ON phm.mapping_id=a.mapping_id LEFT JOIN `hospital_management`.patient_accounts pa ON pa.account_id=phm.account_id WHERE phm.hospital_id=" . (int)$hospital_id . " ORDER BY a.appointment_date ASC, a.appointment_time ASC, a.created_at ASC");

    $doctors = export_query($hospital_conn, "SELECT d.doctor_id,d.doctor_name,COALESCE(dep.department_name,'') department_name,COALESCE(d.specialization,'') specialization,COALESCE(d.qualification,'') qualification,COALESCE(d.experience_years,0) experience_years,COALESCE(d.consultation_fee,0) consultation_fee,d.status FROM doctors d LEFT JOIN departments dep ON dep.department_id=d.department_id ORDER BY d.doctor_name");

    $payments = export_query($hospital_conn, "SELECT p.payment_id,p.bill_id,p.amount,p.payment_method,p.payment_status,p.paid_at FROM payments p ORDER BY p.paid_at DESC,p.payment_id DESC");

    $headers = [];
    $rows = [];
    $safeReport = preg_replace('/[^A-Za-z0-9_-]+/', '_', $report) ?: 'hospital_report';

    if ($report === 'Patients Report') {
        $headers = ['Patient Code','First Name','Last Name','Mobile','Email','Status','Registered At'];
        $rows = array_map(fn($r) => [$r['hospital_patient_code'],$r['first_name'],$r['last_name'],$r['mobile'],$r['email'],$r['patient_status'],$r['created_at']], $patients);
    } elseif ($report === 'Appointments Report') {
        $headers = ['Appointment No','Patient Code','Patient Name','Doctor','Service','Appointment Date','Appointment Time','Booked At','Type','Consultation Mode','Status'];
        $rows = array_map(fn($r) => [$r['appointment_no'],$r['hospital_patient_code'],$r['patient_name'],$r['doctor_name'],$r['service_name'],$r['appointment_date'],$r['appointment_time'],$r['booked_at'],$r['appointment_type'],$r['consultation_mode'],$r['appointment_status']], $appointments);
    } elseif ($report === 'Doctors Report') {
        $headers = ['Doctor ID','Doctor Name','Department','Specialization','Qualification','Experience Years','Consultation Fee','Status'];
        $rows = array_map(fn($r) => [$r['doctor_id'],$r['doctor_name'],$r['department_name'],$r['specialization'],$r['qualification'],$r['experience_years'],$r['consultation_fee'],$r['status']], $doctors);
    } elseif ($report === 'Revenue Report') {
        $headers = ['Payment ID','Bill ID','Amount','Payment Method','Payment Status','Paid At'];
        $rows = array_map(fn($r) => [$r['payment_id'],$r['bill_id'],$r['amount'],$r['payment_method'],$r['payment_status'],$r['paid_at']], $payments);
    } else {
        $headers = ['Report','Record Count'];
        $rows = [
            ['Patients Report', count($patients)],
            ['Appointments Report', count($appointments)],
            ['Doctors Report', count($doctors)],
            ['Revenue Payments', count($payments)],
        ];
    }

    if ($format === 'csv') {
        csv_download($safeReport . '.csv', $headers, $rows);
    }

    if ($format === 'excel' || $format === 'xls') {
        excel_download($safeReport . '.xls', $headers, $rows);
    }

    if ($format === 'pdf') {
        $totalRevenue = 0.0;
        foreach ($payments as $payment) {
            if (strcasecmp((string)$payment['payment_status'], 'Success') === 0) {
                $totalRevenue += (float)$payment['amount'];
            }
        }

        $lines = [
            'Hospital Management System',
            'Report: ' . $report,
            '',
            'Patients: ' . count($patients),
            'Appointments: ' . count($appointments),
            'Doctors: ' . count($doctors),
            'Successful Payments: ' . count(array_filter($payments, fn($p) => strcasecmp((string)$p['payment_status'], 'Success') === 0)),
            'Successful Payment Amount: INR ' . number_format($totalRevenue, 2),
            '',
            implode(' | ', $headers),
        ];

        foreach (array_slice($rows, 0, 80) as $row) {
            $lines[] = implode(' | ', array_map(static fn($v) => (string)$v, $row));
        }

        $pdf = pdf_build($lines, 'Hospital Report - ' . $report);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $safeReport . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $pdf;
        exit;
    }

    http_response_code(400);
    echo 'Invalid export format.';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Report export failed: ' . $e->getMessage();
}
