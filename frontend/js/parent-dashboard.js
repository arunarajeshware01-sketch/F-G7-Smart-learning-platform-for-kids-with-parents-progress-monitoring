let CHILDREN = [];
let SELECTED_CHILD = null;
let ACTIVITY_RANGE = 'week';

(async function(){
  let session;
  try {
    session = await guardPage('parent');
  } catch (err) {
    console.error('Could not reach the backend:', err);
    document.body.innerHTML = `
      <div style="padding:60px; font-family:sans-serif; max-width:600px; margin:0 auto;">
        <h2 style="color:#a30000;">Could not connect to the server</h2>
        <p style="margin-top:12px; line-height:1.6;">
          This usually means: XAMPP's Apache or MySQL isn't running, the
          database hasn't been imported yet, or the project folder isn't at
          <code>htdocs/smart-learning/</code>. Check those, then refresh this page.
        </p>
      </div>`;
    return;
  }
  if (!session) return;

  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.createElement('button');
    btn.textContent = 'Log out';
    btn.className = 'btn btn-ghost btn-sm';
    btn.style.cssText = 'position:fixed;top:14px;right:14px;z-index:999;';
    btn.onclick = logout;
    document.body.appendChild(btn);
  });

  document.getElementById('parentName').textContent = session.name;
  await loadDashboard();
})();

async function loadDashboard(){
  const res = await apiGet('parent/dashboard.php');
  if (!res.success) { document.getElementById('childSwitch').textContent = res.message; return; }

  CHILDREN = res.children;
  document.getElementById('familySub').textContent =
    `${CHILDREN.length} child${CHILDREN.length === 1 ? '' : 'ren'} linked`;

  renderChildSwitch();
  renderNotifications(res.notifications);

  if (CHILDREN.length) {
    SELECTED_CHILD = CHILDREN[0];
    renderSelectedChild();
  } else {
    document.getElementById('subjectProgressList').innerHTML = '<p style="color:var(--ink-soft);">No child linked yet. Use "Link another child" above.</p>';
    document.getElementById('parentQuizBody').innerHTML = '<tr><td colspan="4" style="text-align:center;">No child linked yet.</td></tr>';
    document.getElementById('activityChartRow').innerHTML = '<span style="color:var(--ink-soft); font-weight:700;">No child linked yet.</span>';
  }
}

function renderChildSwitch(){
  const wrap = document.getElementById('childSwitch');
  wrap.innerHTML = CHILDREN.map((c, i) =>
    `<div class="child-chip ${SELECTED_CHILD && SELECTED_CHILD.id === c.id ? 'active' : (i===0 && !SELECTED_CHILD ? 'active' : '')}" onclick="selectChild(${c.id})">
       <div class="av">🧒</div>${c.name} · ${c.class}
     </div>`
  ).join('') + `<button class="btn btn-ghost btn-sm" onclick="toggleLinkForm()">+ Link another child</button>`;
}

function toggleLinkForm(){
  const f = document.getElementById('linkChildForm');
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
}

async function linkChild(){
  const email = document.getElementById('linkChildEmail').value.trim();
  const res = await apiPost('parent/link_child.php', { student_email: email });
  const msg = document.getElementById('linkChildMsg');
  msg.textContent = res.message;
  msg.style.color = res.success ? 'green' : '#a30000';
  if (res.success) { document.getElementById('linkChildForm').style.display = 'none'; await loadDashboard(); }
}

function selectChild(id){
  SELECTED_CHILD = CHILDREN.find(c => c.id === id);
  renderChildSwitch();
  renderSelectedChild();
}

