(() => {
  const GOOGLE_CLIENT_ID = '1020593076419-tv5l93390dog560otm0o2p87h36vq1rs.apps.googleusercontent.com';

  const DOC_TYPES = {
    call_sheet: { title: 'Call Sheet Generator', intro: 'Create a call sheet on its own, without needing a booking on the calendar.' },
    risk_assessment: { title: 'Risk Assessment Generator', intro: 'Create a risk assessment on its own, without needing a booking on the calendar.' },
  };
  const params = new URLSearchParams(location.search);
  const docType = DOC_TYPES[params.get('type')] ? params.get('type') : 'call_sheet';

  const el = {
    authGate: document.getElementById('authGate'),
    appRoot: document.getElementById('appRoot'),
    googleSignInBtn: document.getElementById('googleSignInBtn'),
    authError: document.getElementById('authError'),
    currentUserLabel: document.getElementById('currentUserLabel'),
    signOutBtn: document.getElementById('signOutBtn'),
    docTypeTitle: document.getElementById('docTypeTitle'),
    docTypeIntro: document.getElementById('docTypeIntro'),
    newDocForm: document.getElementById('newDocForm'),
    newDocTitle: document.getElementById('newDocTitle'),
    newDocDate: document.getElementById('newDocDate'),
    newDocLocation: document.getElementById('newDocLocation'),
    newDocError: document.getElementById('newDocError'),
    docsTableBody: document.getElementById('docsTableBody'),
    docsEmpty: document.getElementById('docsEmpty'),
    filterAllBtn: document.getElementById('filterAllBtn'),
    filterMineBtn: document.getElementById('filterMineBtn'),
    filterOthersBtn: document.getElementById('filterOthersBtn'),
  };

  let currentUserEmail = null;
  let allDocs = [];
  let activeFilter = 'all';

  if (docType === 'risk_assessment') {
    el.newDocLocation.classList.add('hidden');
  }

  el.docTypeTitle.textContent = DOC_TYPES[docType].title;
  el.docTypeIntro.textContent = DOC_TYPES[docType].intro;
  document.title = DOC_TYPES[docType].title + ' — Film Plan';

  async function apiGet(path) {
    const res = await fetch(path, { credentials: 'include' });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Request failed');
    return json.data;
  }

  async function apiPost(path, body) {
    const res = await fetch(path, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body || {}),
    });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Request failed');
    return json.data;
  }

  function formatDate(dateStr) {
    const d = new Date(dateStr.replace(' ', 'T'));
    return d.toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
  }

  async function loadDocs() {
    try {
      const data = await apiGet(`api/standalone_docs_list.php?type=${docType}`);
      allDocs = data.docs;
      renderDocs();
    } catch (err) {
      allDocs = [];
      el.docsTableBody.innerHTML = '';
      el.docsEmpty.textContent = 'Could not load documents.';
      el.docsEmpty.classList.remove('hidden');
    }
  }

  function renderDocs() {
    const docs = allDocs.filter((doc) => {
      if (activeFilter === 'mine') return doc.created_by === currentUserEmail;
      if (activeFilter === 'others') return doc.created_by !== currentUserEmail;
      return true;
    });
    el.docsTableBody.innerHTML = '';
    el.docsEmpty.classList.toggle('hidden', docs.length > 0);
    el.docsEmpty.textContent = allDocs.length ? 'No documents match this filter.' : 'No documents created yet.';
    for (const doc of docs) {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${escapeHtml(doc.title)}</td>
        <td>${escapeHtml(formatDate(doc.start_datetime))}</td>
        <td>${escapeHtml(doc.location || '')}</td>
        <td>${escapeHtml(doc.created_by_name || '')}</td>
        <td></td>
      `;
      const actionsCell = row.lastElementChild;
      const editLink = document.createElement('a');
      editLink.href = `calendar.html?standalone_doc=${doc.id}&type=${docType}`;
      editLink.textContent = 'Open';
      editLink.className = 'secondary-link-btn';
      actionsCell.appendChild(editLink);
      actionsCell.appendChild(document.createTextNode(' '));
      const deleteBtn = document.createElement('button');
      deleteBtn.type = 'button';
      deleteBtn.textContent = 'Delete';
      deleteBtn.className = 'danger';
      deleteBtn.addEventListener('click', () => onDeleteClick(doc.id, doc.title));
      actionsCell.appendChild(deleteBtn);
      el.docsTableBody.appendChild(row);
    }
  }

  function setFilter(filter) {
    activeFilter = filter;
    el.filterAllBtn.classList.toggle('active', filter === 'all');
    el.filterMineBtn.classList.toggle('active', filter === 'mine');
    el.filterOthersBtn.classList.toggle('active', filter === 'others');
    renderDocs();
  }

  el.filterAllBtn.addEventListener('click', () => setFilter('all'));
  el.filterMineBtn.addEventListener('click', () => setFilter('mine'));
  el.filterOthersBtn.addEventListener('click', () => setFilter('others'));

  async function onDeleteClick(id, title) {
    if (!confirm(`Delete "${title}"? This can't be undone.`)) return;
    try {
      await apiPost(`api/standalone_doc_delete.php?id=${id}`);
      await loadDocs();
    } catch (err) {
      alert(err.message);
    }
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  el.newDocForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    el.newDocError.classList.add('hidden');
    try {
      const data = await apiPost('api/standalone_doc_create.php', {
        doc_type: docType,
        title: el.newDocTitle.value.trim(),
        date: el.newDocDate.value,
        location: el.newDocLocation.value.trim(),
      });
      location.href = `calendar.html?standalone_doc=${data.id}&type=${docType}`;
    } catch (err) {
      el.newDocError.textContent = err.message;
      el.newDocError.classList.remove('hidden');
    }
  });

  function waitForGoogleIdentity(cb) {
    if (window.google && window.google.accounts && window.google.accounts.id) {
      cb();
    } else {
      setTimeout(() => waitForGoogleIdentity(cb), 50);
    }
  }

  function initSignIn() {
    waitForGoogleIdentity(() => {
      google.accounts.id.initialize({
        client_id: GOOGLE_CLIENT_ID,
        callback: onCredentialResponse,
      });
      google.accounts.id.renderButton(el.googleSignInBtn, { theme: 'outline', size: 'large' });
    });
  }

  async function onCredentialResponse(response) {
    el.authError.classList.add('hidden');
    try {
      const user = await apiPost('api/auth_login.php', { id_token: response.credential });
      await showApp(user);
    } catch (err) {
      el.authError.textContent = err.message;
      el.authError.classList.remove('hidden');
    }
  }

  async function showApp(user) {
    el.authGate.classList.add('hidden');
    el.appRoot.classList.remove('hidden');
    el.currentUserLabel.textContent = user.name || user.email;
    currentUserEmail = user.email;
    el.newDocDate.value = new Date().toISOString().slice(0, 10);
    await loadDocs();
  }

  function showAuthGate() {
    el.appRoot.classList.add('hidden');
    el.authGate.classList.remove('hidden');
    initSignIn();
  }

  el.signOutBtn.addEventListener('click', async () => {
    await apiPost('api/auth_logout.php');
    showAuthGate();
  });

  (async function init() {
    try {
      const user = await apiGet('api/auth_me.php');
      await showApp(user);
    } catch (err) {
      showAuthGate();
    }
  })();
})();
