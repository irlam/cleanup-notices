<?php
// /forms/clean-up/pdf.php
// Render Clean-Up Notice as PDF (inline by default; add ?dl=1 to force download).
// Safe image handling: EXIF rotate, flatten transparency to white, downscale & cache.

declare(strict_types=1);
date_default_timezone_set('Europe/London');

// ---- Debug toggles via query string ----
$DEBUG       = isset($_GET['debug']) && $_GET['debug'] === '1';
$SKIP_IMAGES = isset($_GET['noimg']) && $_GET['noimg'] === '1';
if ($DEBUG) {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Use the path you confirmed:
require_once __DIR__ . '/../../lib/fpdf/fpdf.php';

// Accept from submit.php capture mode or GET ?id=
$noticeId = isset($NOTICE_ID) ? (int)$NOTICE_ID : (int)($_GET['id'] ?? 0);
$capture  = defined('PDF_CAPTURE_MODE') && PDF_CAPTURE_MODE === true;

try {
  if ($noticeId <= 0) {
    if ($capture) { echo ''; return; }
    http_response_code(400); echo 'Missing or invalid notice ID.'; exit;
  }

  // Load the notice
  $stmt = $pdo->prepare("SELECT * FROM cleanup_notices WHERE id = :id LIMIT 1");
  $stmt->execute([':id' => $noticeId]);
  $notice = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$notice) {
    if ($capture) { echo ''; return; }
    http_response_code(404); echo 'Notice not found.'; exit;
  }

  // Load photo paths from cleanup_photos (absolute paths saved by submit.php)
  $photos = [];
  if (!$SKIP_IMAGES) {
    $pstmt = $pdo->prepare("SELECT path FROM cleanup_photos WHERE notice_id = :id ORDER BY id ASC");
    $pstmt->execute([':id' => $noticeId]);
    $photos = array_map(fn($r) => (string)$r['path'], $pstmt->fetchAll(PDO::FETCH_ASSOC));
  }

  // ---------- Helpers (avoid non-ASCII glyphs that core FPDF can't render) ----------
  $f = function(string $key, string $default = 'N/A') use ($notice) {
    $v = trim((string)($notice[$key] ?? ''));
    return $v !== '' ? $v : $default; // ASCII fallback
  };
  $dt = function(string $key) use ($notice) {
    $v = trim((string)($notice[$key] ?? ''));
    if ($v === '' || $v === '0000-00-00 00:00:00') return 'N/A';
    $ts = strtotime($v);
    return $ts ? date('d/m/Y H:i', $ts) : $v; // UK format
  };

  // ---------- GD helpers for downscaling & EXIF rotation ----------
  $GD_AVAILABLE = function_exists('imagecreatefromjpeg');

  // Load any image as truecolor, auto-rotate JPEG by EXIF, flatten transparency to white
  function _img_from_path_with_exif(string $path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $im  = null;

    if (in_array($ext, ['jpg','jpeg'])) {
      $im = @imagecreatefromjpeg($path);
      if ($im && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        if (!empty($exif['Orientation'])) {
          $o = (int)$exif['Orientation'];
          if     ($o === 3) { $im = imagerotate($im, 180, 0); }
          elseif ($o === 6) { $im = imagerotate($im, -90, 0); }
          elseif ($o === 8) { $im = imagerotate($im,  90, 0); }
        }
      }
    } elseif ($ext === 'png') {
      $im = @imagecreatefrompng($path);
    } elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
      $im = @imagecreatefromwebp($path);
    } else {
      // Fallback by contents (GIF etc., if GD supports)
      $bin = @file_get_contents($path);
      $im  = $bin ? @imagecreatefromstring($bin) : null;
    }
    if (!$im) return null;

    // Convert to truecolor & flatten transparency to white
    $w = imagesx($im); $h = imagesy($im);
    $tc = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($tc, 255, 255, 255);
    imagefilledrectangle($tc, 0, 0, $w, $h, $white);
    imagealphablending($tc, true);
    imagecopy($tc, $im, 0, 0, 0, 0, $w, $h);
    imagedestroy($im);
    return $tc;
  }

  // Downscale & cache as JPEG for reliable FPDF embedding
  function downscale_for_pdf(string $absPath, int $maxW=1400, int $maxH=1400, int $quality=82): string {
    if (!function_exists('imagecreatefromjpeg') || !is_readable($absPath)) {
      return $absPath; // fallback
    }
    $stat = @stat($absPath);
    $sig  = $absPath.'|'.($stat ? $stat['size'].'|'.$stat['mtime'] : '0|0');
    $hash = sha1($sig);
    $cacheDir = dirname($absPath).'/.pdfcache';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
    $cachePath = $cacheDir.'/'.$hash.'.jpg';

    if (is_file($cachePath) && filemtime($cachePath) >= filemtime($absPath)) {
      return $cachePath;
    }

    $src = _img_from_path_with_exif($absPath);
    if (!$src) return $absPath;

    $w = imagesx($src); $h = imagesy($src);
    $scale = min($maxW / max(1,$w), $maxH / max(1,$h), 1.0);
    $newW = (int)floor($w * $scale);
    $newH = (int)floor($h * $scale);

    $dst = $src;
    if ($scale < 1.0) {
      $dst = imagecreatetruecolor($newW, $newH);
      $white = imagecolorallocate($dst, 255, 255, 255);
      imagefilledrectangle($dst, 0, 0, $newW, $newH, $white);
      imagealphablending($dst, true);
      imagesavealpha($dst, false);
      imagecopyresampled($dst, $src, 0,0,0,0, $newW, $newH, $w, $h);
      imagedestroy($src);
    }

    @imagejpeg($dst, $cachePath, $quality);
    imagedestroy($dst);

    return is_file($cachePath) ? $cachePath : $absPath;
  }

  // --------- FPDF subclass with layout helpers ----------
  class CUNoticePDF extends FPDF {
    public $logoPath = null;

    function Header() {
      if ($this->logoPath && is_readable($this->logoPath)) {
        $this->Image($this->logoPath, 10, 8, 12, 12, '', '');
      }
      $this->SetFont('helvetica', 'B', 16);
      $this->SetXY(26, 12);
      $this->Cell(0, 8, 'Clean-Up Notice', 0, 1, 'L');

      $this->SetDrawColor(200,200,200);
      $this->SetLineWidth(0.2);
      $this->Line(10, 22, 200, 22);
      $this->Ln(4);
    }

    function Footer() {
      $this->SetDrawColor(230,230,230);
      $this->SetLineWidth(0.2);
      $this->Line(10, 287, 200, 287);

      $this->SetY(-15);
      $this->SetFont('helvetica', '', 8);
      $this->Cell(0, 10, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'C');
    }

    // Key/value row with adjustable label width (prevents overlap on long labels)
    function FieldRow($label, $value, $labelW=60) {
      $this->SetFont('helvetica', 'B', 11);
      $this->Cell($labelW, 7, $label, 0, 0, 'L');
      $this->SetFont('helvetica', '', 11);
      $this->Cell(0, 7, $value, 0, 1, 'L');
    }

    function SectionTitle($text) {
      $this->Ln(2);
      $this->SetFont('helvetica', 'B', 16);
      $this->Cell(0, 9, $text, 0, 1, 'L');
      $this->SetDrawColor(210,210,210);
      $this->SetLineWidth(0.6);
      $x = $this->GetX(); $y = $this->GetY();
      $this->Line($x, $y, 200, $y);
      $this->Ln(1);
    }
  }

  $pdf = new CUNoticePDF('P', 'mm', 'A4');
  $pdf->AliasNbPages();

  $pdf->SetTitle('Clean-Up Notice #'.$noticeId, true);
  $pdf->SetAuthor('Defect Tracker', true);
  $pdf->SetCreator('Defect Tracker', true);
  $pdf->SetSubject('Clean-Up Notice', true);

  // Optional logo
  $maybeLogo = realpath(__DIR__ . '/../../assets/brand/site-documents-logo.png');
  if ($maybeLogo && is_file($maybeLogo)) $pdf->logoPath = $maybeLogo;

  $pdf->AddPage();

  // Top meta
  $pdf->SetFont('helvetica', '', 11);
  $pdf->FieldRow('Notice ID', '#'.$noticeId, 40);
  $pdf->FieldRow('Issued At', $dt('issued_at'), 40);
  $pdf->FieldRow('Issued By', $f('issued_by'), 40);
  $pdf->Ln(1);
	// After: $pdf->FieldRow('Issued By', f('issued_by'));
