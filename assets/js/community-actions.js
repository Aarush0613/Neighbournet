(() => {
  const root = document.querySelector('.community-board');
  if (!root) return;
  const csrf = root.dataset.csrf;
  const incidentId = Number(root.dataset.incident || 0);
  const list = document.getElementById('taskList');
  const filter = document.getElementById('taskFilter');
  const feedback = document.getElementById('createFeedback');
  const api = '/neighbournet/api/community/tasks.php';
  let tasks = [];
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const label = value => ({open:'Open',in_progress:'In progress',completed:'Completed',cancelled:'Cancelled'}[value] || value);
  async function request(action, data={}) {
    const res = await fetch(api, {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({action,csrf_token:csrf,...data})});
    const json = await res.json(); if (!res.ok || !json.success) throw new Error(json.message || 'Request failed.'); return json;
  }
  function render() {
    const chosen = filter.value;
    const visible = tasks.filter(t => chosen==='all' || t.status===chosen);
    if (!visible.length) { list.innerHTML='<div class="card border-0 shadow-sm rounded-4"><div class="card-body text-center py-5"><i class="fa-solid fa-people-group fs-1 text-secondary mb-3"></i><h3 class="h5">No tasks found</h3><p class="text-secondary mb-0">There are no tasks in this view yet. You can create a safe, incident-linked task.</p></div></div>'; return; }
    list.innerHTML = visible.map(t => {
      const full = t.max_volunteers && Number(t.volunteer_count)>=Number(t.max_volunteers);
      const canManage = Boolean(t.can_manage);
      const updates = (t.updates||[]).map(u=>`<div class="small border-start ps-2 mb-2"><div>${esc(u.update_text)}</div><span class="text-secondary">${esc(u.author_name)} · ${esc(u.created_at)}</span></div>`).join('') || '<div class="small text-secondary">No updates yet.</div>';
      return `<article class="card border-0 shadow-sm rounded-4 task-card"><div class="card-body p-4"><div class="d-flex flex-wrap justify-content-between gap-2 mb-2"><span class="badge ${t.status==='completed'?'text-bg-success':t.status==='in_progress'?'text-bg-info':'text-bg-primary'}">${esc(label(t.status))}</span><span class="badge ${t.priority==='high'?'text-bg-danger':'text-bg-light text-dark'}">${esc(t.priority)} priority</span></div><h3 class="h5 fw-bold mb-1">${esc(t.title)}</h3><div class="small text-secondary mb-2">Linked incident: <a href="/neighbournet/pages/incident-details.php?id=${Number(t.incident_id)}">#${Number(t.incident_id)} · ${esc(t.incident_title)}</a></div><p class="mb-3">${esc(t.description)}</p><div class="d-flex flex-wrap gap-3 small text-secondary mb-3"><span><i class="fa-solid fa-location-dot me-1"></i>${esc(t.location_text)}</span><span><i class="fa-solid fa-users me-1"></i>${Number(t.volunteer_count)}${t.max_volunteers?' / '+Number(t.max_volunteers):''} volunteers</span><span><i class="fa-regular fa-user me-1"></i>${esc(t.creator_name||'Community moderator')}</span></div><div class="progress mb-3" role="progressbar" aria-label="Volunteer capacity" aria-valuenow="${t.max_volunteers?Math.min(100,100*Number(t.volunteer_count)/Number(t.max_volunteers)):0}" aria-valuemin="0" aria-valuemax="100" style="height:7px"><div class="progress-bar" style="width:${t.max_volunteers?Math.min(100,100*Number(t.volunteer_count)/Number(t.max_volunteers)):0}%"></div></div><div class="d-flex flex-wrap gap-2 mb-3">${t.status==='open' ? (Number(t.is_volunteered)?'<button class="btn btn-outline-secondary btn-sm" data-action="withdraw" data-id="'+Number(t.id)+'">Withdraw</button>':`<button class="btn btn-primary btn-sm" data-action="join" data-id="${Number(t.id)}" ${full?'disabled':''}>Volunteer</button>`) : ''}<button class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#updates-${Number(t.id)}">View updates (${(t.updates||[]).length})</button>${canManage && !['completed','cancelled'].includes(t.status)?`<button class="btn btn-outline-success btn-sm" data-action="complete" data-id="${Number(t.id)}">Mark completed</button>`:''}</div><div class="collapse" id="updates-${Number(t.id)}"><div class="rounded-3 bg-light p-3"><h4 class="h6 fw-bold">Task updates</h4>${updates}${canManage?`<form class="update-form mt-3" data-id="${Number(t.id)}"><label class="form-label small">Post progress update</label><textarea class="form-control form-control-sm mb-2" name="update_text" rows="2" minlength="4" maxlength="1500" required></textarea><button class="btn btn-sm btn-dark" type="submit">Post update</button></form>`:''}</div></div><div class="small text-secondary mt-2">Created ${esc(t.created_at)} · Updated ${esc(t.updated_at)}</div></div></article>`;
    }).join('');
  }
  async function load() {
    list.innerHTML='<div class="text-center text-secondary py-5"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>';
    try {
      const url = api + (incidentId ? '?incident_id='+incidentId : '');
      const res=await fetch(url,{credentials:'same-origin'}); const data=await res.json(); if(!res.ok||!data.success) throw new Error(data.message||'Unable to load tasks.');
      tasks=(data.tasks||[]).map(t=>({...t,can_manage:Number(t.created_by)>0 && (Number(t.created_by)===Number(window.NEIGHBOURNET_USER_ID||-1)||Boolean(t.can_manage))}));
      document.getElementById('unreadCount').textContent=`${Number(data.unread_notifications||0)} unread`;
      document.getElementById('notificationHint').textContent=Number(data.unread_notifications)>0?`${Number(data.unread_notifications)} unread notification(s).`:'You are all caught up.';
      const noteList=document.getElementById('notificationList'); noteList.innerHTML=(data.notifications||[]).map(n=>`<div class="small rounded-3 p-2 ${Number(n.is_read)?'bg-light':'bg-primary-subtle'}"><div>${esc(n.message)}</div><div class="text-secondary mt-1">${esc(n.created_at)} ${n.incident_id?`· <a href="/neighbournet/pages/incident-details.php?id=${Number(n.incident_id)}">Incident #${Number(n.incident_id)}</a>`:''}</div></div>`).join('') || '<span class="small text-secondary">No notifications yet.</span>';
      render();
    } catch(e) { list.innerHTML=`<div class="alert alert-warning">${esc(e.message)} Check that the Prompt 10 migration has been imported.</div>`; }
  }
  filter.addEventListener('change',render); document.getElementById('refreshTasks').addEventListener('click',load);
  document.getElementById('createTaskForm').addEventListener('submit',async e=>{
    e.preventDefault(); feedback.textContent='Publishing…';
    try { const data=await request('create',{incident_id:Number(document.getElementById('incidentSelect').value),title:document.getElementById('taskTitle').value.trim(),description:document.getElementById('taskDescription').value.trim(),priority:document.getElementById('taskPriority').value,max_volunteers:document.getElementById('taskLimit').value}); feedback.className='small mt-2 text-success'; feedback.textContent=data.message; e.target.reset(); await load(); }
    catch(err){feedback.className='small mt-2 text-danger';feedback.textContent=err.message;}
  });
  list.addEventListener('click',async e=>{const btn=e.target.closest('[data-action]');if(!btn)return;btn.disabled=true;try{const action=btn.dataset.action;await request(action==='complete'?'update':action,{task_id:Number(btn.dataset.id),...(action==='complete'?{status:'completed',update_text:'Task marked as completed by its creator or an administrator.'}:{})});await load();}catch(err){alert(err.message);btn.disabled=false;}});
  list.addEventListener('submit',async e=>{if(!e.target.matches('.update-form'))return;e.preventDefault();const form=e.target;const button=form.querySelector('button');button.disabled=true;try{await request('update',{task_id:Number(form.dataset.id),update_text:form.elements.update_text.value.trim()});await load();}catch(err){alert(err.message);button.disabled=false;}});
  document.getElementById('markRead').addEventListener('click',async()=>{try{await request('mark_notifications_read');await load();}catch(e){alert(e.message);}});
  load();
})();
