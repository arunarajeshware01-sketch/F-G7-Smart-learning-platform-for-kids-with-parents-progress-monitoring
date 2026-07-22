(async function(){
  const session = await guardPage('student');
  if (!session) return;

  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.createElement('button');
    btn.textContent = 'Log out';
    btn.className = 'btn btn-ghost btn-sm';
    btn.style.cssText = 'position:fixed;top:14px;right:14px;z-index:999;';
    btn.onclick = logout;
    document.body.appendChild(btn);
  });

  let res;
  try {
    res = await apiGet('student/dashboard.php');
  } catch (err) {
    document.getElementById('greetingH1').textContent = 'Something went wrong loading your dashboard.';
    document.getElementById('continueQueueList').innerHTML =
      '<p style="color:#a30000;">Could not reach the server. Check that Apache/MySQL are running and try refreshing.</p>';
    console.error(err);
    return;
  }
  if (!res.success) { alert(res.message); return; }

  const s = res.student;
  document.getElementById('sideName').textContent = s.name;
  document.getElementById('sideClass').textContent = s.class + ' · Student';
  document.getElementById('greetingH1').textContent = `Hi ${s.name.split(' ')[0]}! 👋`;

  // Fill each subject card using the subject name as the match key
  document.querySelectorAll('.subj-card').forEach(card => {
    const subjName = card.dataset.subject;
    const data = res.subjects.find(x => x.subject_name === subjName);
    const total = data ? (data.total_content || 0) : 0;
    const completed = data ? Math.min(data.lessons_completed || 0, total) : 0;
    const minutes = data ? (data.learning_time_minutes || 0) : 0;
    if (data) card.dataset.subjectId = data.subject_id;
    // Percentage is now based on the real number of videos+lessons published
    // for that subject, so it accurately reflects what's actually available.
    const pct = total > 0 ? Math.min(Math.round((completed / total) * 100), 100) : 0;
    card.querySelector('[data-field="meta"]').textContent = total > 0
      ? `${completed} of ${total} completed`
      : 'No content published yet';
    card.querySelector('[data-field="bar"]').style.width = pct + '%';
    card.querySelector('[data-field="pct"]').textContent = `${pct}% · ${minutes} min learned`;
  });

  renderContinueQueue(res.continue_queue || []);
  loadNotifications();
  loadAllQuizzes();
  renderRecentScores(res.recent_scores || []);
})();

function renderRecentScores(scores){
  const tbody = document.getElementById('quizScoresBody');
  if (!scores.length) return;
  tbody.innerHTML = scores.map(q => {
    const pct = q.total_questions ? Math.round((q.score / q.total_questions) * 100) : 0;
    const cls = pct >= 80 ? 'badge-green' : (pct >= 50 ? 'badge-yellow' : 'badge-red');
    const date = new Date(q.attempted_at).toLocaleDateString('en-GB', { day:'2-digit', month:'short' });
    return `<tr><td>${q.quiz_title}</td><td>${q.subject_name}</td><td>${date}</td>
            <td><span class="badge ${cls}">${q.score} / ${q.total_questions}</span></td></tr>`;
  }).join('');
}

async function refreshRecentScores(){
  const res = await apiGet('student/dashboard.php');
  if (res.success) renderRecentScores(res.recent_scores || []);
}

