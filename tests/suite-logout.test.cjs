const test=require('node:test'),assert=require('node:assert/strict'),vm=require('node:vm'),fs=require('node:fs');
const source=fs.readFileSync(require('node:path').join(__dirname,'../assets/js/suite-logout.js'),'utf8');
function env(items,online=true){
 const handlers={},alerts=[];let submitted=0,saved=0;
 class Form {matches(){return true;}closest(){return {after(node){alerts.push(node);}};}submit(){submitted++;}requestSubmit(){return handlers.submit({target:this,preventDefault(){}});}}
 const form=new Form();
 const document={addEventListener(type,fn){handlers[type]=fn;},getElementById(){return null;},querySelector(){return form;},createElement(){return {setAttribute(){},style:{},append(){}};}};
 const db={meta:async()=> 'test-owner',all:async()=>items};
 const window={DocsOffline:db,DocsBeforeLogout:async()=>{saved++;}};
 vm.runInNewContext(source,{document,window,DocsOffline:db,HTMLFormElement:Form,navigator:{onLine:online},URL,location:{href:'https://beta.sitenotices.site/dashboard.php'}});
 return {handlers,form,alerts,get submitted(){return submitted;},get saved(){return saved;}};
}
test('Suite sign-out flushes the editor and preserves unfinished own drafts',async()=>{const e=env([{owner:'test-owner',status:'draft'},{owner:'another',status:'synced'}]);await e.form.requestSubmit();assert.equal(e.saved,1);assert.equal(e.submitted,0);assert.match(e.alerts[0].textContent,/1 unsynchronised/);});
test('confirmed records and a different account draft do not block sign-out',async()=>{const e=env([{owner:'test-owner',status:'synced'},{owner:'another',status:'pending'}]);await e.form.requestSubmit();assert.equal(e.submitted,1);});
test('offline sign-out never clears device work',async()=>{const e=env([],false);await e.form.requestSubmit();assert.equal(e.submitted,0);assert.match(e.alerts[0].textContent,/Reconnect/);});
test('native logout links use the protected Suite form',async()=>{const e=env([{owner:'test-owner',status:'needs_attention'}]);let prevented=false;await e.handlers.click({target:{closest(){return {href:'/logout.php'};}},preventDefault(){prevented=true;},stopImmediatePropagation(){}});await new Promise(r=>setImmediate(r));assert(prevented);assert.equal(e.submitted,0);assert.equal(e.alerts.length,1);});
