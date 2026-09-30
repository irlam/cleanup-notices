document.addEventListener('DOMContentLoaded', function () {
  const sigCanvas = document.getElementById('sigCanvas');
  const sigCtx = sigCanvas.getContext('2d');
  let sigDrawing = false;
  function getTouchPos(canvas, e) {
    const rect = canvas.getBoundingClientRect();
    const touch = e.touches[0];
    return { x: (touch.clientX - rect.left) * canvas.width / rect.width, y: (touch.clientY - rect.top) * canvas.height / rect.height };
  }
  sigCanvas.addEventListener('mousedown', e => { sigDrawing = true; sigCtx.beginPath(); sigCtx.moveTo(e.offsetX * e.target.width / e.target.getBoundingClientRect().width, e.offsetY * e.target.height / e.target.getBoundingClientRect().height); });
  sigCanvas.addEventListener('mousemove', e => { if (sigDrawing) { sigCtx.lineTo(e.offsetX * e.target.width / e.target.getBoundingClientRect().width, e.offsetY * e.target.height / e.target.getBoundingClientRect().height); sigCtx.stroke(); }});
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
  annCanvas.addEventListener('mousedown', e => { annDrawing = true; lastPos = [e.offsetX * e.target.width / e.target.getBoundingClientRect().width, e.offsetY * e.target.height / e.target.getBoundingClientRect().height]; });
  annCanvas.addEventListener('mousemove', e => {
    if (!annDrawing) return;
    annCtx.strokeStyle = document.getElementById('penColor').value;
    annCtx.lineWidth = parseInt(document.getElementById('penSize').value);
    annCtx.lineCap = 'round';
    annCtx.beginPath();
    annCtx.moveTo(...lastPos);
    annCtx.lineTo(e.offsetX * e.target.width / e.target.getBoundingClientRect().width, e.offsetY * e.target.height / e.target.getBoundingClientRect().height);
    annCtx.stroke();
    lastPos = [e.offsetX * e.target.width / e.target.getBoundingClientRect().width, e.offsetY * e.target.height / e.target.getBoundingClientRect().height];
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
  window.resetNoticeCanvases = () => { annBg = null; sigCtx.clearRect(0,0,sigCanvas.width,sigCanvas.height); annCtx.clearRect(0,0,annCanvas.width,annCanvas.height); };
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

  Promise.resolve().then(async () => {
      const owner = await DocsOffline.meta('activeOwner');
      const cached = await DocsOffline.meta('context:' + owner);
      return cached?.recipients || [];
    })
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
    }).catch(() => {});

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