async function openSubject(cardEl){
  const subjectId = cardEl.dataset.subjectId;
  const subjectName = cardEl.querySelector('h4').textContent;
  if (!subjectId) return;

  const panel = document.getElementById('subjectContentPanel');
  const body = document.getElementById('subjectContentBody');
  document.getElementById('subjectContentTitle').textContent = subjectName;
  panel.style.display = 'block';
  body.innerHTML = 'Loading…';
  panel.scrollIntoView({ behavior: 'smooth', block: 'start' });

  const res = await apiGet(`student/content.php?subject_id=${subjectId}`);
  if (!res.success) { body.innerHTML = `<p>${res.message}</p>`; return; }

  let html = '';

  html += '<h4 style="margin-bottom:10px;">🎬 Videos</h4>';
  html += res.videos.length
    ? res.videos.map(v => `
        <div style="margin-bottom:18px;" id="video-row-${v.id}">
          <p style="font-weight:700; margin-bottom:6px;">${v.title} <span class="badge badge-green" id="video-done-${v.id}" style="${v.completed ? '' : 'display:none;'}">✅ Completed</span></p>
          <video controls style="width:100%; max-width:520px; border-radius:12px; background:#000;" src="${fileUrl(v.video_url)}"
                 onended="onVideoEnded(this, ${subjectId}, ${v.id})"></video>
        </div>`).join('')
    : '<p style="color:var(--ink-soft);">No videos uploaded for this subject yet.</p>';

  html += '<h4 style="margin:18px 0 10px;">📘 Lessons / Study material</h4>';
  html += res.lessons.length
    ? res.lessons.map(l => `
        <div class="lesson-item" style="padding:10px 0; border-bottom:1px solid var(--line);" id="lesson-row-${l.id}">
          <p style="font-weight:700;">${l.title} <span class="badge badge-green" id="lesson-done-${l.id}" style="${l.completed ? '' : 'display:none;'}">✅ Completed</span></p>
          <p style="color:var(--ink-soft); font-size:13px;">${l.description || ''}</p>
          ${l.content_url ? `<a href="${fileUrl(l.content_url)}" target="_blank" onclick="logProgress(${subjectId}, 'lesson', ${l.id}, 0)">Open material →</a>` : ''}
        </div>`).join('')
    : '<p style="color:var(--ink-soft);">No study material added yet.</p>';

  html += '<h4 style="margin:18px 0 10px;">📝 Quizzes</h4>';
  html += res.quizzes.length
    ? res.quizzes.map(q => `
        <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--line);">
          <span style="font-weight:700;">${q.title}</span>
          <button class="btn btn-primary btn-sm" onclick="startQuiz(${q.id})">Take quiz</button>
        </div>`).join('')
    : '<p style="color:var(--ink-soft);">No quizzes for this subject yet.</p>';

  body.innerHTML = html;
}

async function logProgress(subjectId, contentType, contentId, minutes){
  const res = await apiPost('student/update_progress.php', {
    subject_id: subjectId, content_type: contentType, content_id: contentId, minutes
  });
  if (res.success) {
    // Refresh the subject card progress bar in the background without a full reload
    refreshSubjectCards();

    // Show an immediate checkmark + toast so it's obvious the lecture was
    // marked complete, instead of only relying on the outer progress bar.
    if (res.counted_as_new_lesson) {
      const badge = document.getElementById(`${contentType}-done-${contentId}`);
      if (badge) badge.style.display = 'inline-flex';
      showToast('✅ Progress updated — nice work!');
    }
  }
}

function showToast(message){
  let toast = document.getElementById('progressToast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'progressToast';
    toast.style.cssText = 'position:fixed; bottom:24px; left:50%; transform:translateX(-50%); background:#1F6B45; color:white; font-weight:800; font-size:13.5px; padding:12px 20px; border-radius:14px; z-index:2000; box-shadow:0 10px 24px rgba(0,0,0,.18); transition:opacity .3s;';
    document.body.appendChild(toast);
  }
  toast.textContent = message;
  toast.style.opacity = '1';
  clearTimeout(toast._hideTimer);
  toast._hideTimer = setTimeout(() => { toast.style.opacity = '0'; }, 2200);
}

async function onVideoEnded(videoEl, subjectId, videoId){
  const rawMinutes = Math.round((videoEl.duration || 60) / 60);
  const minutes = Math.max(1, Number.isFinite(rawMinutes) ? rawMinutes : 1);
  await logProgress(subjectId, 'video', videoId, minutes);
}

