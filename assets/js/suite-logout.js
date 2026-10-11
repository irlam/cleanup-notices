/* Keep Suite sign-out from clearing unfinished notice drafts and attachments. */
document.addEventListener('submit', async event => {
  const form=event.target;
  if(!(form instanceof HTMLFormElement)||!form.matches('form[data-suite-logout]'))return;
  event.preventDefault();
  try {
    if(!navigator.onLine)throw new Error('Reconnect before signing out. Your saved work remains on this device.');
    if(typeof window.DocsBeforeLogout==='function')await window.DocsBeforeLogout();
    if(!window.DocsOffline)throw new Error('Device storage is still opening. Please retry.');
    const owner=await DocsOffline.meta('activeOwner');
    const pending=(await DocsOffline.all('items')).filter(item=>item.owner===owner&&item.status!=='synced');
    if(pending.length)throw new Error('You have '+pending.length+' unsynchronised notice(s). Open Offline, finish and sync them before signing out. Your drafts and photos are still saved.');
    HTMLFormElement.prototype.submit.call(form);
  } catch(error) {
    let notice=document.getElementById('suite-logout-status');
    if(!notice){notice=document.createElement('p');notice.id='suite-logout-status';notice.setAttribute('role','alert');notice.style.cssText='padding:16px;background:#422006;color:#fde68a;margin:0';form.closest('div').after(notice);}
    notice.textContent=error.message+' ';
    const link=document.createElement('a');link.href='/field.html';link.textContent='Open Offline workspace';link.style.color='#67e8f9';notice.append(link);
  }
},true);
document.addEventListener('click',event=>{
  const link=event.target.closest('a');
  if(link&&new URL(link.href,location.href).pathname==='/logout.php'){
    const form=document.querySelector('form[data-suite-logout]');
    if(form){event.preventDefault();event.stopImmediatePropagation();form.requestSubmit();}
  }
},true);