function renderSelectedChild(){
  const c = SELECTED_CHILD;
  const totalMinutes = c.subject_progress.reduce((sum, s) => sum + (s.learning_time_minutes || 0), 0);
  const totalLessons = c.subject_progress.reduce((sum, s) => sum + (s.lessons_completed || 0), 0);
  const quizzes = c.quiz_results;
  const avgScore = quizzes.length
    ? Math.round(quizzes.reduce((sum, q) => sum + (q.score / q.total_questions), 0) / quizzes.length * 100)
    : 0;

  document.getElementById('statMinutes').textContent = totalMinutes + 'm';
  document.getElementById('statQuizCount').textContent = quizzes.length;
  document.getElementById('statAvgScore').textContent = avgScore + '%';
  document.getElementById('statLessons').textContent = totalLessons;

  loadActivityChart();

  const icons = {'English':'📖','Mathematics':'➗','Environmental Studies (EVS)':'🌍','Basic Computer Knowledge':'💻'};
  const colors = {'English':'var(--sky)','Mathematics':'var(--yellow)','Environmental Studies (EVS)':'var(--leaf)','Basic Computer Knowledge':'var(--violet)'};
  document.getElementById('subjectProgressList').innerHTML = c.subject_progress.map(s => {
    const pct = Math.min((s.lessons_completed || 0) * 10, 100);
    return `<div class="subj-prog">
      <div class="ic" style="background:#EAF8FD;">${icons[s.subject_name] || '📘'}</div>
      <span class="lbl">${s.subject_name}</span>
      <div class="bar-track"><div class="bar-fill" style="width:${pct}%; background:${colors[s.subject_name] || 'var(--sky)'};"></div></div>
      <span class="pct">${s.lessons_completed || 0} done</span>
    </div>`;
  }).join('');

  const tbody = document.getElementById('parentQuizBody');
  tbody.innerHTML = quizzes.length ? quizzes.map(q => {
    const pct = q.total_questions ? Math.round((q.score / q.total_questions) * 100) : 0;
    const cls = pct >= 80 ? 'badge-green' : (pct >= 50 ? 'badge-yellow' : 'badge-red');
    const date = new Date(q.attempted_at).toLocaleDateString('en-GB', { day:'2-digit', month:'short' });
   return <tr><td>${q.quiz_title}</td><td>${q.subject_name}</td><td>${date}</td><td><span class="badge ${cls}">${q.score} / ${q.total_questions}</span></td></tr>; 
  }).join('') : '<tr><td colspan="4" style="text-align:center;">No quizzes attempted yet.</td></tr>';
}

function renderNotifications(notifs){
  const wrap = document.getElementById('parentNotifList');
  if (!notifs.length) { wrap.innerHTML = '<p style="color:var(--ink-soft);">No notifications yet.</p>'; return; }
  wrap.innerHTML = notifs.map(n => {
    const date = new Date(n.created_at).toLocaleString('en-GB', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' });
    return `<div class="notif-item"><div class="notif-dot" style="background:#EAF8FD;">🔔</div><div><h5>${n.message}</h5><span>${date}</span></div></div>`;
  }).join('');
}

function setActivityRange(range){
  ACTIVITY_RANGE = range;
  document.querySelectorAll('#activitySeg span').forEach(el => {
    el.classList.toggle('active', el.dataset.range === range);
  });
  loadActivityChart();
}

async function loadActivityChart(){c
  const row = document.getElementById('activityChartRow');
  if (!SELECTED_CHILD) return;

  row.innerHTML = '<span style="color:var(--ink-soft); font-weight:700;">Loading…</span>';

  let res;
  try {
    res = await apiGet(`parent/activity.php?student_id=${SELECTED_CHILD.id}&range=${ACTIVITY_RANGE}`);
  } catch (err) {
    console.error('Activity chart failed to load:', err);
    row.innerHTML = '<span style="color:#a30000; font-weight:700;">Could not load activity data. Check that backend/api/parent/activity.php exists and the daily_activity table has been created (see add_daily_activity_table.sql).</span>';
    return;
  }

  if (!res || !res.success) {
    row.innerHTML = `<span style="color:#a30000; font-weight:700;">${res ? res.message : 'Unknown error loading activity.'}</span>`;
    return;
  }

  const points = res.points;
  const max = Math.max(...points.map(p => p.minutes), 1); // avoid divide-by-zero
  const today = points.length - 1;

  row.innerHTML = points.map((p, i) => {
    const heightPct = Math.max(Math.round((p.minutes / max) * 100), p.minutes > 0 ? 6 : 2);
    const isLast = i === today;
    return `
      <div class="chart-col" style="height:${heightPct}%; ${isLast ? 'background:var(--sky);' : ''}">
        <span class="val">${p.minutes}m</span>
        <span>${p.label}</span>
      </div>`;
  }).join('');
}
