const http=require('http'),fs=require('fs'),path=require('path'),assert=require('assert');
const {chromium}=require('playwright');
async function poll(page,fn){for(let i=0;i<200;i++){if(await page.evaluate(fn))return;await new Promise(r=>setTimeout(r,150));}throw new Error('Async condition not met');}
const root=path.resolve(__dirname,'..');
const artifacts=fs.mkdtempSync(path.join(require('os').tmpdir(),'docs-offline-check-'));
let serverOwner='irlam',loseReply=false,uploads=0;const receipts=new Map();
const pdf=Buffer.from('%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF');
const png=fs.readFileSync(root+'/assets/icons/icon-96.png');
function json(res,value,status=200){res.writeHead(status,{'Content-Type':'application/json','Cache-Control':'no-store'});res.end(JSON.stringify(value));}
const server=http.createServer((req,res)=>{
 const url=new URL(req.url,'http://localhost');
 if(url.pathname==='/api/offline_context.php'){return json(res,{success:true,owner:serverOwner,csrfToken:'test-token',recipients:['recipient@example.test']});}
 if(url.pathname==='/api/offline_notices.php')return json(res,{success:true,owner:serverOwner,notices:[{id:7,site_name:'Existing site',location:'Block A',description:'Saved existing notice',status:'open',photoIds:[1]}]});
 if(url.pathname==='/api/offline_asset.php'){res.writeHead(200,{'Content-Type':url.searchParams.get('kind')==='pdf'?'application/pdf':'image/png'});return res.end(url.searchParams.get('kind')==='pdf'?pdf:png);}
 if(url.pathname==='/api/offline_close.php'){const chunks=[];req.on('data',chunk=>chunks.push(chunk));req.on('end',()=>{const body=Buffer.concat(chunks).toString('utf8');assert(body.includes('name="notice_id"'));json(res,{success:true,id:7,pdfUrl:'/api/offline_asset.php?id=7&kind=pdf',emailStatus:'not_required',recipientCount:0});});return;}
 if(url.pathname==='/forms/clean-up/submit.php'){
  const chunks=[];req.on('data',chunk=>chunks.push(chunk));req.on('end',()=>{
   const body=Buffer.concat(chunks).toString('utf8');
   const value=name=>body.match(new RegExp('name="'+name+'"\\r\\n\\r\\n([^\\r]+)'))?.[1];
   const uuid=value('client_submission_id'),owner=value('offline_owner');
   if(owner!==serverOwner)return json(res,{success:false,message:'Wrong user'},403);
   assert(uuid);assert(body.includes('photo.jpg'));assert(body.includes('signature_data'));
   if(!receipts.has(uuid)){uploads++;receipts.set(uuid,{success:true,id:100+uploads,pdfUrl:'/api/offline_asset.php?id='+(100+uploads)+'&kind=pdf',emailStatus:'sent',recipientCount:1});}
   if(loseReply){loseReply=false;json(res,{success:false,message:'Server committed the notice, but acknowledgement was unavailable.'},503);return;}json(res,receipts.get(uuid));
  });return;
 }
 if(url.pathname==='/logout.php'){serverOwner=null;res.writeHead(302,{Location:'/index.php'});res.end();return;}
 if(['/dashboard.php','/index.php'].includes(url.pathname)){
  let html=fs.readFileSync(root+'/field.html','utf8').replace('<head>','<head><script>window.DOCS_OFFLINE_USER='+JSON.stringify(serverOwner)+'</script>');res.writeHead(200,{'Content-Type':'text/html'});res.end(html);return;
 }
 const file=path.join(root,url.pathname);if(!file.startsWith(root)||!fs.existsSync(file)||fs.statSync(file).isDirectory()){res.writeHead(404);res.end();return;}
 const types={'.html':'text/html','.js':'application/javascript','.css':'text/css','.png':'image/png','.webmanifest':'application/manifest+json'};
 res.writeHead(200,{'Content-Type':types[path.extname(file)]||'application/octet-stream'});fs.createReadStream(file).pipe(res);
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const base='http://127.0.0.1:'+server.address().port;
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||chromium.executablePath(),args:['--no-sandbox','--disable-dev-shm-usage'],headless:true});
 try{
  const context=await browser.newContext({viewport:{width:390,height:844}});const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/dashboard.php');await page.waitForFunction(()=>document.getElementById('offlineOwner').textContent.includes('irlam'));
  await page.getByRole('button',{name:'Prepare for offline use',exact:true}).click();
  await page.waitForFunction(()=>document.getElementById('offlineFeedback').textContent.includes('Offline workspace ready'));
  await page.getByRole('button',{name:'Refresh recent notices',exact:true}).click();await page.getByRole('button',{name:'Refresh PDF & photos',exact:true}).click();
  await page.waitForFunction(()=>document.getElementById('offlineFeedback').textContent.includes('files are saved'));
  await context.setOffline(true);await page.goto(base+'/forms/clean-up/form.php');await page.waitForSelector('#noticeForm');
  assert(await page.locator('#offlineOwner').textContent().then(t=>t.includes('irlam')));
  await page.locator('#site_name').fill('Offline mobile site');await page.locator('#location').fill('Floor 2');await page.locator('#issued_at').fill('2026-09-30T16:00');await page.locator('#issued_to').fill('Subcontractor');await page.locator('#issued_by').fill('Chris');await page.locator('#description').fill('Clean the work area');await page.locator('#urgency').selectOption('Within 24 hours');await page.locator('#completed_ok').selectOption('no');await page.locator('#mcgoff_clear').selectOption('no');await page.locator('#extra_email').fill('recipient@example.test');await page.getByRole('button',{name:'+ Add Email',exact:true}).click();
  await page.locator('#photos').setInputFiles({name:'photo.jpg',mimeType:'image/jpeg',buffer:png});
  await page.locator('#sigCanvas').evaluate(c=>{const x=c.getContext('2d');x.beginPath();x.moveTo(20,20);x.lineTo(100,75);x.stroke();c.dispatchEvent(new Event('pointerup',{bubbles:true}));});
  await page.waitForFunction(()=>document.getElementById('formSaveStatus').textContent.includes('Draft saved'));
  await page.reload();await page.waitForFunction(()=>document.getElementById('site_name').value==='Offline mobile site');
  assert.equal(await page.evaluate(async()=>{const rows=await DocsOffline.noticeItems('irlam');return rows.find(r=>r.status==='draft').entries.filter(e=>e.kind==='file').length;}),1);
  await page.getByRole('button',{name:'Submit Notice',exact:true}).click();
  await poll(page,async()=>{const rows=await DocsOffline.noticeItems('irlam');return rows.some(r=>r.status==='pending');});
  await page.reload();await page.waitForFunction(()=>document.getElementById('offlineItems').textContent.includes('pending'));
  assert.equal(uploads,0);await page.screenshot({path:path.join(artifacts,'docs-offline-mobile.png'),fullPage:true});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  await page.screenshot({path:path.join(artifacts,'docs-offline-mobile.png'),fullPage:true});
  loseReply=true;await context.setOffline(false);await page.evaluate(async()=>{await DocsOffline.sync();});
  await poll(page,async()=>{await DocsOffline.sync();const r=(await DocsOffline.noticeItems('irlam')).find(r=>r.status==='pending');return r&&r.attempts>0;});

  assert.equal(uploads,1);
  await page.evaluate(async()=>{for(const item of await DocsOffline.noticeItems('irlam')){item.nextTry=0;await DocsOffline.put('items',item);}await DocsOffline.sync();});
  const state=await page.evaluate(async()=>{const r=(await DocsOffline.noticeItems('irlam')).find(r=>r.status==='synced');return {status:r?.status,pdfSize:r?.pdf?.size,fileCount:r?.entries.filter(e=>e.kind==='file').length};});
  assert.equal(state.status,'synced');assert(state.pdfSize>20);assert.equal(state.fileCount,1);assert.equal(uploads,1);
  await context.setOffline(true);const savedEvent=page.waitForEvent('download');await page.goto(base+'/forms/clean-up/pdf.php?id=101').catch(error=>{if(!error.message.includes('Download is starting'))throw error;});const saved=await savedEvent;await saved.saveAs(path.join(artifacts,'docs-cached-offline.pdf'));assert(fs.readFileSync(path.join(artifacts,'docs-cached-offline.pdf')).subarray(0,5).equals(Buffer.from('%PDF-')));
  await page.goto(base+'/field.html');await page.waitForFunction(()=>document.getElementById('offlineItems').textContent.includes('Synced #101'));
  const downloadEvent=page.waitForEvent('download');await page.getByRole('button',{name:'Download local PDF',exact:true}).click();const dl=await downloadEvent;await dl.saveAs(path.join(artifacts,'docs-local-offline.pdf'));assert(fs.readFileSync(path.join(artifacts,'docs-local-offline.pdf')).subarray(0,5).equals(Buffer.from('%PDF-')));
  page.once('dialog',dialog=>dialog.accept());await page.getByRole('button',{name:'Queue close-out',exact:true}).click();await poll(page,async()=>{const items=await DocsOffline.noticeItems('irlam');return items.some(item=>item.action==='close'&&item.status==='pending');});
  await context.setOffline(false);await page.evaluate(async()=>{await DocsOffline.sync();});await poll(page,async()=>{await DocsOffline.sync();const items=await DocsOffline.noticeItems('irlam');return items.some(item=>item.action==='close'&&item.status==='synced');});
  await context.setOffline(false);serverOwner='another-user';await page.goto(base+'/dashboard.php');await page.waitForFunction(()=>document.getElementById('offlineOwner').textContent.includes('another-user'));assert(!(await page.locator('#offlineItems').textContent()).includes('Offline mobile site'));assert(!(await page.locator('#offlineLibrary').textContent()).includes('Existing site'));
  assert.deepEqual(errors,[]);console.log('Browser checks passed: mobile layout, offline reopening, autosaved draft/photos/signature, queued submission, lost acknowledgement retry without duplication, cached PDF, offline PDF generation, queued close-out, account isolation.');
 }finally{await browser.close();server.close();}
})().catch(error=>{console.error(error);server.close();process.exitCode=1;});
