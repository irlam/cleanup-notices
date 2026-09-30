<?php
// /forms/clean-up/form.php
declare(strict_types=1);
session_start();
if (!isset($_SESSION['user'])) { header('Location: /index.php'); exit; }

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

date_default_timezone_set('Europe/London');
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
  <style>
    body {
      background-color: #111827;
      color: #f9fafb;
      font-family: system-ui, sans-serif;
      margin: 0; padding: 0;
    }
    .container {
      max-width: 860px;
      margin: 0 auto;
      padding: 1.5rem;
    }
    h1 {
      font-size: 1.8rem;
      margin-bottom: 1rem;
      color: #60a5fa;
    }
    label { display: block; font-weight: bold; margin-top: 1rem; }
    input, select, textarea {
      width: 100%;
      background-color: #1f2937;
      color: #fff;
      border: 1px solid #374151;
      border-radius: 6px;
      padding: 0.5rem;
      margin-top: 0.25rem;
      font-size: 1rem;
    }
    .form-row { margin-bottom: 1rem; }
    .thumbs img { height: 100px; margin: 4px; border-radius: 6px; border: 1px solid #555; }
    canvas {
      background: #fff;
      border: 1px solid #888;
      width: 100%;
      height: auto;
      touch-action: none;
    }
    .btn {
      background: #2563eb;
      color: #fff;
      border: none;
      padding: 0.5rem 1rem;
      margin-top: 1rem;
      border-radius: 6px;
      cursor: pointer;
      font-size: 1rem;
    }
    .btn:hover { background: #1d4ed8; }
    .toolbar { margin-top: 0.5rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; }
    #recipientChips {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      margin-top: 0.5rem;
    }
    .chip-btn {
      background: #1f2937;
      border: 1px solid #374151;
      color: #f9fafb;
      padding: 0.4rem 0.75rem;
      border-radius: 9999px;
      cursor: pointer;
      font-size: 0.9rem;
      transition: all 0.2s ease;
    }
    .chip-btn.selected {
      background: #059669;
      color: #fff;
    }
  </style>
<main class="container docs-form-page">
  <h1>New Clean-Up Notice</h1>
  <form method="post" action="submit.php" enctype="multipart/form-data" onsubmit="return beforeSubmit();">
    <div class="form-row"><label for="site_name">Site Name *</label><input type="text" id="site_name" name="site_name" required></div>
    <div class="form-row"><label for="location">Location *</label><input type="text" id="location" name="location" required></div>
    <div class="form-row"><label for="issued_at">Date / Time Issued *</label><input type="datetime-local" id="issued_at" name="issued_at" required></div>
    <div class="form-row"><label for="issued_to">Issued To *</label><input type="text" id="issued_to" name="issued_to" required></div>
    <div class="form-row"><label for="issued_by">Issued By *</label><input type="text" id="issued_by" name="issued_by" required></div>
    <div class="form-row"><label for="reason">Reason</label><textarea id="reason" name="reason" rows="2"></textarea></div>
    <div class="form-row"><label for="description">Description *</label><textarea id="description" name="description" rows="3" required></textarea></div>
    <div class="form-row">
      <label for="urgency">Urgency *</label>
      <select id="urgency" name="urgency" required onchange="autoSetDeadline()">
        <option value="">— Select —</option>
        <option value="Immediate">Immediate</option>
        <option value="Within 1 hour">Within 1 hour</option>
        <option value="Same day">Same day</option>
        <option value="Within 24 hours">Within 24 hours</option>
        <option value="This week">This week</option>
      </select>
    </div>
    <div class="form-row"><label for="deadline_at">Deadline</label><input type="datetime-local" id="deadline_at" name="deadline_at"></div>
    <div class="form-row"><label for="completed_ok">Completed OK? *</label>
      <select id="completed_ok" name="completed_ok" required>
        <option value="">— Select —</option>
        <option value="yes">Yes</option>
        <option value="no">No</option>
      </select>
    </div>
    <div class="form-row"><label for="mcgoff_clear">McGoff to Clear? *</label>
      <select id="mcgoff_clear" name="mcgoff_clear" required>
        <option value="">— Select —</option>
        <option value="yes">Yes</option>
        <option value="no">No</option>
      </select>
    </div>

    <div class="form-row"><label for="photos">Upload Photos</label><input type="file" id="photos" name="photos[]" accept="image/*;capture=camera" multiple><div id="photoThumbs" class="thumbs"></div></div>

    <div class="form-row">
      <label for="annotate_file">Annotate (optional)</label>
      <input type="file" id="annotate_file" accept="image/*">
      <div class="toolbar">
        <label>Pen: <input type="color" id="penColor" value="#e11d48"></label>
        <label>Size: <input type="range" id="penSize" min="2" max="24" value="4"></label>
        <button type="button" onclick="clearAnnotation()">Clear</button>
        <button type="button" onclick="addAnnotation()">Add annotated image</button>
      </div>
      <canvas id="annCanvas" width="600" height="400"></canvas>
      <div id="annThumbs" class="thumbs"></div>
      <div id="annFields"></div>
    </div>

    <div class="form-row">
      <label>Signature *</label>
      <canvas id="sigCanvas" width="600" height="200"></canvas>
      <div class="toolbar"><button type="button" onclick="clearSignature()">Clear Signature</button></div>
      <input type="hidden" id="signature_data" name="signature_data">
    </div>

    <div class="form-row">
      <label>Recipients *</label>
      <select id="recipients" name="recipients[]" multiple required></select>
      <input type="email" id="extra_email" placeholder="Add recipient email...">
      <button type="button" class="btn" onclick="addRecipient()">+ Add Email</button>
    </div>

    <div class="form-row">
      <label>Tap to add previous recipients:</label>
      <div id="recipientChips"></div>
    </div>

    <div class="form-row"><button type="submit" class="btn">Submit Notice</button></div>
  </form>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const sigCanvas = document.getElementById('sigCanvas');
  const sigCtx = sigCanvas.getContext('2d');
  let sigDrawing = false;
  function getTouchPos(canvas, e) {
    const rect = canvas.getBoundingClientRect();
    const touch = e.touches[0];
    return { x: touch.clientX - rect.left, y: touch.clientY - rect.top };
  }
  sigCanvas.addEventListener('mousedown', e => { sigDrawing = true; sigCtx.beginPath(); sigCtx.moveTo(e.offsetX, e.offsetY); });
  sigCanvas.addEventListener('mousemove', e => { if (sigDrawing) { sigCtx.lineTo(e.offsetX, e.offsetY); sigCtx.stroke(); }});
  window.addEventListener('mouseup', () => sigDrawing = false);
  sigCanvas.addEventListener('touchstart', e => {
    e.preventDefault(); sigDrawing = true;
    const pos = getTouchPos(sigCanvas, e);
    sigCtx.beginPath(); sigCtx.moveTo(pos.x, pos.y);
  });
  sigCanvas.addEventListener('touchmove', e => {
    e.preventDefault();
    if (!sigDrawing) return;
    const pos = getTouchPos(sigCanvas, e);
    sigCtx.lineTo(pos.x, pos.y);
    sigCtx.stroke();
  });
  sigCanvas.addEventListener('touchend', e => sigDrawing = false);
  window.clearSignature = () => { sigCtx.clearRect(0, 0, sigCanvas.width, sigCanvas.height); };
  window.beforeSubmit = () => {
    document.getElementById('signature_data').value = sigCanvas.toDataURL('image/png');
    return true;
  };

  document.getElementById('photos').addEventListener('change', function () {
    const thumbsDiv = document.getElementById('photoThumbs');
    thumbsDiv.innerHTML = '';
    [...this.files].forEach(f => {
      const reader = new FileReader();
      reader.onload = e => {
        const img = document.createElement('img');
        img.src = e.target.result;
        thumbsDiv.appendChild(img);
      };
      reader.readAsDataURL(f);
    });
  });

  const annCanvas = document.getElementById('annCanvas');
  const annCtx = annCanvas.getContext('2d');
  let annDrawing = false, lastPos = null, annBg = null;
  annCanvas.addEventListener('mousedown', e => { annDrawing = true; lastPos = [e.offsetX, e.offsetY]; });
  annCanvas.addEventListener('mousemove', e => {
    if (!annDrawing) return;
    annCtx.strokeStyle = document.getElementById('penColor').value;
    annCtx.lineWidth = parseInt(document.getElementById('penSize').value);
    annCtx.lineCap = 'round';
    annCtx.beginPath();
    annCtx.moveTo(...lastPos);
    annCtx.lineTo(e.offsetX, e.offsetY);
    annCtx.stroke();
    lastPos = [e.offsetX, e.offsetY];
  });
  window.addEventListener('mouseup', () => annDrawing = false);

  annCanvas.addEventListener('touchstart', e => {
    e.preventDefault(); annDrawing = true;
    const pos = getTouchPos(annCanvas, e);
    lastPos = [pos.x, pos.y];
  });
  annCanvas.addEventListener('touchmove', e => {
    e.preventDefault();
    if (!annDrawing) return;
    const pos = getTouchPos(annCanvas, e);
    annCtx.strokeStyle = document.getElementById('penColor').value;
    annCtx.lineWidth = parseInt(document.getElementById('penSize').value);
    annCtx.lineCap = 'round';
    annCtx.beginPath();
    annCtx.moveTo(...lastPos);
    annCtx.lineTo(pos.x, pos.y);
    annCtx.stroke();
    lastPos = [pos.x, pos.y];
  });
  annCanvas.addEventListener('touchend', () => annDrawing = false);
  window.clearAnnotation = () => { if (annBg) annCtx.drawImage(annBg, 0, 0, annCanvas.width, annCanvas.height); else annCtx.clearRect(0, 0, annCanvas.width, annCanvas.height); };
  window.addAnnotation = () => {
    const dataUrl = annCanvas.toDataURL('image/png');
    const input = document.createElement('input');
    input.type = 'hidden'; input.name = 'annotate[]'; input.value = dataUrl;
    document.getElementById('annFields').appendChild(input);
    const img = document.createElement('img');
    img.src = dataUrl;
    document.getElementById('annThumbs').appendChild(img);
  };
  document.getElementById('annotate_file').addEventListener('change', function () {
    const file = this.files[0]; if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
      const img = new Image();
      img.onload = () => {
        annBg = img;
        annCtx.clearRect(0, 0, annCanvas.width, annCanvas.height);
        annCtx.drawImage(img, 0, 0, annCanvas.width, annCanvas.height);
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  });

  fetch('/forms/clean-up/get_recipient_emails.php')
    .then(res => res.json())
    .then(data => {
      const container = document.getElementById('recipientChips');
      const sel = document.getElementById('recipients');
      data.forEach(email => {
        const chip = document.createElement('span');
        chip.className = 'chip-btn';
        chip.textContent = email;
        chip.dataset.email = email;
        chip.addEventListener('click', () => {
          let exists = false;
          for (let opt of sel.options) {
            if (opt.value === email) {
              opt.selected = !opt.selected;
              exists = true;
              break;
            }
          }
          if (!exists) {
            const opt = new Option(email, email, true, true);
            sel.add(opt);
          }
          chip.classList.toggle('selected');
          chip.textContent = chip.classList.contains('selected') ? email + ' ✔️' : email;
        });
        container.appendChild(chip);
      });
    });

  window.autoSetDeadline = () => {
    const urgency = document.getElementById('urgency').value;
    if (!urgency) return;
    const now = new Date();
    let deadline = new Date(now);
    switch (urgency) {
      case 'Immediate': deadline = now; break;
      case 'Within 1 hour': deadline.setHours(now.getHours() + 1); break;
      case 'Same day': deadline.setHours(23, 59, 0); break;
      case 'Within 24 hours': deadline.setDate(now.getDate() + 1); break;
      case 'This week': deadline.setDate(now.getDate() + (5 - now.getDay())); break;
    }
    const pad = n => String(n).padStart(2, '0');
    const ukFormat = `${deadline.getFullYear()}-${pad(deadline.getMonth()+1)}-${pad(deadline.getDate())}T${pad(deadline.getHours())}:${pad(deadline.getMinutes())}`;
    document.getElementById('deadline_at').value = ukFormat;
  };

  window.addRecipient = () => {
    const val = document.getElementById('extra_email').value.trim();
    if (!val || !val.includes('@')) return;
    const sel = document.getElementById('recipients');
    let exists = false;
    for (let opt of sel.options) {
      if (opt.value === val) {
        opt.selected = true;
        exists = true;
        break;
      }
    }
    if (!exists) {
      const opt = new Option(val, val, true, true);
      sel.add(opt);
    }
    document.getElementById('extra_email').value = '';
  };
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

