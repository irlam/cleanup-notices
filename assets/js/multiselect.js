(function(){
  function enhanceSelect(select){
    if (!select || select.dataset.enhanced === '1') return;
    select.dataset.enhanced = '1';
    select.style.display = 'none'; // keep for form submission

    // Build wrapper
    const wrap = document.createElement('div');
    wrap.className = 'ms';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);

    // Input (search)
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'ms-input';
    input.placeholder = select.dataset.placeholder || 'Search recipients…';
    wrap.appendChild(input);

    // Tags container
    const tags = document.createElement('div');
    tags.className = 'ms-tags';
    wrap.appendChild(tags);

    // Panel (options)
    const panel = document.createElement('div');
    panel.className = 'ms-panel';
    wrap.appendChild(panel);

    // Build an array of options
    const options = Array.from(select.options).map((opt, idx) => ({
      value: opt.value,
      label: opt.textContent,
      selected: opt.selected,
      index: idx
    }));

    function renderTags(){
      tags.innerHTML = '';
      options.filter(o => o.selected).forEach(o => {
        const tag = document.createElement('span');
        tag.className = 'ms-tag';
        tag.innerHTML = `<span>${o.label}</span>`;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.setAttribute('aria-label', 'Remove');
        btn.innerHTML = '✕';
        btn.addEventListener('click', () => toggle(o.value, false));
        tag.appendChild(btn);
        tags.appendChild(tag);
      });
    }

    function renderPanel(filterText){
      panel.innerHTML = '';
      const q = (filterText || '').trim().toLowerCase();
      const items = options.filter(o => !q || o.label.toLowerCase().includes(q));
      if (items.length === 0){
        const empty = document.createElement('div');
        empty.className = 'ms-empty';
        empty.textContent = 'No matches';
        panel.appendChild(empty);
        return;
      }
      items.forEach(o => {
        const row = document.createElement('div');
        row.className = 'ms-item' + (o.selected ? ' is-selected' : '');
        row.innerHTML = `<span>${o.label}</span><span>${o.selected ? '✓' : ''}</span>`;
        row.addEventListener('click', () => toggle(o.value, !o.selected));
        panel.appendChild(row);
      });
    }

    function syncNative(){
      options.forEach((o, i) => {
        select.options[i].selected = !!o.selected;
      });
    }

    function toggle(value, makeSelected){
      const o = options.find(x => x.value === value);
      if (!o) return;
      o.selected = !!makeSelected;
      syncNative();
      renderTags();
      renderPanel(input.value);
    }

    // Open/close behaviour
    function open(){ wrap.classList.add('open'); renderPanel(input.value); }
    function close(){ wrap.classList.remove('open'); }
    input.addEventListener('focus', open);
    input.addEventListener('input', () => renderPanel(input.value));
    document.addEventListener('click', (e) => {
      if (!wrap.contains(e.target)) close();
    });

    // Keyboard support: Enter to toggle first visible item
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter'){
        e.preventDefault();
        const first = panel.querySelector('.ms-item');
        if (first){
          const text = first.querySelector('span').textContent;
          const o = options.find(x => x.label === text);
          if (o) toggle(o.value, !o.selected);
        }
      }
    });

    // Initial render (respect any preselected values)
    renderTags();
    renderPanel('');
  }

  // Enhance any <select multiple data-enhance="searchable">
  function init(){
    document.querySelectorAll('select[multiple][data-enhance="searchable"]').forEach(enhanceSelect);
  }
  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
