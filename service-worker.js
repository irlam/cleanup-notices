'use strict';
importScripts('/assets/js/offline-store.js');
const CACHE='site-documents-offline-shell-v3';
const SHELL=[
 '/offline.html','/field.html','/assets/css/admin.css','/assets/css/utilities.css',
 '/assets/css/notice-form.css','/assets/js/offline-store.js','/assets/js/offline-app.js',
 '/assets/js/notice-form.js','/assets/js/pwa.js','/assets/vendor/jspdf.umd.min.js',
 '/assets/brand/site-documents-logo.png','/assets/icons/icon-32.png','/assets/icons/icon-96.png',
 '/assets/icons/icon-180.png','/assets/icons/icon-192.png','/assets/icons/icon-512.png',
 '/assets/icons/maskable-192.png','/assets/icons/maskable-512.png','/manifest.webmanifest'
];
self.addEventListener('install',event=>event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(SHELL)).then(()=>self.skipWaiting())));
self.addEventListener('activate',event=>event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(key=>
 (key.startsWith('site-documents-public-')||key.startsWith('site-documents-offline-shell-'))&&key!==CACHE).map(key=>caches.delete(key)))).then(()=>self.clients.claim())));
async function savedPdf(url,owner){
 const id=Number(url.searchParams.get('id'));if(!owner||!id)return null;
 const headers={'Content-Type':'application/pdf','Content-Disposition':'attachment; filename="clean-up-notice-'+id+'.pdf"'};
 const downloaded=await DocsOffline.get('library',owner+':'+id);
 if(downloaded?.pdf)return new Response(downloaded.pdf,{headers});
 const submitted=(await DocsOffline.noticeItems(owner)).find(item=>item.receipt?.id===id&&item.pdf);
 return submitted?new Response(submitted.pdf,{headers}):null;
}
async function offlineNavigation(url){
 const owner=await DocsOffline.meta('activeOwner');
 if(owner&&['/forms/clean-up/pdf.php','/api/offline_asset.php'].includes(url.pathname)){
  const pdf=await savedPdf(url,owner);if(pdf)return pdf;
 }
 // Never reuse session-rendered pages across accounts. The generic workspace
 // reads only the active account's IndexedDB records.
 return await caches.match(owner?'/field.html':'/offline.html')||new Response('Reconnect to prepare offline use.',{status:503,headers:{'Content-Type':'text/plain'}});
}
self.addEventListener('fetch',event=>{
 const request=event.request,url=new URL(request.url);
 if(request.method!=='GET'||url.origin!==self.location.origin)return;
 if(url.pathname==='/logout.php'){
  event.respondWith((async()=>{await DocsOffline.setMeta('activeOwner',null);await DocsOffline.setMeta('logoutPending',true);
   try{const response=await fetch(request);if(response.ok)await DocsOffline.setMeta('logoutPending',false);return response;}catch{return offlineNavigation(url);}})());return;
 }
 if(SHELL.includes(url.pathname)){
  event.respondWith(caches.match(url.pathname).then(cached=>cached||fetch(request)));return;
 }
 if(request.mode==='navigate'){
  event.respondWith(fetch(request).catch(()=>offlineNavigation(url)));return;
 }
 // API calls, PDFs, uploads and submissions stay on the network. The app stores
 // explicit authenticated snapshots and attachment Blobs in account-scoped IDB.
});
self.addEventListener('sync',event=>{
 if(event.tag!=='sync-site-documents')return;
 event.waitUntil((async()=>{
  if(await DocsOffline.meta('logoutPending'))return;
  try{const result=await DocsOffline.sync();if(result.remaining)throw new Error('Notices still awaiting connection.');}
  finally{const clients=await self.clients.matchAll({type:'window'});for(const client of clients)client.postMessage({type:'notice-sync-status'});}
 })());
});