function renderContinueQueue(items){
  const tag = document.getElementById('queueCountTag');
  const list = document.getElementById('continueQueueList');
  tag.textContent = `${items.length} item${items.length === 1 ? '' : 's'} queued`;

  if (!items.length) {
    list.innerHTML = '<p style="color:var(--ink-soft);">You\'re all caught up! Check back after your teacher adds new content.</p>';
    return;
  }

  const icons = {'English':'📖','Mathematics':'➗','Environmental Studies (EVS)':'🌍','Basic Computer Knowledge':'💻'};
  list.innerHTML = items.map(item => `
    <div class="lesson-row">
      <div class="lesson-ic" style="background:#EAF8FD;">${icons[item.subject_name] || '📘'}</div>
      <div><h5>${item.title}</h5><span>${item.subject_name} · ${item.content_type === 'video' ? 'Video' : 'Lesson'}</span></div>
      <button class="btn btn-primary btn-sm" onclick="openSubjectById(${item.subject_id})">Start</button>
    </div>`).join('');
}

function openSubjectById(subjectId){
  const card = document.querySelector(`.subj-card[data-subject-id="${subjectId}"]`);
  if (card) openSubject(card);
}

async function refreshSubjectCards(){
  const res = await apiGet('student/dashboard.php');
  if (!res.success) return;
  document.querySelectorAll('.subj-card').forEach(card => {
    const subjName = card.dataset.subject;
    const data = res.subjects.find(x => x.subject_name === subjName);
    const total = data ? (data.total_content || 0) : 0;
    const completed = data ? Math.min(data.lessons_completed || 0, total) : 0;
    const minutes = data ? (data.learning_time_minutes || 0) : 0;
    const pct = total > 0 ? Math.min(Math.round((completed / total) * 100), 100) : 0;
    card.querySelector('[data-field="meta"]').textContent = total > 0
      ? `${completed} of ${total} completed`
      : 'No content published yet';
    card.querySelector('[data-field="bar"]').style.width = pct + '%';
    card.querySelector('[data-field="pct"]').textContent = `${pct}% · ${minutes} min learned`;
  });
  renderContinueQueue(res.continue_queue || []);
}

// ---------------- Quiz-taking modal ----------------

let quizState = null; // { quizId, title, questions, answers, index }

async function startQuiz(quizId){
  const res = await apiGet(`student/quiz_get.php?quiz_id=${quizId}`);
  if (!res.success) { alert(res.message); return; }

  if (!res.questions.length) {
    alert('This quiz has no questions yet. Please check back later.');
    return;
  }

  quizState = {
    quizId,
    title: res.quiz.title,
    questions: res.questions,
    answers: {},
    index: 0,
  };

  document.getElementById('quizOverlay').classList.add('open');
  renderQuizQuestion();
}

function closeQuizModal(){
  document.getElementById('quizOverlay').classList.remove('open');
  quizState = null;
}

function renderQuizQuestion(){
  const st = quizState;
  const q = st.questions[st.index];
  const total = st.questions.length;
  const answeredCount = Object.keys(st.answers).length;
  const pct = Math.round(((st.index + 1) / total) * 100);
  const options = [['A', q.option_a], ['B', q.option_b], ['C', q.option_c], ['D', q.option_d]];

  const dots = st.questions.map((qq, i) => {
    let cls = 'quiz-dot';
    if (i === st.index) cls += ' current';
    else if (st.answers[qq.id]) cls += ' answered';
    return `<span class="${cls}"></span>`;
  }).join('');

  document.getElementById('quizCard').innerHTML = `
    <div class="quiz-card-head">
      <h3>📝 ${st.title}</h3>
      <button class="quiz-close" onclick="confirmCloseQuiz()">✕</button>
    </div>
    <div class="quiz-progress-track"><div class="quiz-progress-fill" style="width:${pct}%;"></div></div>
    <div class="quiz-progress-lbl">Question ${st.index + 1} of ${total} · ${answeredCount} answered</div>
    <div class="quiz-question">${q.question_text}</div>
    <div class="quiz-options">
      ${options.map(([letter, text]) => `
        <button type="button" class="quiz-option ${st.answers[q.id] === letter ? 'selected' : ''}" onclick="selectQuizOption('${letter}')">
          <span class="opt-letter">${letter}</span><span>${text}</span>
        </button>`).join('')}
    </div>
    <div class="quiz-nav">
      <button class="btn btn-ghost btn-sm" ${st.index === 0 ? 'disabled' : ''} onclick="goQuizStep(-1)">← Back</button>
      <div class="quiz-dots">${dots}</div>
      ${st.index === total - 1
        ? `<button class="btn btn-primary btn-sm" onclick="submitQuizAnswers()">Submit ✓</button>`
        : `<button class="btn btn-primary btn-sm" onclick="goQuizStep(1)">Next →</button>`}
    </div>
  `;
}

