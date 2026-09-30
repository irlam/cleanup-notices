'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),crypto=require('node:crypto');
const {IDBFactory}=require('fake-indexeddb');
const source=fs.readFileSync(require('node:path').join(__dirname,'../assets/js/offline-store.js'),'utf8');
function environment({indexedDB=new IDBFactory(),fetch}={}){
 const sandbox={indexedDB,crypto:crypto.webcrypto,Blob,FormData,AbortController,fetch,setTimeout,clearTimeout,setInterval,clearInterval,Date,console};sandbox.self=sandbox;
 vm.createContext(sandbox);vm.runInContext(source,sandbox);return {db:sandbox.DocsOffline,indexedDB};
}
function server(){
 let posts=0,records=0,owner='irlam',drop=false,pdfFailure=false,validation=false;
 const receipts=new Map();
 const fetch=async(url,options={})=>{
  if(url.includes('offline_context'))return new Response(JSON.stringify({success:true,owner,csrfToken:'fresh-token',recipients:[]}),{headers:{'Content-Type':'application/json'}});
  if(url.includes('offline_asset'))return pdfFailure?new Response('Unavailable',{status:503}):new Response('%PDF-1.4\nserver copy',{headers:{'Content-Type':'application/pdf'}});
  if(url.includes('submit.php')){
   posts++;const form=options.body;assert.equal(form.get('csrf_token'),'fresh-token');assert.equal(form.get('offline_owner'),'irlam');assert.equal(form.get('offline_photo_count'),'1');
   if(validation)return new Response(JSON.stringify({success:false,message:'Photo rejected'}),{status:422});
   const id=form.get('client_submission_id');if(!receipts.has(id)){records++;receipts.set(id,{success:true,id:records,pdfUrl:'/api/offline_asset.php?id='+records+'&kind=pdf',emailStatus:'sent'});}
   if(drop){drop=false;throw new TypeError('Connection lost after server commit');}
   return new Response(JSON.stringify(receipts.get(id)),{headers:{'Content-Type':'application/json'}});
  }
  throw new Error('Unexpected URL: '+url);
 };
 return {fetch,get posts(){return posts;},get records(){return records;},owner(value){owner=value;},drop(){drop=true;},pdfFailure(value){pdfFailure=value;},validation(value){validation=value;}};
}
async function queue(db){
 await db.setMeta('activeOwner','irlam');
 const data=new FormData();data.append('site_name','Mobile site');data.append('description','Offline test');data.append('csrf_token','expired-token');data.append('photos[]',new Blob(['original-photo-bytes'],{type:'image/jpeg'}),'photo.jpg');data.append('signature_data','data:image/png;base64,signature');data.append('annotate[]','data:image/png;base64,annotation');
 const row={id:db.id(),owner:'irlam',status:'pending',createdAt:Date.now(),entries:db.serialise(data),attempts:0};await db.put('items',row);return row;
}
test('attachments survive reopening storage and lost acknowledgement retries reuse the UUID',async()=>{
 const endpoint=server(),a=environment({fetch:endpoint.fetch}),row=await queue(a.db);
 const b=environment({indexedDB:a.indexedDB,fetch:endpoint.fetch});const restored=await b.db.get('items',row.id);assert.equal(await restored.entries.find(e=>e.kind==='file').blob.text(),'original-photo-bytes');
 endpoint.drop();await b.db.sync();assert.equal(endpoint.records,1);assert.equal((await b.db.get('items',row.id)).status,'pending');
 const pending=await b.db.get('items',row.id);pending.nextTry=0;await b.db.put('items',pending);await b.db.sync();
 const synced=await b.db.get('items',row.id);assert.equal(synced.status,'synced');assert.equal(endpoint.records,1);assert.equal(endpoint.posts,2);assert.equal(await synced.pdf.text(),'%PDF-1.4\nserver copy');
});
test('switching accounts cannot upload another author’s queue',async()=>{
 const endpoint=server(),{db}=environment({fetch:endpoint.fetch}),row=await queue(db);endpoint.owner('another-user');await assert.rejects(db.sync(),/Sign in as irlam/);assert.equal(endpoint.posts,0);assert.equal((await db.get('items',row.id)).status,'pending');
 await db.setMeta('activeOwner',null);assert.equal((await db.sync()).locked,true);
});
test('confirmed receipts prevent re-submission when only the PDF download fails',async()=>{
 const endpoint=server(),{db}=environment({fetch:endpoint.fetch}),row=await queue(db);endpoint.pdfFailure(true);await db.sync();assert.equal((await db.get('items',row.id)).status,'synced');assert.equal(endpoint.posts,1);
 endpoint.pdfFailure(false);await db.sync();assert.equal(endpoint.posts,1);assert((await db.get('items',row.id)).pdf);
});
test('validation failures keep all attachments and require attention rather than silent retries',async()=>{
 const endpoint=server(),{db}=environment({fetch:endpoint.fetch}),row=await queue(db);endpoint.validation(true);await db.sync();const saved=await db.get('items',row.id);assert.equal(saved.status,'needs_attention');assert.equal(saved.httpStatus,422);assert.equal(await saved.entries.find(e=>e.kind==='file').blob.text(),'original-photo-bytes');await db.sync();assert.equal(endpoint.posts,1);
});
test('two tabs share a lease and cannot drain the outbox concurrently',async()=>{
 const endpoint=server();let release;const gate=new Promise(resolve=>release=resolve);let entered;const started=new Promise(resolve=>entered=resolve);
 const delayed=async(url,options)=>{if(url.includes('offline_context')){entered();await gate;}return endpoint.fetch(url,options);};
 const a=environment({fetch:delayed});await queue(a.db);const b=environment({indexedDB:a.indexedDB,fetch:endpoint.fetch});const running=a.db.sync();await started;assert.equal((await b.db.sync()).busy,true);release();await running;assert.equal(endpoint.posts,1);
});
test('offline close-outs use their own endpoint and keep the same receipt ID across retry',async()=>{
 let attempts=0,closed=false;const ids=[];
 const fetch=async(url,options={})=>{
  if(url.includes('offline_context'))return new Response(JSON.stringify({success:true,owner:'irlam',csrfToken:'fresh-token'}));
  if(url.includes('offline_asset'))return new Response('%PDF-1.4\nclosed notice',{headers:{'Content-Type':'application/pdf'}});
  assert.equal(url,'/api/offline_close.php');assert.equal(options.body.get('notice_id'),'7');assert.equal(options.body.get('offline_owner'),'irlam');ids.push(options.body.get('client_submission_id'));attempts++;closed=true;
  if(attempts===1)throw new TypeError('Lost close-out acknowledgement');
  return new Response(JSON.stringify({success:true,id:7,pdfUrl:'/api/offline_asset.php?id=7&kind=pdf',emailStatus:'not_required'}));
 };
 const {db}=environment({fetch});await db.setMeta('activeOwner','irlam');const row={id:db.id(),owner:'irlam',action:'close',status:'pending',createdAt:Date.now(),entries:[{name:'notice_id',kind:'text',value:'7'}]};await db.put('items',row);await db.sync();assert(closed);const waiting=await db.get('items',row.id);waiting.nextTry=0;await db.put('items',waiting);await db.sync();assert.equal(ids[0],ids[1]);assert.equal((await db.get('items',row.id)).status,'synced');
});
