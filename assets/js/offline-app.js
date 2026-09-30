/* Device workspace, autosave, attachments and automatic reconnection sync. */
(() => {
  'use strict';
  const db=DocsOffline;
  let owner=null,editorId=null,restoredFiles=[],saveTimer,saveChain=Promise.resolve(),submitting=false,rendering=false;
  const urls=[];
  const sessionChannel='BroadcastChannel'in window?new BroadcastChannel('site-documents-session'):null;
  if(sessionChannel)sessionChannel.onmessage=event=>{if(event.data?.owner!==owner){owner=null;submitting=true;if($('noticeForm'))$('noticeForm').closest('main').hidden=true;render().then(()=>message('The signed-in account changed. Reopen this workspace.'));}};
  const $=id=>document.getElementById(id);
  function status(text){if($('offlineStatus'))$('offlineStatus').textContent=text;if($('offlineBadge'))$('offlineBadge').textContent=text;}
  function message(text){if($('formSaveStatus'))$('formSaveStatus').textContent=text;if($('offlineFeedback'))$('offlineFeedback').textContent=text;status(text);}
  function field(item,name){return item.entries.find(e=>e.kind==='text'&&e.name===name)?.value||'';}
  function button(text,action){const node=document.createElement('button');node.type='button';node.className='btn-secondary';node.textContent=text;node.addEventListener('click',()=>Promise.resolve(action()).catch(error=>message(error.message)));return node;}
  function download(blob,name){const url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),60000);}
  async function draftPdf(item){
    if(!window.jspdf)throw new Error('Prepare offline use while online to download the PDF tools.');
    const {jsPDF}=window.jspdf,pdf=new jsPDF({compress:true});let y=16;
    const line=(text,bold=false)=>{pdf.setFont('helvetica',bold?'bold':'normal');pdf.setFontSize(bold?14:10);const rows=pdf.splitTextToSize(String(text).replace(/[\u2010-\u2015]/g,'-'),180);if(y+rows.length*5>275){pdf.addPage();y=16;}pdf.text(rows,15,y);y+=rows.length*5+4;};
    line('Site Documents - Clean-Up Notice',true);
    line(item.status==='synced'?'Local copy - server reference #'+item.receipt.id:'OFFLINE DRAFT - awaiting server synchronisation',true);
    line('Device reference: '+item.id);line('Saved by: '+item.owner);
    const labels={site_name:'Site',location:'Location',issued_at:'Issued at',issued_to:'Issued to',issued_by:'Issued by',reason:'Reason',description:'Description',urgency:'Urgency',deadline_at:'Deadline',completed_ok:'Completed OK',mcgoff_clear:'Main contractor to arrange clearance'};
    for(const [name,label]of Object.entries(labels))line(label+': '+field(item,name));
    line('Recipients: '+item.entries.filter(e=>e.name==='recipients[]').map(e=>e.value).join(', '));
    async function image(data,label){
      try{
        const blob=data instanceof Blob?data:await (await fetch(data)).blob(),url=URL.createObjectURL(blob),img=new Image();
        try{await new Promise((resolve,reject)=>{img.onload=resolve;img.onerror=reject;img.src=url;});const canvas=document.createElement('canvas');const scale=Math.min(1,1400/img.naturalWidth);canvas.width=img.naturalWidth*scale;canvas.height=img.naturalHeight*scale;const ctx=canvas.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.drawImage(img,0,0,canvas.width,canvas.height);const h=Math.min(180,150*canvas.height/canvas.width),w=h*canvas.width/canvas.height;if(y+h+15>275){pdf.addPage();y=16;}line(label);pdf.addImage(canvas.toDataURL('image/jpeg',.8),'JPEG',15,y,w,h);y+=h+8;}finally{URL.revokeObjectURL(url);}
      }catch{line(label+' (original image saved on device; this format cannot be previewed here)');}
    }
    for(const e of item.entries){if(e.kind==='file')await image(e.blob,e.filename);else if(e.name==='annotate[]')await image(e.value,'Annotated image');}
    if(field(item,'signature_data'))await image(field(item,'signature_data'),'Signature');
    if(item.status!=='synced')line('This is a local draft. No notice number or email delivery is confirmed until synchronisation completes.');
    download(pdf.output('blob'),'notice-'+(item.receipt?.id||item.id)+'-local.pdf');
  }
  function previews(node,entries){
    const gallery=document.createElement('div');gallery.className='offline-photos';
    for(const e of entries){
      if(e.kind!=='file'&&!['annotate[]','signature_data'].includes(e.name))continue;
      const a=document.createElement('a'),img=document.createElement('img');
      const url=e.kind==='file'?URL.createObjectURL(e.blob):e.value;
      if(e.kind==='file')urls.push(url);
      img.src=url;img.alt=e.filename||'Saved '+(e.name==='signature_data'?'signature':'annotation');img.loading='lazy';
      a.href=url;a.download=e.filename||'saved-image.png';a.append(img);gallery.append(a);
    }
    if(gallery.childElementCount)node.append(gallery);
  }
  async function render(){
    if(rendering)return;rendering=true;
    try{
      if(owner&&(await db.meta('activeOwner'))!==owner)owner=null;
      if($('noticeForm'))$('noticeForm').closest('main').hidden=!owner;
      const items=owner?await db.noticeItems(owner):[];
      const pending=items.filter(i=>!['draft','synced'].includes(i.status)).length;
      const failed=items.filter(i=>['needs_auth','needs_attention'].includes(i.status)).length;
      status((navigator.onLine?'Online':'Offline')+' · '+pending+' queued'+(failed?' · '+failed+' need attention':''));
      if($('offlineOwner'))$('offlineOwner').textContent=owner?'Saved on this device for '+owner:'Sign in online first, then prepare this device for offline use.';
      if($('offlineEditor'))$('offlineEditor').hidden=!owner;
      const host=$('offlineItems');if(!host)return;
      for(const url of urls.splice(0))URL.revokeObjectURL(url);
      host.replaceChildren();
      if(!items.length)host.textContent='No saved drafts or queued notices on this device.';
      for(const item of items){
        const card=document.createElement('article');card.className='offline-item';
        const heading=document.createElement('h3');heading.textContent=(item.action==='close'?'Close notice #'+field(item,'notice_id'):(field(item,'site_name')||'Untitled notice'))+' · '+(item.status==='synced'?'Synced #'+item.receipt.id:item.status.replaceAll('_',' '));card.append(heading);
        const details=document.createElement('p');details.className='docs-muted';details.textContent=(field(item,'location')||'')+' · '+new Date(item.createdAt).toLocaleString('en-GB');card.append(details);
        if(item.lastError){const p=document.createElement('p');p.className='offline-error';p.textContent=item.lastError;card.append(p);}
        if(item.receipt&&!['sent','not_required'].includes(item.receipt.emailStatus)){const p=document.createElement('p');p.className='offline-error';p.textContent='Notice saved. Email '+item.receipt.emailStatus+'. Contact your administrator before attempting to send it again.';card.append(p);}
        const actions=document.createElement('div');actions.className='offline-actions';
        if(item.status==='draft')actions.append(button('Continue draft',()=>loadDraft(item)));
        if(item.status==='needs_attention'&&[400,413,422].includes(item.httpStatus))actions.append(button('Revise saved notice',async()=>{const copy={...item,id:db.id(),status:'draft',createdAt:Date.now(),lastError:'',attempts:0,nextTry:0};await db.put('items',copy);await loadDraft(copy);await render();}));
        if(!['draft','synced'].includes(item.status))actions.append(button('Retry sync',async()=>{item.nextTry=0;if(item.status==='needs_attention')item.status='pending';await db.put('items',item);await synchronise();}));
        if(item.pdf)actions.append(button('Download saved PDF',()=>download(item.pdf,'notice-'+item.receipt.id+'.pdf')));
        else if(item.status==='synced')actions.append(button('Save PDF for offline',async()=>{await db.downloadPdf(item);await render();}));
        if(item.action!=='close')actions.append(button('Download local PDF',()=>draftPdf(item)));
        actions.append(button('Remove from device',async()=>{const latest=await db.get('items',item.id);if(latest?.status==='uploading')throw new Error('This notice is currently uploading. Wait for confirmation before removing the device copy.');if(!confirm('Remove this notice and its photos from this device? '+(item.status==='synced'?'The server copy remains.':'Unsynced work will be lost.')))return;await db.remove('items',item.id);if(editorId===item.id){editorId=null;await db.setMeta('editor:'+owner,null);$('noticeForm')?.reset();window.resetNoticeCanvases?.();restoredFiles=[];$('annFields')?.replaceChildren();$('annThumbs')?.replaceChildren();$('photoThumbs')?.replaceChildren();}await render();}));
        card.append(actions);previews(card,item.entries);host.append(card);
      }
      await renderLibrary();
    }finally{rendering=false;}
  }
  async function renderLibrary(){
    const host=$('offlineLibrary');if(!host)return;host.replaceChildren();
    const saved=owner?(await db.all('library')).filter(row=>row.owner===owner):[];
    if(!saved.length)host.textContent='Connect and refresh to choose recent notices for offline use.';
    for(const row of saved){
      const card=document.createElement('article');card.className='offline-item';const h=document.createElement('h3');h.textContent='#'+row.notice.id+' · '+row.notice.site_name+' · '+(row.notice.status||'open');card.append(h);
      const p=document.createElement('p');p.textContent=row.notice.location+' · '+(row.notice.description||'');card.append(p);
      const stamp=document.createElement('p');stamp.className='docs-muted';stamp.textContent='Snapshot: '+new Date(row.refreshedAt).toLocaleString('en-GB')+(row.savedAt?' · Files saved: '+new Date(row.savedAt).toLocaleString('en-GB'):' · Files not yet saved');card.append(stamp);
      const actions=document.createElement('div');actions.className='offline-actions';actions.append(button(row.pdf?'Refresh PDF & photos':'Save PDF & photos for offline',async()=>{await saveNoticeAssets(row);await render();}));
      if(row.pdf)actions.append(button('Download saved PDF',()=>download(row.pdf,'notice-'+row.notice.id+'.pdf')));
      if(row.notice.status!=='closed')actions.append(button('Queue close-out',()=>queueClose(row.notice)));
      actions.append(button('Remove local copy',async()=>{if(confirm('Remove this downloaded copy? The server notice remains.')){await db.remove('library',row.key);await render();}}));card.append(actions);
      previews(card,(row.photos||[]).map((blob,i)=>({kind:'file',blob,filename:'notice-'+row.notice.id+'-photo-'+(i+1)+'.jpg'})));
      if(row.signature)previews(card,[{kind:'file',blob:row.signature,filename:'signature.jpg'}]);
      host.append(card);
    }
  }
  async function queueClose(notice){
    if(!owner||(await db.meta('activeOwner'))!==owner)throw new Error('Sign in to queue this close-out.');
    const duplicate=(await db.noticeItems(owner)).find(item=>item.action==='close'&&field(item,'notice_id')===String(notice.id)&&item.status!=='synced');
    if(duplicate){message('This close-out is already saved for synchronisation.');return;}
    if(!confirm('Queue notice #'+notice.id+' for close-out when connected?'))return;
    const item={id:db.id(),owner,action:'close',status:'pending',createdAt:Date.now(),entries:[{name:'notice_id',kind:'text',value:String(notice.id)},{name:'site_name',kind:'text',value:notice.site_name||''}],attempts:0,nextTry:0};
    await db.put('items',item);
    if(navigator.serviceWorker)navigator.serviceWorker.ready.then(reg=>reg.sync?.register('sync-site-documents')).catch(()=>{});
    message('Close-out saved on this device. It will be confirmed when connected.');await render();if(navigator.onLine)await synchronise();
  }
  async function saveNoticeAssets(row){
    if(!navigator.onLine)throw new Error('Reconnect to download files that are not saved yet.');
    const fresh=await db.context();if(fresh.owner!==owner)throw new Error('Sign in as '+owner+'.');
    message('Downloading PDF and photos for notice #'+row.notice.id+'…');
    const prefix='/api/offline_asset.php?id='+row.notice.id;
    const pdf=await db.binary(prefix+'&kind=pdf','application/pdf'),photos=[];
    for(const photo of row.notice.photoIds||[])photos.push(await db.binary(prefix+'&kind=photo&photo='+photo,'image/'));
    let signature=null;try{signature=await db.binary(prefix+'&kind=signature','image/');}catch{}
    if((await db.meta('activeOwner'))!==owner)throw new Error('The signed-in user changed.');
    await db.put('library',{...row,pdf,photos,signature,savedAt:Date.now(),fileVersion:JSON.stringify(row.notice)});message('Notice #'+row.notice.id+' and its files are saved for offline use.');
  }
  async function refreshNotices(downloadFiles=false){
    const response=await db.request('/api/offline_notices.php',{headers:{Accept:'application/json'}});
    const result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Unable to refresh notices.');
    if(result.owner!==owner)throw new Error('Sign in as '+owner+'.');
    for(const notice of result.notices){const key=owner+':'+notice.id,previous=await db.get('library',key);await db.put('library',{...previous,key,owner,notice,refreshedAt:Date.now()});}
    let failedDownloads=0;
    if(downloadFiles){
      for(const row of (await db.all('library')).filter(row=>row.owner===owner)){
        if(!row.pdf||row.fileVersion!==JSON.stringify(row.notice)){
          try{await saveNoticeAssets(row);}catch(error){failedDownloads++;message('Some files could not be downloaded yet: '+error.message);}
        }
      }
    }
    await render();
    return {failedDownloads};
  }
  async function prepare(){
    const wasPreparedUser=Boolean(owner);
    const fresh=await db.context();if(owner&&fresh.owner!==owner)throw new Error('Sign in as '+owner+'.');owner=fresh.owner;
    await db.setMeta('activeOwner',owner);await db.setMeta('context:'+owner,fresh);
    if(navigator.storage?.persist)await navigator.storage.persist();
    if(!('serviceWorker'in navigator))throw new Error('This browser cannot prepare the offline workspace.');
    const reg=await navigator.serviceWorker.register('/service-worker.js',{scope:'/'});
    await reg.update();
    if(reg.installing)await new Promise((resolve,reject)=>{const worker=reg.installing;worker.addEventListener('statechange',()=>{if(worker.state==='activated')resolve();if(worker.state==='redundant')reject(new Error('Offline files could not be installed.'));});});
    await navigator.serviceWorker.ready;
    if(!navigator.serviceWorker.controller){await new Promise(resolve=>{const timer=setTimeout(resolve,3000);navigator.serviceWorker.addEventListener('controllerchange',()=>{clearTimeout(timer);resolve();},{once:true});});}
    const response=await fetch('/field.html',{cache:'no-cache'});if(!response.ok)throw new Error('Unable to prepare the offline form.');
    await db.setMeta('prepared:'+owner,Date.now());
    const downloads=await refreshNotices(true);
    if(!wasPreparedUser){location.reload();return;}
    await render();if(downloads.failedDownloads){message('Offline form ready. '+downloads.failedDownloads+' existing notices still need files; refresh before leaving signal.');return;}message('Offline workspace ready. New notices, photos and signatures can now be saved without signal. Recent notices and their available files have been downloaded; check saved-file dates below.');
  }
  async function synchronise(){
    try{await db.sync();if(owner&&await db.meta('prepared:'+owner))await refreshNotices(true);await render();}catch(error){status(error.message);}
  }
  function capture(){
    const form=$('noticeForm');if(!form)return [];
    window.beforeSubmit?.();
    const entries=db.serialise(new FormData(form));
    if(!entries.some(e=>e.kind==='file'&&e.name==='photos[]'))entries.push(...restoredFiles);
    return entries;
  }
  function autosave(){
    if(!owner||submitting)return;if($('formSaveStatus'))$('formSaveStatus').textContent='Saving draft…';clearTimeout(saveTimer);
    saveTimer=setTimeout(()=>{saveChain=saveChain.then(()=>saveDraft()).catch(error=>message('Device save failed: '+error.message+'. Keep this page open.'));},500);
  }
  async function saveDraft(){
    if(!owner||submitting)return;
    if((await db.meta('activeOwner'))!==owner)throw new Error('The signed-in account changed. Reopen the workspace.');
    editorId=editorId||db.id();
    const previous=await db.get('items',editorId);
    if(previous&&previous.status!=='draft')editorId=db.id();
    await db.put('items',{id:editorId,owner,status:'draft',createdAt:previous?.createdAt||Date.now(),updatedAt:Date.now(),entries:capture(),annotation:$('annCanvas')?.toDataURL('image/png')});
    await db.setMeta('editor:'+owner,editorId);
    if($('formSaveStatus'))$('formSaveStatus').textContent='Draft saved on this device · '+new Date().toLocaleTimeString('en-GB');
  }
  async function loadDraft(item){
    if(item.owner!==owner||item.status!=='draft')return;const form=$('noticeForm');if(!form)return;
    submitting=true;form.reset();$('annFields').replaceChildren();$('annThumbs').replaceChildren();$('photoThumbs').replaceChildren();window.clearSignature?.();
    for(const e of item.entries){if(e.kind==='file')continue;const control=form.elements.namedItem(e.name);
      if(e.name==='recipients[]'){const sel=$('recipients');let option=[...sel.options].find(o=>o.value===e.value);if(!option){option=new Option(e.value,e.value);sel.add(option);}option.selected=true;}
      else if(e.name==='annotate[]'){const input=document.createElement('input');input.type='hidden';input.name='annotate[]';input.value=e.value;$('annFields').append(input);const img=new Image();img.src=e.value;$('annThumbs').append(img);}
      else if(control&&'value'in control)control.value=e.value;
    }
    restoredFiles=item.entries.filter(e=>e.kind==='file');
    for(const e of restoredFiles){const img=new Image();const url=URL.createObjectURL(e.blob);img.src=url;img.onload=()=>URL.revokeObjectURL(url);$('photoThumbs').append(img);}
    function restoreCanvas(canvas,data){return new Promise(resolve=>{if(!canvas||!data){resolve();return;}const img=new Image();img.onload=()=>{canvas.getContext('2d').drawImage(img,0,0,canvas.width,canvas.height);resolve();};img.onerror=resolve;img.src=data;});}
    await restoreCanvas($('sigCanvas'),field(item,'signature_data'));await restoreCanvas($('annCanvas'),item.annotation);
    editorId=item.id;await db.setMeta('editor:'+owner,editorId);submitting=false;
    message('Saved draft restored, including its photos and signature.');form.scrollIntoView({behavior:'smooth',block:'start'});
  }
  async function submit(event){
    event.preventDefault();if(submitting)return;
    const form=$('noticeForm');if(!form.reportValidity())return;
    if(!owner){message('Sign in online and prepare this device before saving offline notices.');return;}
    if((await db.meta('activeOwner'))!==owner){message('The signed-in account changed. Reopen the workspace.');return;}
    submitting=true;clearTimeout(saveTimer);const submitButton=form.querySelector('[type="submit"]');submitButton.disabled=true;
    try{
      await saveChain;let uuid=editorId||db.id();const previous=await db.get('items',uuid);if(previous&&previous.status!=='draft')uuid=db.id();
      const item={id:uuid,owner,status:'pending',createdAt:previous?.createdAt||Date.now(),updatedAt:Date.now(),entries:capture(),attempts:0,nextTry:0};
      await db.put('items',item);await db.setMeta('editor:'+owner,null);editorId=null;restoredFiles=[];
      form.reset();window.resetNoticeCanvases?.();$('annFields').replaceChildren();$('annThumbs').replaceChildren();$('photoThumbs').replaceChildren();
      message('Notice, photos and signature saved on this device. Waiting for confirmed upload.');
      if(navigator.serviceWorker)navigator.serviceWorker.ready.then(registration=>registration.sync?.register('sync-site-documents')).catch(()=>{});
      await render();if(navigator.onLine)await synchronise();
    }catch(error){message('Could not save this notice: '+error.message+'. Your form is still here; do not close it.');}
    finally{submitting=false;submitButton.disabled=false;}
  }
  async function boot(){
    $('noticeForm')?.addEventListener('submit',submit);
    try{
      if(await db.meta('logoutPending')){if(navigator.onLine){await fetch('/logout.php',{credentials:'same-origin'});await db.setMeta('logoutPending',false);location.href='/index.php';return;}}
      if(!('indexedDB'in window))throw new Error('This browser cannot store offline work.');
      if(Object.prototype.hasOwnProperty.call(window,'DOCS_OFFLINE_USER')){
        owner=window.DOCS_OFFLINE_USER;await db.setMeta('activeOwner',owner);sessionChannel?.postMessage({owner});
      }else owner=await db.meta('activeOwner');
      if(owner){
        if(!navigator.onLine&&!await db.meta('prepared:'+owner))status('Prepare offline use while connected before relying on this device.');
        if(navigator.onLine)try{const fresh=await db.context();if(fresh.owner===owner)await db.setMeta('context:'+owner,fresh);}catch{}
        const form=$('noticeForm');if(form){form.addEventListener('input',autosave);form.addEventListener('change',event=>{if(event.target.id==='photos')restoredFiles=[];autosave();});
          form.addEventListener('pointerup',autosave);form.addEventListener('touchend',autosave);
          const draftId=await db.meta('editor:'+owner),draft=draftId?await db.get('items',draftId):null;if(draft?.status==='draft')await loadDraft(draft);
        }
      }
      $('saveDraftNow')?.addEventListener('click',()=>{clearTimeout(saveTimer);saveChain=saveChain.then(()=>saveDraft()).catch(error=>message('Draft not saved: '+error.message));});
      $('prepareOffline')?.addEventListener('click',()=>prepare().catch(error=>message(error.message)));
      $('syncNow')?.addEventListener('click',synchronise);
      $('refreshNotices')?.addEventListener('click',()=>refreshNotices(true).catch(error=>message(error.message)));
      for(const a of document.querySelectorAll('a[href="/logout.php"]'))a.addEventListener('click',event=>{event.preventDefault();db.setMeta('activeOwner',null).then(()=>{sessionChannel?.postMessage({owner:null});location.href='/logout.php';});});
      await render();
      if(owner&&navigator.onLine)await synchronise();
      window.addEventListener('online',synchronise);window.addEventListener('offline',render);
      document.addEventListener('visibilitychange',()=>{if(document.hidden&&$('noticeForm')&&!submitting){clearTimeout(saveTimer);saveChain=saveChain.then(()=>saveDraft()).catch(()=>{});}if(!document.hidden){render();if(navigator.onLine)synchronise();}});
      navigator.serviceWorker?.addEventListener('message',event=>{if(event.data?.type==='notice-sync-status')render();});
      setInterval(()=>{if(owner&&navigator.onLine&&!document.hidden)synchronise();},30000);
    }catch(error){message('Offline storage unavailable: '+error.message+'. Do not close unsaved work.');}
  }
  document.addEventListener('DOMContentLoaded',boot,{once:true});
})();