function selectQuizOption(letter){
  const st = quizState;
  const q = st.questions[st.index];
  st.answers[q.id] = letter;
  renderQuizQuestion();
  // Small delay so the selection is visible before auto-advancing
  if (st.index < st.questions.length - 1) {
    setTimeout(() => {
      if (quizState === st && st.index === quizState.index) goQuizStep(1);
    }, 280);
  }
}

function goQuizStep(direction){
  const st = quizState;
  const newIndex = st.index + direction;
  if (newIndex < 0 || newIndex >= st.questions.length) return;
  st.index = newIndex;
  renderQuizQuestion();
}

function confirmCloseQuiz(){
  const answered = Object.keys(quizState.answers).length;
  const total = quizState.questions.length;
  if (answered < total) {
    if (!confirm(`You've only answered ${answered} of ${total} questions. Close without submitting?`)) return;
  }
  closeQuizModal();
}

async function submitQuizAnswers(){
  const st = quizState;
  document.getElementById('quizCard').innerHTML = `
    <div style="text-align:center; padding:30px 0;"><p style="font-weight:800; color:var(--ink-soft);">Submitting…</p></div>`;

  const submitRes = await apiPost('student/quiz_submit.php', { quiz_id: st.quizId, answers: st.answers });

  if (!submitRes.success) {
    alert(submitRes.message);
    closeQuizModal();
    return;
  }

  renderQuizResult(submitRes.score, submitRes.total);
  loadAllQuizzes(); // refresh best-score badge in the list behind the modal
  refreshRecentScores();
}

function renderQuizResult(score, total){
  const pct = total ? Math.round((score / total) * 100) : 0;
  const good = pct >= 80;
  const ok = pct >= 50;
  const color = good ? '#1F6B45' : (ok ? '#8A6412' : '#B0332D');
  const bg = good ? '#E9F8F0' : (ok ? '#FFF6DE' : '#FFEAEA');
  const message = good ? 'Excellent work! 🎉' : (ok ? 'Good effort — keep practicing! 💪' : 'Nice try — review the lesson and try again! 📘');

  document.getElementById('quizCard').innerHTML = `
    <div class="quiz-result">
      <div class="score-circle" style="background:${bg}; color:${color};">
        <span class="num">${score}/${total}</span>
        <span class="lbl">Score</span>
      </div>
      <p style="font-family:'Fredoka'; font-size:17px; margin-bottom:8px;">${message}</p>
      <p style="color:var(--ink-soft); font-weight:700; font-size:13.5px; margin-bottom:24px;">You got ${pct}% correct.</p>
      <button class="btn btn-primary btn-sm" onclick="closeQuizModal()">Done</button>
    </div>
  `;
}

document.addEventListener('keydown', (e) => {
  if (!quizState) return;
  if (e.key === 'Escape') confirmCloseQuiz();
  if (['a','A','1'].includes(e.key)) selectQuizOption('A');
  if (['b','B','2'].includes(e.key)) selectQuizOption('B');
  if (['c','C','3'].includes(e.key)) selectQuizOption('C');
  if (['d','D','4'].includes(e.key)) selectQuizOption('D');
});

