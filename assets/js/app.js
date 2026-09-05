(() => {
  if (!document.querySelector('link[data-ihlink-layout-fix]')) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = '/assets/css/layout-fix.css?v=1';
    link.dataset.ihlinkLayoutFix = '1';
    document.head.appendChild(link);
  }

  const qs=(s,c=document)=>c.querySelector(s), qsa=(s,c=document)=>[...c.querySelectorAll(s)];

  const sidebarNav = qs('.sidebar-nav');
  if (sidebarNav && !qs('a[href="/upgrade.php"]', sidebarNav)) {
    const upgrade = document.createElement('a');
    upgrade.href = '/upgrade.php';
    upgrade.className = 'nav-item';
    upgrade.innerHTML = '<span>Upgrade Account</span>';
    sidebarNav.appendChild(upgrade);
  }
  const hasAdmin = !!qs('a[href="?page=admin"]', sidebarNav || document);
  if (sidebarNav && hasAdmin && !qs('a[href="/admin-control.php"]', sidebarNav)) {
    const control = document.createElement('a');
    control.href = '/admin-control.php';
    control.className = 'nav-item';
    control.innerHTML = '<span>Tier Management</span>';
    sidebarNav.appendChild(control);
  }

  // The old profile form allowed direct self-promotion. Replace it with the
  // administrator-reviewed upgrade flow while leaving the rest of the profile intact.
  const legacyRoleAction = qs('input[name="action"][value="change_role"]');
  const legacyRoleForm = legacyRoleAction?.closest('form');
  if (legacyRoleForm) {
    const box = document.createElement('div');
    box.innerHTML = '<div class="notice"><span>↗</span><div><b>Tier changes require approval</b><br><small>Request Premium, Reseller or API access from the upgrade page.</small><br><br><a class="btn btn-primary" href="/upgrade.php">Request an upgrade →</a></div></div>';
    legacyRoleForm.replaceWith(box);
  }

  const sidebar=qs('#sidebar'), overlay=qs('[data-sidebar-overlay]');
  qs('[data-sidebar-open]')?.addEventListener('click',()=>{sidebar?.classList.add('open');overlay?.classList.add('show')});
  const closeSidebar=()=>{sidebar?.classList.remove('open');overlay?.classList.remove('show')};
  qs('[data-sidebar-close]')?.addEventListener('click',closeSidebar); overlay?.addEventListener('click',closeSidebar);
  qs('[data-toggle-password]')?.addEventListener('click',e=>{const input=qs('#password'); if(!input)return; const show=input.type==='password'; input.type=show?'text':'password'; e.currentTarget.textContent=show?'Hide':'Show'});
  qs('[data-balance-toggle]')?.addEventListener('click',()=>{const el=qs('[data-balance]'); if(!el)return; if(!el.dataset.real) el.dataset.real=el.textContent; const hidden=el.textContent.includes('•'); el.textContent=hidden?el.dataset.real:'₦••••••';});
  qsa('[data-amount]').forEach(btn=>btn.addEventListener('click',()=>{const input=qs('input[name="amount"]'); if(input){input.value=btn.dataset.amount; input.focus();}}));
  const search=qs('[data-table-search]'), filter=qs('[data-status-filter]'), table=qs('[data-transaction-table]');
  const filterRows=()=>{if(!table)return; const term=(search?.value||'').toLowerCase(); const status=(filter?.value||'').toLowerCase(); qsa('tbody tr',table).forEach(row=>{const text=row.innerText.toLowerCase(); const s=(row.dataset.status||'').toLowerCase(); row.style.display=(!term||text.includes(term))&&(!status||s===status)?'':'none';});};
  search?.addEventListener('input',filterRows); filter?.addEventListener('change',filterRows);
  qsa('[data-confirm-form]').forEach(form=>form.addEventListener('submit',e=>{const amount=form.querySelector('input[name="amount"]')?.value||'0'; const provider=form.querySelector('[name="provider"]')?.value||'service'; if(!confirm(`Confirm ${provider} purchase of ₦${Number(amount).toLocaleString()}?`)) e.preventDefault();}));
})();
