<?php
function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function post($key, $default=''){ return isset($_POST[$key]) ? trim($_POST[$key]) : $default; }
function safe_filename($name){ return preg_replace('/[^a-zA-Z0-9._-]/','_', $name); }

function save_base64_image($dataUrl, $destPath){
    if(strpos($dataUrl, 'data:image') !== 0){ return false; }
    $parts = explode(',', $dataUrl, 2);
    if(count($parts) !== 2) return false;
    $bin = base64_decode($parts[1]);
    if(!is_dir(dirname($destPath))) mkdir(dirname($destPath), 0775, true);
    return file_put_contents($destPath, $bin) !== false;
}

/** Email with PDF attachment using PHP mail() */
function send_mail_with_attachment($toEmails, $subject, $bodyHtml, $attachPath){
    $from = 'no-reply@docs.defecttracker.uk';
    $boundary = "==Multipart_Boundary_x" . md5(time()) . "x";
    $headers = [
        "From: Defect Tracker <{$from}>",
        "MIME-Version: 1.0",
        "Content-Type: multipart/mixed; boundary=\"{$boundary}\""
    ];

    $message  = "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $bodyHtml . "\r\n\r\n";

    if($attachPath && file_exists($attachPath)){
        $filename = basename($attachPath);
        $filedata = chunk_split(base64_encode(file_get_contents($attachPath)));
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: application/pdf; name=\"{$filename}\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
        $message .= $filedata . "\r\n\r\n";
    }

    $message .= "--{$boundary}--";
    $okAll = true;
    foreach($toEmails as $to){
        $ok = @mail($to, $subject, $message, implode("\r\n", $headers));
        if(!$ok){ $okAll = false; }
    }
    return $okAll;
}

/** PDF generator using FPDF */
function make_pdf(array $data, string $outputPath){
    $fpdfPath = __DIR__ . '/../lib/fpdf/fpdf.php';
    if(!file_exists($fpdfPath)){ error_log("FPDF not found at {$fpdfPath}"); return false; }
    require_once $fpdfPath;

    $pdf = new \FPDF();
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 15);

    // Header
    if(!empty($data['logo_path']) && file_exists($data['logo_path'])){
        $pdf->Image($data['logo_path'], 160, 10, 30);
    }
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(0,10,'Clean-Up Notice #'.(int)$data['id'],0,1);
    $pdf->SetFont('Arial','',10);
    $pdf->Cell(0,6,'Generated on '.date('Y-m-d H:i'),0,1);
    $pdf->Ln(2);

    // Details grid
    $left = [
        ['Site Name', $data['site_name'] ?? ''],
        ['Location', $data['location'] ?? ''],
        ['Issued To', $data['issued_to'] ?? ''],
    ];
    $right = [
        ['Contract No', $data['contract_no'] ?? ''],
        ['Issued At', $data['issued_at'] ?? ''],
        ['Issued By', $data['issued_by'] ?? ''],
    ];
    $yStart = $pdf->GetY();
    $xLeft = 10; $xRight = 110; $w = 90; $h = 6;

    // Left column
    $pdf->SetXY($xLeft, $yStart);
    foreach($left as $row){
        $pdf->SetFont('Arial','B',11); $pdf->Cell(30,$h,$row[0].':',0,0);
        $pdf->SetFont('Arial','',11); $pdf->MultiCell($w-30,$h,$row[1],0,1);
    }

    // Right column
    $pdf->SetXY($xRight, $yStart);
    foreach($right as $row){
        $pdf->SetFont('Arial','B',11); $pdf->Cell(30,$h,$row[0].':',0,0);
        $pdf->SetFont('Arial','',11); $pdf->MultiCell($w-30,$h,$row[1],0,1);
    }
    $pdf->Ln(2);

    // Reason & details
    $pdf->SetFont('Arial','B',12); $pdf->Cell(0,7,'Reason for Notification',0,1);
    $pdf->SetFont('Arial','',11); $pdf->MultiCell(0,6, $data['reason'] ?? ''); $pdf->Ln(1);

    $pdf->SetFont('Arial','B',12); $pdf->Cell(0,7,'Notice Details',0,1);
    $pdf->SetFont('Arial','B',11); $pdf->Cell(30,6,'Description:',0,0);
    $pdf->SetFont('Arial','',11); $pdf->MultiCell(0,6, $data['description'] ?? ''); $pdf->Ln(1);

    // Urgency
    $urg = $data['urgency'] ?? ''; if(!empty($data['deadline_at'])) $urg .= ' — Deadline: '.$data['deadline_at'];
    $pdf->SetFont('Arial','B',11); $pdf->Cell(30,6,'Urgency:',0,0);
    $pdf->SetFont('Arial','',11); $pdf->MultiCell(0,6, $urg); $pdf->Ln(1);

    // Outcome
    $out = [];
    if(!empty($data['completed_ok'])) $out[] = 'Completed OK: '.$data['completed_ok'];
    if(!empty($data['mcgoff_clear'])) $out[] = 'Main contractor to arrange clearance: '.$data['mcgoff_clear'];
    if($out){
        $pdf->SetFont('Arial','B',11); $pdf->Cell(30,6,'Outcome:',0,0);
        $pdf->SetFont('Arial','',11); $pdf->MultiCell(0,6, implode(' | ', $out)); $pdf->Ln(1);
    }

    // Photos
    $pdf->SetFont('Arial','B',12); $pdf->Cell(0,7,'Photos',0,1);
    $pdf->SetFont('Arial','',11);
    $x = 10; $y = $pdf->GetY(); $thumbW = 60; $gap = 5; $perRow = 3; $i = 0;
    if(!empty($data['photo_paths'])){
        foreach($data['photo_paths'] as $p){
            if(!file_exists($p)) continue;
            $pdf->Image($p, $x, $y, $thumbW, 0);
            $x += $thumbW + $gap; $i++;
            if($i % $perRow === 0){ $x = 10; $y += 50; }
            if($y > 250){ $pdf->AddPage(); $y = 20; }
            $pdf->SetY($y);
        }
        $pdf->Ln(($i ? 50 : 6));
    } else {
        $pdf->MultiCell(0,6,'No photos provided.');
    }

    // Signature
    $pdf->SetFont('Arial','B',12); $pdf->Cell(0,7,'Signature',0,1);
    if(!empty($data['signature_path']) && file_exists($data['signature_path'])){
        $pdf->Image($data['signature_path'], 10, $pdf->GetY(), 60, 0);
        $pdf->Ln(30);
    } else {
        $pdf->SetFont('Arial','',11); $pdf->MultiCell(0,6,'No signature captured.');
    }

    // Footer
    $pdf->SetY(-20); $pdf->SetFont('Arial','',9);
    $pdf->Cell(0,10,'This document was generated by Defect Tracker.',0,0,'C');

    // Save
    $dir = dirname($outputPath);
    if(!is_dir($dir)) mkdir($dir, 0775, true);
    return $pdf->Output('F', $outputPath) === '';
}

