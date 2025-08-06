<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/fpdf/fpdf.php';

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(40,10,'FPDF OK');
$pdf->Output('I', 'fpdf-test.pdf');
