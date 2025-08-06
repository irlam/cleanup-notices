<?php
/**
 * includes/image_utils.php
 *
 * Safe image processing utilities for uploads:
 * - Accepts JPEG/PNG/WebP/HEIC/HEIF (via Imagick fallback).
 * - Auto-rotates by EXIF.
 * - Flattens transparency onto white.
 * - Downscales to max dimensions.
 * - Writes JPEG to destination directory with safe filename.
 *
 * Requirements: PHP GD; optional Imagick for HEIC/HEIF.
 */

declare(strict_types=1);

if (!function_exists('iu_safe_filename')) {
  function iu_safe_filename(string $name): string {
    $name = preg_replace('/[^\w\.\-]+/u', '_', $name);
    $name = trim($name, '._');
    if ($name === '') $name = 'img';
    // Force .jpg extension (we output JPEG)
    return preg_replace('/\.(jpe?g|png|gif|bmp|webp|heic|heif)$/i', '', $name) . '.jpg';
  }
}

if (!function_exists('iu_resample_to_jpeg')) {
  /**
   * Resample a GD image to JPEG file with white background,
   * maintaining aspect ratio to fit within $maxW x $maxH.
   */
  function iu_resample_to_jpeg($gd, string $destPath, int $maxW = 2000, int $maxH = 2000, int $quality = 82): bool {
    if (!is_resource($gd) && !(is_object($gd) && get_class($gd) === 'GdImage')) return false;
    $w = imagesx($gd); $h = imagesy($gd);
    if ($w < 1 || $h < 1) return false;

    $scale = min($maxW / max(1,$w), $maxH / max(1,$h), 1.0);
    $newW = (int)floor($w * $scale);
    $newH = (int)floor($h * $scale);

    $dst = imagecreatetruecolor($newW, $newH);
    // Fill white (flatten alpha)
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $newW, $newH, $white);
    imagealphablending($dst, true);
    imagesavealpha($dst, false);

    imagecopyresampled($dst, $gd, 0,0,0,0, $newW, $newH, $w, $h);
    $ok = imagejpeg($dst, $destPath, $quality);
    imagedestroy($dst);
    return (bool)$ok;
  }
}

if (!function_exists('iu_gd_from_path_or_imagick')) {
  /**
   * Load any image path into a GD image:
   * - Try GD fast paths.
   * - Fallback to Imagick (handles HEIC/HEIF).
   * - Auto-orient by EXIF where possible.
   * - Flatten transparency to white, return GD truecolor.
   */
  function iu_gd_from_path_or_imagick(string $path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $gd  = null;

    $try_by_contents = function(string $p) {
      $bin = @file_get_contents($p);
      return $bin ? @imagecreatefromstring($bin) : null;
    };

    // --- Try GD first
    if (in_array($ext, ['jpg','jpeg'])) {
      $gd = @imagecreatefromjpeg($path);
      if ($gd && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        if (!empty($exif['Orientation'])) {
          $o = (int)$exif['Orientation'];
          if     ($o === 3) { $gd = imagerotate($gd, 180, 0); }
          elseif ($o === 6) { $gd = imagerotate($gd, -90, 0); }
          elseif ($o === 8) { $gd = imagerotate($gd,  90, 0); }
        }
      }
    } elseif ($ext === 'png') {
      $gd = @imagecreatefrompng($path);
    } elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
      $gd = @imagecreatefromwebp($path);
    } else {
      // Generic attempt (GIF, BMP if supported by GD build)
      $gd = $try_by_contents($path);
    }

    // --- Imagick fallback (HEIC/HEIF or anything GD can't do)
    if (!$gd && class_exists('Imagick')) {
      try {
        $img = new Imagick($path);
        if ($img->getNumberImages() > 1) $img->setIteratorIndex(0);
        if (method_exists($img, 'autoOrient')) $img->autoOrient();

        // Composite onto white background (flatten alpha)
        $bg = new Imagick();
        $bg->newImage($img->getImageWidth(), $img->getImageHeight(), 'white', 'jpeg');
        $bg->compositeImage($img, Imagick::COMPOSITE_OVER, 0, 0);
        $bg->setImageFormat('jpeg');

        $blob = $bg->getImageBlob();
        $img->clear(); $img->destroy();
        $bg->clear();  $bg->destroy();

        $gd = @imagecreatefromstring($blob);
      } catch (\Throwable $e) {
        $gd = null;
      }
    }

    if (!$gd) return null;

    // Ensure truecolor + flatten transparency to white again (safe)
    $w = imagesx($gd); $h = imagesy($gd);
    $tc = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($tc, 255, 255, 255);
    imagefilledrectangle($tc, 0, 0, $w, $h, $white);
    imagealphablending($tc, true);
    imagecopy($tc, $gd, 0, 0, 0, 0, $w, $h);
    imagedestroy($gd);
    return $tc;
  }
}

if (!function_exists('process_uploaded_image')) {
  /**
   * Process a single uploaded file (from $_FILES),
   * saving a rotated/flattened JPEG into $destDir.
   *
   * @param string $tmpPath     Temporary uploaded file path
   * @param string $destDir     Destination directory (must exist)
   * @param string $original    Original filename (for naming)
   * @param int    $maxW        Max width
   * @param int    $maxH        Max height
   * @param int    $quality     JPEG quality
   * @return string|null        Absolute path to saved JPEG or null on failure
   */
  function process_uploaded_image(string $tmpPath, string $destDir, string $original, int $maxW=2000, int $maxH=2000, int $quality=82): ?string {
    if (!is_uploaded_file($tmpPath)) return null;
    if (!is_dir($destDir)) @mkdir($destDir, 0775, true);

    $gd = iu_gd_from_path_or_imagick($tmpPath);
    if (!$gd) return null;

    $safeName = iu_safe_filename($original ?: ('img_'.date('Ymd_His').'.jpg'));
    $destPath = rtrim($destDir, '/').'/'.$safeName;

    if (!iu_resample_to_jpeg($gd, $destPath, $maxW, $maxH, $quality)) {
      return null;
    }
    return realpath($destPath) ?: $destPath;
  }
}

if (!function_exists('process_base64_image')) {
  /**
   * Process a base64 data URL (e.g., canvas export),
   * save as rotated/flattened JPEG at desired size.
   *
   * @param string $dataUrl   'data:image/png;base64,...' etc.
   * @param string $destDir
   * @param string $suggestedName  Suggested base name (no extension needed)
   * @param int    $maxW
   * @param int    $maxH
   * @param int    $quality
   * @return string|null Absolute path to saved JPEG or null on failure
   */
  function process_base64_image(string $dataUrl, string $destDir, string $suggestedName='annotated', int $maxW=2000, int $maxH=2000, int $quality=82): ?string {
    if (strpos($dataUrl, 'base64,') === false) return null;
    [$meta, $b64] = explode('base64,', $dataUrl, 2);
    $raw = base64_decode($b64);
    if ($raw === false) return null;

    if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
    $tmp = tempnam(sys_get_temp_dir(), 'b64img_');
    file_put_contents($tmp, $raw);

    $gd = iu_gd_from_path_or_imagick($tmp);
    @unlink($tmp);
    if (!$gd) return null;

    $safeName = iu_safe_filename($suggestedName).'.jpg';
    $destPath = rtrim($destDir, '/').'/'.$safeName;

    if (!iu_resample_to_jpeg($gd, $destPath, $maxW, $maxH, $quality)) {
      return null;
    }
    return realpath($destPath) ?: $destPath;
  }
}
