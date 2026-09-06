(() => {
  const table=[...document.querySelectorAll('.data-table')].find(t=>t.closest('.section-block')?.querySelector('h2')?.textContent.trim()==='Multi-tier pricing');
  const form=table?.closest('.section-block')?.querySelector('form');
  if(!table||!form)return;
  const tbody=table.querySelector('tbody');
  const head=table.querySelector('thead tr');
  if(head&&!head.querySelector('[data-product-actions]')){const th=document.createElement('th');th.dataset.productActions='1';th.textContent='Action';head.appendChild(th);}
  const fields=['id','category','provider','name','code','cost_price','retail_price','reseller_price','api_price','face_value','validity','plan_type'];
  const submit=form.querySelector('button[type="submit"],button:not([type])');
  if(submit){submit.dataset.originalText=submit.textContent;}
  const cancel=document.createElement('button');cancel.type='button';cancel.className='btn btn-ghost';cancel.textContent='Cancel edit';cancel.style.display='none';cancel.style.marginLeft='8px';submit?.after(cancel);
  const reset=()=>{form.reset();form.querySelector('[name="id"]').value='';if(submit)submit.textContent='Add product';cancel.style.display='none';form.querySelector('[name="active"]').checked=true;};
  cancel.addEventListener('click',reset);
  [...tbody.querySelectorAll('tr')].forEach(row=>{
    const cells=row.children;if(cells.length<8)return;
    const product=cells[0].querySelector('b')?.textContent.trim()||'';
    const code=cells[0].querySelector('small')?.textContent.trim()||'';
    const providerGuess=product.split(' ')[0]||'';
    const nameGuess=product.slice(providerGuess.length).trim();
    const money=n=>Number((n||'').replace(/[^0-9.-]/g,''))||0;
    const td=document.createElement('td');
    const btn=document.createElement('button');btn.type='button';btn.className='btn btn-ghost';btn.textContent='Edit';
    btn.addEventListener('click',()=>{
      // Locate the product by code from the server-rendered catalogue data embedded below.
      const p=(window.IH_PRODUCT_CATALOG||[]).find(x=>String(x.code)===code);
      if(!p){alert('Unable to load this product for editing. Refresh the page and try again.');return;}
      fields.forEach(k=>{const input=form.querySelector(`[name="${k}"]`);if(input&&k in p)input.value=p[k]??'';});
      const active=form.querySelector('[name="active"]');if(active)active.checked=Number(p.active)===1;
      if(submit)submit.textContent='Save changes';cancel.style.display='inline-flex';
      form.scrollIntoView({behavior:'smooth',block:'start'});form.querySelector('[name="retail_price"]')?.focus();
    });
    td.appendChild(btn);row.appendChild(td);
  });
})();
