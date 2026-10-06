/* Shared durable notice outbox, used by pages and the service worker. */
(function (scope) {
  'use strict';
  const DB_NAME = 'site-documents-offline-v1';
  let opening;
  function open() {
    if (opening) return opening;
    opening = new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, 1);
      request.onupgradeneeded = () => {
        const db = request.result;
        db.createObjectStore('items', {keyPath: 'id'});
        db.createObjectStore('meta', {keyPath: 'key'});
        db.createObjectStore('library', {keyPath: 'key'});
      };
      request.onsuccess = () => { request.result.onversionchange = () => { request.result.close(); opening = null; }; resolve(request.result); };
      request.onerror = () => { opening = null; reject(request.error); };
      request.onblocked = () => { opening = null; reject(new Error('Close other Site Documents tabs and retry.')); };
    });
    return opening;
  }
  async function transaction(store, mode, action) {
    const db = await open();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(store, mode);
      let result;
      tx.oncomplete = () => resolve(result);
      tx.onerror = tx.onabort = () => reject(tx.error || new Error('Device storage failed.'));
      const req = action(tx.objectStore(store));
      if (req) req.onsuccess = () => { result = req.result; };
    });
  }
  const get = (store, key) => transaction(store, 'readonly', s => s.get(key));
  const put = (store, value) => transaction(store, 'readwrite', s => s.put(value));
  const remove = (store, key) => transaction(store, 'readwrite', s => s.delete(key));
  const all = store => transaction(store, 'readonly', s => s.getAll());
  const meta = async key => (await get('meta', key))?.value ?? null;
  const setMeta = (key, value) => put('meta', {key, value});
  function id() { return scope.crypto.randomUUID(); }
  function serialise(data) {
    return [...data.entries()].filter(([name, value]) => !['csrf_token','offline_owner','client_submission_id'].includes(name) && !(value instanceof Blob && value.size === 0))
      .map(([name, value]) => value instanceof Blob ? {name, kind:'file', blob:value, filename:value.name || 'photo.jpg'} : {name, kind:'text', value:String(value)});
  }
  function restore(item, context) {
    const data = new FormData();
    for (const entry of item.entries) {
      if (entry.kind === 'file') data.append(entry.name, entry.blob, entry.filename);
      else data.append(entry.name, entry.value);
    }
    data.set('client_submission_id', item.id);
    data.set('csrf_token', context.csrfToken);
    data.set('offline_owner', item.owner);
    data.set('offline_photo_count', String(item.entries.filter(e=>e.kind==='file'&&e.name==='photos[]').length));
    data.set('offline_annotation_count', String(item.entries.filter(e=>e.name==='annotate[]').length));
    return data;
  }
  async function request(url, options = {}) {
    const controller = new AbortController();
    const {timeout=90000,...fetchOptions}=options;
    const timer = setTimeout(() => controller.abort(), timeout);
    try { return await fetch(url, {credentials:'same-origin', cache:'no-store', redirect:'error', ...fetchOptions, signal:controller.signal}); }
    finally { clearTimeout(timer); }
  }
  async function context() {
    const response = await request('/api/offline_context.php', {timeout:10000,headers:{Accept:'application/json'}});
    if (response.status === 401) throw Object.assign(new Error('Sign in to sync. Your notices are still saved.'), {auth:true});
    if (!response.ok) throw new Error('Could not reach the site. Your notices are still saved.');
    const result = await response.json();
    if (!result.success || !result.owner || !result.csrfToken) throw new Error('Offline setup is unavailable.');
    return result;
  }
  async function lock() {
    const db = await open();
    return new Promise((resolve,reject) => {
      const tx = db.transaction('meta','readwrite'), store = tx.objectStore('meta'), token = id();
      let acquired = false;
      const req = store.get('syncLease');
      req.onsuccess = () => {
        if (!req.result || req.result.value.until < Date.now()) {
          acquired = true; store.put({key:'syncLease',value:{token,until:Date.now()+150000}});
        }
      };
      tx.oncomplete = () => resolve(acquired ? token : null);
      tx.onerror = tx.onabort = () => reject(tx.error);
    });
  }
  async function renew(token) {
    const db = await open();
    await new Promise((resolve,reject) => {
      const tx=db.transaction('meta','readwrite'),store=tx.objectStore('meta'),req=store.get('syncLease');
      req.onsuccess=()=>{if(req.result?.value.token===token)store.put({key:'syncLease',value:{token,until:Date.now()+150000}});};
      tx.oncomplete=resolve;tx.onerror=tx.onabort=()=>reject(tx.error);
    });
  }
  async function release(token) {
    const db = await open();
    await new Promise((resolve,reject) => {
      const tx=db.transaction('meta','readwrite'),store=tx.objectStore('meta'),req=store.get('syncLease');
      req.onsuccess=()=>{if(req.result?.value.token===token)store.delete('syncLease');};
      tx.oncomplete=resolve;tx.onerror=tx.onabort=()=>reject(tx.error);
    });
  }
  async function noticeItems(owner) { return (await all('items')).filter(item => item.owner === owner).sort((a,b)=>b.createdAt-a.createdAt); }
  async function binary(url, type) {
    const response = await request(url);
    if (!response.ok || !(response.headers.get('Content-Type') || '').startsWith(type)) throw new Error('The file is not available yet.');
    const blob=await response.blob();
    if (type==='application/pdf' && (await blob.slice(0,5).text())!=='%PDF-') throw new Error('Invalid PDF response.');
    return blob;
  }
  async function downloadPdf(item) {
    const fresh=await context();
    if (fresh.owner!==item.owner || (await meta('activeOwner'))!==item.owner) throw new Error('Sign in as the notice author.');
    const pdf=await binary(item.receipt.pdfUrl,'application/pdf');
    const current=await get('items',item.id);
    if(current?.status==='synced')await put('items',{...current,pdf});
  }
  async function sync() {
    const token=await lock();
    if(!token)return {busy:true};
    const heartbeat=setInterval(()=>renew(token).catch(()=>{}),30000);
    let uploaded=0;
    try {
      const owner=await meta('activeOwner');
      if(!owner||await meta('logoutPending'))return {locked:true};
      const fresh=await context();
      if(fresh.owner!==owner)throw Object.assign(new Error('Sign in as '+owner+' to sync these notices.'),{auth:true});
      await setMeta('context:'+owner,fresh);
      const rows=await noticeItems(owner);
      for(const item of rows.reverse()) {
        if((await meta('activeOwner'))!==owner)break;
        if(item.status==='synced') {
          if(!['sent','not_required'].includes(item.receipt?.emailStatus))try{const response=await request('/api/offline_receipt.php?client_id='+encodeURIComponent(item.id),{timeout:10000});const body=await response.json();if(response.ok&&body.success&&body.owner===owner){item.receipt=body;await put('items',item);}}catch{}
          if(!item.pdf)try{await downloadPdf(item);}catch{}
          continue;
        }
        if(!['pending','uploading','needs_auth'].includes(item.status) || (item.nextTry || 0)>Date.now())continue;
        await renew(token);
        item.status='uploading';item.lastError='';await put('items',item);
        try {
          const response=await request(item.action==='close'?'/api/offline_close.php':'/forms/clean-up/submit.php',{method:'POST',headers:{Accept:'application/json','X-Offline-Submission':'1'},body:restore(item,fresh)});
          let body={};try{body=await response.json();}catch{}
          if(response.status===401 || response.status===403)throw Object.assign(new Error(body.message || 'Sign in again to sync.'),{auth:true});
          if(!response.ok || !body.success)throw Object.assign(new Error(body.message || 'Upload not confirmed. Your notice remains saved.'),{permanent:[400,409,413,422].includes(response.status),httpStatus:response.status});
          item.status='synced';item.receipt=body;item.syncedAt=Date.now();item.lastError='';item.nextTry=0;
          // Record the server receipt before downloading the PDF. Never re-submit
          // a confirmed notice just because a subsequent PDF download fails.
          await put('items',item);uploaded++;
          try{await downloadPdf(item);}catch{}
        } catch(error) {
          item.status=error.auth?'needs_auth':error.permanent?'needs_attention':'pending';
          item.lastError=error.message;item.httpStatus=error.httpStatus||0;item.attempts=(item.attempts||0)+1;
          item.nextTry=error.auth||error.permanent?0:Date.now()+Math.min(300000,5000*2**Math.min(item.attempts,6));
          await put('items',item);
          if(error.auth || !error.permanent)break;
        }
      }
      return {uploaded,remaining:(await noticeItems(owner)).filter(item=>['pending','uploading'].includes(item.status)).length};
    } finally { clearInterval(heartbeat); await release(token); }
  }
  scope.DocsOffline={open,get,put,remove,all,meta,setMeta,id,serialise,restore,context,noticeItems,sync,binary,downloadPdf,request};
})(typeof self !== 'undefined' ? self : globalThis);
