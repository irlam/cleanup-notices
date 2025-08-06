(function(){
  const canvas = document.getElementById('sigpad');
  if(!canvas) return;
  const ctx = canvas.getContext('2d');
  let drawing = false, last = null;
  function getPos(e){
    const rect = canvas.getBoundingClientRect();
    const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
    const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top;
    return {x, y};
  }
  function start(e){ drawing = true; last = getPos(e); e.preventDefault(); }
  function move(e){
    if(!drawing) return;
    const p = getPos(e);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    last = p;
    e.preventDefault();
  }
  function end(e){ drawing = false; e && e.preventDefault(); }
  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  window.addEventListener('mouseup', end);
  canvas.addEventListener('touchstart', start, {passive:false});
  canvas.addEventListener('touchmove', move, {passive:false});
  canvas.addEventListener('touchend', end, {passive:false});
  document.getElementById('clearSig')?.addEventListener('click', function(){
    ctx.clearRect(0,0,canvas.width, canvas.height);
  });
  function resize(){
    const w = canvas.clientWidth, h = canvas.clientHeight;
    const img = ctx.getImageData(0,0,canvas.width, canvas.height);
    canvas.width = w; canvas.height = h;
    try{ ctx.putImageData(img, 0, 0); }catch(e){}
  }
  window.addEventListener('resize', resize);
  resize();
})();