// ---------------- Notifications panel ----------------

async function loadNotifications(){
  const res = await apiGet('student/notifications.php');
  const badge = document.getElementById('notifBadge');
  const list = document.getElementById('notifList');
  if (!res.success) {
    list.innerHTML = '<p style="color:var(--ink-soft);">Could not load notifications.</p>';
    return;
  }

  if (res.unread_count > 0) {
    badge.style.display = 'block';
    badge.textContent = res.unread_count > 9 ? '9+' : res.unread_count;
  } else {
    badge.style.display = 'none';
  }

  if (!res.notifications.length) {
    list.innerHTML = '<p style="color:var(--ink-soft);">No notifications yet.</p>';
    return;
  }

  list.innerHTML = res.notifications.map(n => {
    const date = new Date(n.created_at).toLocaleDateString('en-GB', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' });
    return `
      <div style="padding:10px 0; border-bottom:1px solid var(--line); ${n.is_read == 0 ? 'background:#F5FBFF;' : ''}">
        <p style="font-size:13.5px; font-weight:${n.is_read == 0 ? '800' : '600'};">${n.message}</p>
        <span style="font-size:11px; color:var(--ink-soft);">${date}</span>
      </div>`;
  }).join('');
}

function toggleNotifPanel(){
  const panel = document.getElementById('notifPanel');
  const opening = panel.style.display === 'none';
  panel.style.display = opening ? 'block' : 'none';
  if (opening) loadNotifications();
}

document.addEventListener('click', (e) => {
  const panel = document.getElementById('notifPanel');
  const bell = document.getElementById('notifBellBtn');
  if (!panel || panel.style.display === 'none') return;
  if (!panel.contains(e.target) && !bell.contains(e.target)) panel.style.display = 'none';
});

async function markAllNotifsRead(){
  await apiPost('student/notifications.php', { mark_all: true });
  loadNotifications();
}

// ---------------- All quizzes panel ----------------

async function loadAllQuizzes(){
  const res = await apiGet('student/quizzes.php');
  const tag = document.getElementById('quizCountTag');
  const list = document.getElementById('allQuizzesList');
  if (!res.success) {
    list.innerHTML = '<p style="color:var(--ink-soft);">Could not load quizzes.</p>';
    return;
  }
  tag.textContent = `${res.quizzes.length} quiz${res.quizzes.length === 1 ? '' : 'zes'}`;

  if (!res.quizzes.length) {
    list.innerHTML = '<p style="color:var(--ink-soft);">No quizzes have been published yet. Check back soon!</p>';
    return;
  }

  list.innerHTML = res.quizzes.map(q => {
    const attempted = q.attempt_count > 0;
    const noQuestions = (q.question_count || 0) === 0;
    let scoreBadge = '';
    if (attempted) {
      const pct = q.best_total ? Math.round((q.best_score / q.best_total) * 100) : 0;
      const cls = pct >= 80 ? 'badge-green' : (pct >= 50 ? 'badge-yellow' : 'badge-red');
      scoreBadge = `<span class="badge ${cls}" style="margin-right:10px;">Best: ${q.best_score}/${q.best_total}</span>`;
    }
    return `
      <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid var(--line);">
        <div>
          <p style="font-weight:700;">${q.title}</p>
          <span style="font-size:12px; color:var(--ink-soft); font-weight:700;">${q.subject_name} · ${q.question_count} question${q.question_count === 1 ? '' : 's'}</span>
        </div>
        <div style="display:flex; align-items:center;">
          ${scoreBadge}
          <button class="btn btn-primary btn-sm" ${noQuestions ? 'disabled' : ''} onclick="startQuiz(${q.id})">
            ${noQuestions ? 'Coming soon' : (attempted ? 'Retake quiz' : 'Take quiz')}
          </button>
        </div>
      </div>`;
  }).join('');
}