$pdf->Ln(1);
$pdf->SectionTitle('Status');
$pdf->FieldRow('Status', ucfirst(trim((string)($notice['status'] ?? 'open'))));
if (!empty($notice['closed_at'])) {
  $pdf->FieldRow('Closed At', date('d/m/Y H:i', strtotime($notice['closed_at'])));
}

	

  // Issue details
  $pdf->SectionTitle('Issue Details');
  $pdf->FieldRow('Site Name', $f('site_name'));
  $pdf->FieldRow('Location',  $f('location'));
  $pdf->FieldRow('Notice Issued To', $f('issued_to'));

  // Reason
  $pdf->Ln(1);
  $pdf->SectionTitle('Reason for Notification');
  $pdf->SetFont('helvetica', '', 11);
  $reason = $f('reason', '');
  if ($reason === '') {
    $reason = 'Having undertaken a survey of the site, it has become apparent that off-cuts, waste/surplus materials and/or rubbish have been left on site and are becoming a hazard. This is a Health & Safety issue and must be addressed.';
  }
  $pdf->MultiCell(0, 6, $reason);

  // Description
  $pdf->Ln(1);
  $pdf->SectionTitle('Description of Items under Notification');
  $pdf->SetFont('helvetica', '', 11);
  $pdf->MultiCell(0, 6, $f('description'));

  // Timing / Urgency (two-column)
  $pdf->Ln(1);
  $pdf->SectionTitle('Timing / Urgency');
  $pdf->SetFont('helvetica', '', 11);
  $y0 = $pdf->GetY();
  $pdf->SetXY(10, $y0);
  $pdf->FieldRow('Urgency',  $f('urgency'), 40);
  $y1 = $pdf->GetY();
  $pdf->SetXY(110, $y0);
  $pdf->FieldRow('Deadline', $dt('deadline_at'), 40);
  $pdf->SetY(max($y1, $pdf->GetY()));

  // Completion / Next action (wider labels so the '?' doesn't collide)
  $pdf->Ln(1);
  $pdf->SectionTitle('Action at End of Notification Period');
  $pdf->SetFont('helvetica', '', 11);
  $pdf->FieldRow('Completed Satisfactorily?', $f('completed_ok'), 95);
  $pdf->FieldRow('McGoff to Undertake Clearance?', $f('mcgoff_clear'), 95);

  // Photos grid (3 per row)
  if (!$SKIP_IMAGES && !empty($photos)) {
    $pdf->Ln(2);
    $pdf->SectionTitle('Photographs');

    $cols   = 3;
    $gap    = 6;      // mm
    $x0     = 10;     // left margin
    $WALL   = 200;    // right edge in mm (A4 width - margin)
    $usable = $WALL - $x0;            // 190mm
    $w      = floor(($usable - ($gap * ($cols - 1))) / $cols);
    if ($w < 40) { $w = 40; }
    $rowH   = 45;     // approx row height
    $y      = $pdf->GetY() + 2;

    foreach ($photos as $i => $abs) {
      if (!$abs || !is_readable($abs)) continue;
      $embed = downscale_for_pdf($abs, 1400, 1400, 82);

      $col = $i % $cols;
      if ($col === 0 && $i > 0) { $y += $rowH; }
      $x = $x0 + ($col * ($w + $gap));
      try {
        $pdf->Image($embed, $x, $y, $w, 0);
      } catch (\Throwable $e) {
        if ($DEBUG) {
          $pdf->SetXY($x, $y);
          $pdf->SetFont('helvetica', '', 8);
          $pdf->SetDrawColor(200,0,0);
          $pdf->SetTextColor(180,0,0);
          $pdf->Rect($x, $y, $w, 18);
          $pdf->MultiCell($w, 4.5, 'Image error: '.$e->getMessage(), 0, 'L');
        }
      }
    }
    $pdf->SetY($y + $rowH + 2);
  }

  // Signature (absolute path in DB)
  $sigAbs = trim((string)($notice['signature_path'] ?? ''));
  if ($sigAbs !== '' && is_readable($sigAbs)) {
    $pdf->Ln(2);
    $pdf->SectionTitle('Signature');
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 7, 'Signed:', 0, 1, 'L');
    $xSig = 10;
    $ySig = $pdf->GetY() + 1;
    $embedSig = downscale_for_pdf($sigAbs, 1000, 1000, 82);
    $pdf->Image($embedSig, $xSig, $ySig, 40, 0);
    $pdf->SetY($ySig + 45);
  }

  // Output
  $filename = "cleanup-{$noticeId}.pdf";
  $forceDownload = isset($_GET['dl']) && (int)$_GET['dl'] === 1;

  if ($capture) {
    $bytes = $pdf->Output('S', $filename);
    echo $bytes; return;
  } else {
    $pdf->Output($forceDownload ? 'D' : 'I', $filename);
    exit;
  }

} catch (Throwable $e) {
  if ($DEBUG) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "PDF ERROR: ".$e->getMessage()."\n\n".$e->getTraceAsString();
    return;
  }
  error_log("PDF error for notice {$noticeId}: ".$e->getMessage());
  http_response_code(500);
  echo 'Unable to render PDF.';
  exit;
}

