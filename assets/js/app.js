(() => {
  const ensureStyle=(href,key)=>{if(document.querySelector(`link[data-${key}]`))return;const l=document.createElement('link');l.rel='stylesheet';l.href=href;l.dataset[key]='1';document.head.appendChild(l);};
  ensureStyle('/assets/css/layout-fix.css?v=2','ihlinkLayoutFix');
  ensureStyle('/assets/css/vtu-user.css?v=1','ihlinkVtuUser');

  const qs=(s,c=document)=>c.querySelector(s), qsa=(s,c=document)=>[...c.querySelectorAll(s)];
  const sidebarNav=qs('.sidebar-nav');
  const headerRole=(qs('.dash-header .eyebrow')?.textContent||'').trim().toLowerCase();
  const userRole=(qs('.user-chip small')?.textContent||'').trim().toLowerCase();
  const isCustomer=!!qs('.dashboard-main') && (headerRole.includes('customer')||userRole==='customer') && !qs('a[href="?page=reseller"]',sidebarNav||document) && !qs('a[href="?page=api-dashboard"]',sidebarNav||document) && !qs('a[href="?page=admin"]',sidebarNav||document);
  const hasAdmin=!!qs('a[href="?page=admin"]',sidebarNav||document);

  if(isCustomer){
    document.body.classList.add('ih-customer-ui');
    const page=new URLSearchParams(location.search).get('page')||'dashboard';
    const title=qs('.dash-header h1');
    if(page==='dashboard'&&title)title.textContent='Dashboard';
    const roleTag=qs('.user-chip small');if(roleTag)roleTag.textContent='Personal Account';

    const overview=qs('a[href="?page=dashboard"]',sidebarNav||document);if(overview)overview.querySelector('span')&&(overview.querySelector('span').textContent='Dashboard');
    const profile=qs('a[href="?page=profile"]',sidebarNav||document);if(profile)profile.querySelector('span')&&(profile.querySelector('span').textContent='Profile');

    // Standard customers should only see consumer VTU navigation.
    const commonPages=['dashboard','data','airtime','electricity','cable','exam','wallet','transactions','profile'];
    qsa('.sidebar-nav .nav-item').forEach(a=>{const p=new URL(a.href,location.origin).searchParams.get('page');a.dataset.common=commonPages.includes(p)?'1':'0';});

    // Remove internal pricing/provider environment information from the consumer workspace.
    qsa('.dashboard-main .notice').forEach(n=>{const t=n.textContent.toLowerCase();if(t.includes('pricing')||t.includes('admin controls every tier')||t.includes('staging mode')||t.includes('provider mode'))n.classList.add('customer-hidden');});
    const summary=qs('.workspace-grid>.account-summary');if(summary)summary.classList.add('customer-hidden');
    qsa('.checkout small').forEach(s=>{const t=s.textContent.toLowerCase();if(t.includes('staging')||t.includes('provider'))s.classList.add('customer-hidden');});
    qsa('.pricing-badge').forEach(b=>{b.textContent='Price';});

    // Keep upgrades in Profile rather than cluttering the normal dashboard/sidebar.
    const roleAction=qs('input[name="action"][value="change_role"]');
    const roleForm=roleAction?.closest('form');
    if(roleForm){
      const box=document.createElement('div');box.className='customer-profile-upgrade';
      box.innerHTML='<h4>Business & reseller access</h4><p>Need reseller pricing or API access for your business? Submit an upgrade request for review.</p><a class="btn btn-ghost" href="/upgrade.php">View upgrade options</a>';
      roleForm.replaceWith(box);
    }
  } else {
    if(sidebarNav&&!qs('a[href="/upgrade.php"]',sidebarNav)){
      const upgrade=document.createElement('a');upgrade.href='/upgrade.php';upgrade.className='nav-item';upgrade.innerHTML='<span>Upgrade Account</span>';sidebarNav.appendChild(upgrade);
    }
    if(sidebarNav&&hasAdmin&&!qs('a[href="/admin-control.php"]',sidebarNav)){
      const control=document.createElement('a');control.href='/admin-control.php';control.className='nav-item';control.innerHTML='<span>Tier Management</span>';sidebarNav.appendChild(control);
    }
    if(sidebarNav&&hasAdmin&&!qs('a[href="/staff-admin.php"]',sidebarNav)){
      const staff=document.createElement('a');staff.href='/staff-admin.php';staff.className='nav-item';staff.innerHTML='<span>Staff Admins</span>';sidebarNav.appendChild(staff);
    }

    const legacyRoleAction=qs('input[name="action"][value="change_role"]');
    const legacyRoleForm=legacyRoleAction?.closest('form');
    if(legacyRoleForm){const box=document.createElement('div');box.innerHTML='<div class="notice"><span>↗</span><div><b>Tier changes require approval</b><br><small>Request Premium, Reseller or API access from the upgrade page.</small><br><br><a class="btn btn-primary" href="/upgrade.php">Request an upgrade →</a></div></div>';legacyRoleForm.replaceWith(box);}
  }

  // Google OAuth entry point on both login and registration forms.
  const authAction=qs('form input[name="action"][value="login"], form input[name="action"][value="register"]');
  const authForm=authAction?.closest('form');
  if(authForm&&!qs('[data-google-auth]')){
    const googleWrap=document.createElement('div');googleWrap.dataset.googleAuth='1';googleWrap.style.marginTop='16px';
    googleWrap.innerHTML=`<div style="display:flex;align-items:center;gap:10px;margin:14px 0;color:#7a8797;font-size:12px"><span style="height:1px;background:#e2e8f0;flex:1"></span><span>OR</span><span style="height:1px;background:#e2e8f0;flex:1"></span></div><a href="/google-auth.php?action=start" class="btn btn-ghost" style="width:100%;display:flex;align-items:center;justify-content:center;gap:10px;border:1px solid #d9e1ea;background:#fff"><span style="font-weight:900;font-size:18px">G</span><span>Continue with Google</span></a>`;
    authForm.appendChild(googleWrap);
  }

  const sidebar=qs('#sidebar'),overlay=qs('[data-sidebar-overlay]');
  qs('[data-sidebar-open]')?.addEventListener('click',()=>{sidebar?.classList.add('open');overlay?.classList.add('show')});
  const closeSidebar=()=>{sidebar?.classList.remove('open');overlay?.classList.remove('show')};
  qs('[data-sidebar-close]')?.addEventListener('click',closeSidebar);overlay?.addEventListener('click',closeSidebar);
  qs('[data-toggle-password]')?.addEventListener('click',e=>{const input=qs('#password');if(!input)return;const show=input.type==='password';input.type=show?'text':'password';e.currentTarget.textContent=show?'Hide':'Show'});
  qs('[data-balance-toggle]')?.addEventListener('click',()=>{const el=qs('[data-balance]');if(!el)return;if(!el.dataset.real)el.dataset.real=el.textContent;const hidden=el.textContent.includes('•');el.textContent=hidden?el.dataset.real:'₦••••••';});
  qsa('[data-amount]').forEach(btn=>btn.addEventListener('click',()=>{const input=qs('input[name="amount"]');if(input){input.value=btn.dataset.amount;input.focus();}}));
  const search=qs('[data-table-search]'),filter=qs('[data-status-filter]'),table=qs('[data-transaction-table]');
  const filterRows=()=>{if(!table)return;const term=(search?.value||'').toLowerCase();const status=(filter?.value||'').toLowerCase();qsa('tbody tr',table).forEach(row=>{const text=row.innerText.toLowerCase();const s=(row.dataset.status||'').toLowerCase();row.style.display=(!term||text.includes(term))&&(!status||s===status)?'':'none';});};
  search?.addEventListener('input',filterRows);filter?.addEventListener('change',filterRows);
  qsa('[data-confirm-form]').forEach(form=>form.addEventListener('submit',e=>{const amount=form.querySelector('input[name="amount"]')?.value||'0';const provider=form.querySelector('[name="provider"]')?.value||'service';if(!confirm(`Confirm ${provider} purchase of ₦${Number(amount).toLocaleString()}?`))e.preventDefault();}));
})();
