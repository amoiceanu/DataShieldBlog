document.querySelectorAll('[data-exposure-tabs]').forEach(workspace => {
 const tabs = [...workspace.querySelectorAll('[data-exposure-tab]')];
 const panels = [...workspace.querySelectorAll('[data-exposure-panel]')];
 const activate = (tab, focus = false) => {
  tabs.forEach(t => { const active = t === tab; t.setAttribute('aria-selected', String(active)); t.tabIndex = active ? 0 : -1; });
  panels.forEach(p => p.hidden = p.dataset.exposurePanel !== tab.dataset.exposureTab);
  if (focus) tab.focus();
 };
 workspace.querySelector('.exposure-tabs').setAttribute('role', 'tablist');
 tabs.forEach((tab, i) => {
  tab.id = 'exposure-tab-' + tab.dataset.exposureTab;
  tab.setAttribute('role','tab'); tab.setAttribute('aria-controls', tab.dataset.exposureTab);
  const panel = panels.find(p => p.dataset.exposurePanel === tab.dataset.exposureTab);
  panel?.setAttribute('role','tabpanel'); panel?.setAttribute('aria-labelledby',tab.id);
  tab.addEventListener('click', e => { e.preventDefault(); activate(tab); });
  tab.addEventListener('keydown', e => {
   let next;
   if (e.key === 'ArrowRight') next = (i + 1) % tabs.length;
   if (e.key === 'ArrowLeft') next = (i + tabs.length - 1) % tabs.length;
   if (e.key === 'Home') next = 0;
   if (e.key === 'End') next = tabs.length - 1;
   if (next !== undefined) { e.preventDefault(); activate(tabs[next], true); }
  });
 });
 if (tabs.length) activate(tabs.find(t => '#' + t.dataset.exposureTab === window.location.hash) || tabs[0]);
});
document.querySelectorAll('[data-public-search]').forEach(form => {
 form.querySelectorAll('[name=match_type]').forEach(radio => radio.addEventListener('change', () => {
  const email = form.querySelector('[name=match_type]:checked').value === 'email';
  const input = form.querySelector('[name=subject]');
  input.type = email ? 'email' : 'text'; input.value = ''; input.placeholder = email ? 'nume@exemplu.ro' : 'Numele și prenumele';
  form.querySelector('[data-subject-label]').textContent = email ? 'Adresa ta de e-mail' : 'Nume complet';
  form.querySelector('[data-own-label]').textContent = email ? 'Verific propria adresă de e-mail.' : 'Confirm că verific propriile date.';
  form.querySelector('[data-location]').hidden = email;
  form.querySelector('[name=location]').disabled = email;
 }));
});
document.querySelectorAll('[data-processing-form]').forEach(form => form.addEventListener('submit', () => {
 const button = form.querySelector('button[type=submit],button:not([type])');
 if (button) { button.disabled = true; button.textContent = 'Se înregistrează solicitarea…'; }
}));
const status = document.querySelector('[data-exposure-status]');
if (status) {
 let stopped = false;
 const poll = async () => {
  if (stopped) return;
  try {
   const response = await fetch(status.dataset.exposureStatus, {headers:{Accept:'application/json'}, cache:'no-store', credentials:'same-origin'});
   if (!response.ok) { stopped = true; status.textContent = 'Statusul nu mai este disponibil. Raportul poate fi expirat sau sesiunea s-a încheiat.'; return; }
   const data = await response.json();
   if (data.state !== status.dataset.initialState) { stopped = true; window.location.reload(); return; }
  } catch { status.textContent = 'Conexiunea pentru actualizare este indisponibilă. Încercăm din nou; acest mesaj nu reprezintă un rezultat negativ.'; }
  window.setTimeout(poll, 5000);
 };
 window.setTimeout(poll, 5000);
}
