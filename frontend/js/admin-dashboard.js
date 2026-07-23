let SUBJECTS = [];

function jumpToPanel(panelId, focusFieldId){
  const panel = document.getElementById(panelId);
  if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  const field = focusFieldId ? document.getElementById(focusFieldId) : null;
  if (field) setTimeout(() => field.focus(), 400);
}
let ALL_STUDENTS = [];

(async function(){
  const session = await guardPage('admin');
  if (!session) return;

  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.createElement('button');
    btn.textContent = 'Log out';
    btn.className = 'btn btn-ghost btn-sm';
    btn.style.cssText = 'position:fixed;top:14px;right:14px;z-index:999;';
    btn.onclick = logout;
    document.body.appendChild(btn);
  });

  document.getElementById('adminName').textContent = session.name;

  await loadSubjects();
  await loadClasses();
  await loadStats();
  await loadStudents();
  await loadParents();
  await loadContentLists();
  await loadQuizzesList();
  addQuestionRow(); // start the quiz builder with one question

  document.getElementById('studentSearch').addEventListener('input', renderStudents);
  document.getElementById('parentSearch').addEventListener('input', renderParents);
  // Event delegation so class filter buttons work even after being
  // re-rendered dynamically from the classes list.
  document.getElementById('classFilterTabs').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    document.querySelectorAll('#classFilterTabs button').forEach(x => x.classList.remove('active'));
    b.classList.add('active');
    renderStudents();
  });
})();

async function loadSubjects(){
  const res = await apiGet('admin/subjects.php');
  if (!res.success) return;
  SUBJECTS = res.subjects;
  const opts = SUBJECTS.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
  ['videoSubject','lessonSubject','quizSubject'].forEach(id => {
    document.getElementById(id).innerHTML = opts;
  });
  document.getElementById('statSubjects').textContent = SUBJECTS.length;
  renderSubjectsTable();
}

let RENAME_SUBJECT_ID = null;

function renderSubjectsTable(){
  const tbody = document.getElementById('subjectsBody');
  if (!SUBJECTS.length) { tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No subjects yet — add one above.</td></tr>'; return; }

  tbody.innerHTML = SUBJECTS.map(s => {
    const isRenaming = RENAME_SUBJECT_ID === s.id;
    const hasContent = (s.video_count + s.lesson_count + s.quiz_count) > 0;
    const nameCell = isRenaming
      ? `<input type="text" id="renameInput-${s.id}" value="${s.name}" style="padding:6px 10px; border-radius:8px; border:2px solid var(--line); font-weight:700;">`
      : `<strong>${s.name}</strong>`;
    const actionCell = isRenaming
      ? `<button class="btn btn-primary btn-sm" onclick="saveRenameSubject(${s.id})">Save</button>
         <button class="btn btn-ghost btn-sm" onclick="cancelRenameSubject()">Cancel</button>`
      : `<button class="icon-btn" onclick="startRenameSubject(${s.id})">✏️</button>
         <button class="icon-btn" onclick="deleteSubject(${s.id}, '${s.name.replace(/'/g, "\\'")}', ${hasContent ? 'true' : 'false'})">🗑️</button>`;

    return `<tr>
      <td>${nameCell}</td>
      <td>${s.video_count}</td>
      <td>${s.lesson_count}</td>
      <td>${s.quiz_count}</td>
      <td style="white-space:nowrap;">${actionCell}</td>
    </tr>`;
  }).join('');
}

async function createSubject(){
  const input = document.getElementById('newSubjectName');
  const name = input.value.trim();
  const msg = document.getElementById('subjectAddMsg');
  if (!name) { msg.textContent = 'Enter a subject name first.'; msg.style.color = '#a30000'; return; }

  const res = await apiPost('admin/subjects.php', { action: 'create', name });
  msg.textContent = res.message;
  msg.style.color = res.success ? 'green' : '#a30000';
  if (res.success) { input.value = ''; await loadSubjects(); }
}

function startRenameSubject(id){
  RENAME_SUBJECT_ID = id;
  renderSubjectsTable();
  const input = document.getElementById(`renameInput-${id}`);
  if (input) { input.focus(); input.select(); }
}

function cancelRenameSubject(){
  RENAME_SUBJECT_ID = null;
  renderSubjectsTable();
}

async function saveRenameSubject(id){
  const input = document.getElementById(`renameInput-${id}`);
  const name = input.value.trim();
  if (!name) { alert('Subject name cannot be empty.'); return; }

  const res = await apiPost('admin/subjects.php', { id, name });
  if (res.success) {
    RENAME_SUBJECT_ID = null;
    await loadSubjects();
  } else {
    alert(res.message);
  }
}

async function deleteSubject(id, name, hasContent){
  if (hasContent) {
    alert(`"${name}" still has videos, lessons, or quizzes published under it. Remove/reassign that content in "Manage published content" before deleting the subject.`);
    return;
  }
  if (!confirm(`Delete the subject "${name}"? This cannot be undone.`)) return;

  const res = await apiPost('admin/subjects.php', { action: 'delete', id });
  if (res.success) {
    await loadSubjects();
  } else {
    alert(res.message);
  }
}

let CLASSES = [];
let RENAME_CLASS_ID = null;

async function loadClasses(){
  const res = await apiGet('admin/classes.php');
  if (!res.success) return;
  CLASSES = res.classes;

  const opts = CLASSES.map(c => `<option>${c.name}</option>`).join('');
  ['newStuClass','videoClass','lessonClass','quizClass','editClass'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.innerHTML = opts;
  });

  // Rebuild the "All classes / Class 1 / Class 2 …" filter tabs on the students panel
  const tabs = document.getElementById('classFilterTabs');
  tabs.innerHTML = '<button class="active" data-class="all">All classes</button>' +
    CLASSES.map(c => `<button data-class="${c.name}">${c.name}</button>`).join('');

  renderClassesTable();
}

function renderClassesTable(){
  const tbody = document.getElementById('classesBody');
  if (!CLASSES.length) { tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No classes yet — add one above.</td></tr>'; return; }

  tbody.innerHTML = CLASSES.map(c => {
    const isRenaming = RENAME_CLASS_ID === c.id;
    const contentTotal = c.video_count + c.lesson_count + c.quiz_count;
    const inUse = c.student_count > 0 || contentTotal > 0;
    const nameCell = isRenaming
      ? `<input type="text" id="renameClassInput-${c.id}" value="${c.name}" style="padding:6px 10px; border-radius:8px; border:2px solid var(--line); font-weight:700;">`
      : `<strong>${c.name}</strong>`;
    const actionCell = isRenaming
      ? `<button class="btn btn-primary btn-sm" onclick="saveRenameClass(${c.id})">Save</button>
         <button class="btn btn-ghost btn-sm" onclick="cancelRenameClass()">Cancel</button>`
      : `<button class="icon-btn" onclick="startRenameClass(${c.id})">✏️</button>
         <button class="icon-btn" onclick="deleteClass(${c.id}, '${c.name.replace(/'/g, "\\'")}', ${inUse ? 'true' : 'false'})">🗑️</button>`;

    return `<tr>
      <td>${nameCell}</td>
      <td>${c.student_count}</td>
      <td>${contentTotal}</td>
      <td style="white-space:nowrap;">${actionCell}</td>
    </tr>`;
  }).join('');
}

async function createClass(){
  const input = document.getElementById('newClassName');
  const name = input.value.trim();
  const msg = document.getElementById('classAddMsg');
  if (!name) { msg.textContent = 'Enter a class name first.'; msg.style.color = '#a30000'; return; }

  const res = await apiPost('admin/classes.php', { action: 'create', name });
  msg.textContent = res.message;
  msg.style.color = res.success ? 'green' : '#a30000';
  if (res.success) { input.value = ''; await loadClasses(); }
}

function startRenameClass(id){
  RENAME_CLASS_ID = id;
  renderClassesTable();
  const input = document.getElementById(`renameClassInput-${id}`);
  if (input) { input.focus(); input.select(); }
}

function cancelRenameClass(){
  RENAME_CLASS_ID = null;
  renderClassesTable();
}

async function saveRenameClass(id){
  const input = document.getElementById(`renameClassInput-${id}`);
  const name = input.value.trim();
  if (!name) { alert('Class name cannot be empty.'); return; }

  const res = await apiPost('admin/classes.php', { id, name });
  if (res.success) {
    RENAME_CLASS_ID = null;
    await loadClasses();
  } else {
    alert(res.message);
  }
}

async function deleteClass(id, name, inUse){
  if (inUse) {
    alert(`"${name}" still has students or content assigned to it. Reassign them to a different class before deleting.`);
    return;
  }
  if (!confirm(`Delete the class "${name}"? This cannot be undone.`)) return;

  const res = await apiPost('admin/classes.php', { action: 'delete', id });
  if (res.success) {
    await loadClasses();
  } else {
    alert(res.message);
  }
}

async function loadStats(){
  const res = await apiGet('admin/reports.php');
  if (!res.success) return;
  document.getElementById('statStudents').textContent = res.total_students;
  document.getElementById('statParents').textContent = res.total_parents;
  document.getElementById('statQuizzes').textContent = res.total_quizzes;
  document.getElementById('statusStudents').textContent = `${res.total_students} students, ${res.total_parents} parents registered`;
  document.getElementById('statusQuizAvg').textContent = `Average quiz score: ${res.average_score_percent}%`;
}

async function loadStudents(){
  const res = await apiGet('admin/students.php');
  if (!res.success) { document.getElementById('studentsBody').innerHTML = `<tr><td colspan="5">${res.message}</td></tr>`; return; }
  ALL_STUDENTS = res.students;
  renderStudents();
}

function renderStudents(){
  const activeClass = document.querySelector('#classFilterTabs button.active')?.dataset.class || 'all';
  document.getElementById('studentCountLabel').textContent = '(${ALL_STUDENTS.length} total)';
  const q = (document.getElementById('studentSearch').value || '').toLowerCase();
  const rows = ALL_STUDENTS.filter(s =>
    (activeClass === 'all' || s.class === activeClass) &&
    (s.name.toLowerCase().includes(q) || s.email.toLowerCase().includes(q))
  );
  const tbody = document.getElementById('studentsBody');
  if (!rows.length) { tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">No students found.</td></tr>`; return; }
  tbody.innerHTML = rows.map(s => `
    <tr>
      <td><div class="row-name"><div class="row-avatar" style="background:#EAF8FD;">🧒</div>${s.name}</div></td>
      <td>${s.class}</td>
      <td>${s.email}</td>
      <td>${s.parent_email}</td>
      <td><button class="icon-btn" onclick="deleteStudent(${s.id}, '${s.name.replace(/'/g,"\\'")}')">🗑️</button></td>
    </tr>
  `).join('');
}

function toggleAddStudentForm(){
  const f = document.getElementById('addStudentForm');
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
}

async function createStudent(){
  const payload = {
    action: 'create',
    name: document.getElementById('newStuName').value.trim(),
    email: document.getElementById('newStuEmail').value.trim(),
    age: document.getElementById('newStuAge').value,
    class: document.getElementById('newStuClass').value,
    parent_mobile: document.getElementById('newStuParentMobile').value.trim(),
    parent_email: document.getElementById('newStuParentEmail').value.trim(),
    password: document.getElementById('newStuPassword').value,
  };
  const res = await apiPost('admin/students.php', payload);
  const msg = document.getElementById('addStudentMsg');
  msg.textContent = res.message;
  msg.style.color = res.success ? 'green' : '#a30000';
  if (res.success) { await loadStudents(); await loadStats(); document.getElementById('addStudentForm').style.display = 'none'; }
}

async function deleteStudent(id, name){
  if (!confirm(`Remove ${name}'s account? This cannot be undone.`)) return;
  const res = await apiPost('admin/students.php', { action: 'delete', id });
  if (res.success) { await loadStudents(); await loadStats(); } else alert(res.message);
}

let ALL_PARENTS = [];

async function loadParents(){
  const res = await apiGet('admin/parents.php');
  if (!res.success) { document.getElementById('parentsBody').innerHTML = `<tr><td colspan="5">${res.message}</td></tr>`; return; }
  ALL_PARENTS = res.parents;
  renderParents();
}

function renderParents(){
  const q = (document.getElementById('parentSearch').value || '').toLowerCase();
  const rows = ALL_PARENTS.filter(p => p.name.toLowerCase().includes(q) || p.email.toLowerCase().includes(q));
  const tbody = document.getElementById('parentsBody');
  if (!rows.length) { tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">No parents found.</td></tr>`; return; }
  tbody.innerHTML = rows.map(p => `
    <tr>
      <td><div class="row-name"><div class="row-avatar" style="background:#F2ECFF;">👪</div>${p.name}</div></td>
      <td>${p.mobile}</td>
      <td>${p.email}</td>
      <td>${p.children_count}</td>
      <td><button class="icon-btn" onclick="deleteParent(${p.id}, '${p.name.replace(/'/g,"\\'")}', ${p.children_count})">🗑️</button></td>
    </tr>
  `).join('');
}

async function deleteParent(id, name, childrenCount){
  const warn = childrenCount > 0
    ? `${name} has ${childrenCount} linked child account(s), which will be unlinked (not deleted). Remove this parent account?`
    : `Remove ${name}'s account? This cannot be undone.`;
  if (!confirm(warn)) return;
  const res = await apiPost('admin/parents.php', { action: 'delete', id });
  if (res.success) { await loadParents(); await loadStats(); } else alert(res.message);
}

async function uploadVideo(){
  const form = new FormData();
  form.append('subject_id', document.getElementById('videoSubject').value);
  form.append('class_level', document.getElementById('videoClass').value);
  form.append('title', document.getElementById('videoTitle').value.trim());
  const file = document.getElementById('videoFile').files[0];
  if (!file) { document.getElementById('videoMsg').textContent = 'Choose a video file first.'; return; }
  form.append('video', file);
  const res = await apiUpload('admin/videos.php', form);
  const msg = document.getElementById('videoMsg');
  msg.textContent = res.message;
  msg.style.color = res.success ? 'green' : '#a30000';
  if (res.success) await loadContentLists();
}

async function addLesson(){
  const payload = {
    subject_id: document.getElementById('lessonSubject').value,
    class_level: document.getElementById('lessonClass').value,
    title: document.getElementById('lessonTitle').value.trim(),
    description: document.getElementById('lessonDesc').value.trim(),
    content_url: document.getElementById('lessonUrl').value.trim(),
  };
  const res = await apiPost('admin/lessons.php', payload);
  const msg = document.getElementById('lessonMsg');
  msg.textContent = res.message;
  msg.style.color = res.success ? 'green' : '#a30000';
  if (res.success) await loadContentLists();
}

async function sendNotification(){
  const payload = {
    recipient_type: document.getElementById('notifyTarget').value,
    message: document.getElementById('notifyMessage').value.trim(),
  };
  const res = await apiPost('admin/notifications.php', payload);
  const msg = document.getElementById('notifyMsg');
  msg.textContent = res.message;
  msg.style.color = res.success ? 'green' : '#a30000';
}

async function runBackup(){
  const msg = document.getElementById('backupMsg');
  msg.textContent = 'Running backup…';
  const res = await apiGet('admin/backup.php');
  msg.textContent = res.message;
  msg.style.color = res.success ? 'green' : '#a30000';
}

// ---- Quiz builder ----
let questionCount = 0;
let EDIT_QUIZ_ID = null; // set when editing an existing quiz instead of creating a new one

function addQuestionRow(prefill){
  questionCount++;
  const div = document.createElement('div');
  div.className = 'quiz-q-row';
  div.style.cssText = 'border:1px solid var(--line); border-radius:12px; padding:14px; margin-top:12px; position:relative;';
  div.innerHTML = `
    <button type="button" class="icon-btn" style="position:absolute; top:10px; right:10px;" onclick="this.closest('.quiz-q-row').remove()">🗑️</button>
    <div class="field"><label>Question ${questionCount}</label><input type="text" class="q-text" placeholder="Question text" value="${prefill ? escapeAttr(prefill.question_text) : ''}"></div>
    <div class="field-row" style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
      <input type="text" class="q-a" placeholder="Option A" value="${prefill ? escapeAttr(prefill.option_a) : ''}">
      <input type="text" class="q-b" placeholder="Option B" value="${prefill ? escapeAttr(prefill.option_b) : ''}">
      <input type="text" class="q-c" placeholder="Option C" value="${prefill ? escapeAttr(prefill.option_c) : ''}">
      <input type="text" class="q-d" placeholder="Option D" value="${prefill ? escapeAttr(prefill.option_d) : ''}">
    </div>
    <div class="field" style="margin-top:8px; max-width:160px;"><label>Correct option</label>
      <select class="q-correct"><option>A</option><option>B</option><option>C</option><option>D</option></select>
    </div>`;
  document.getElementById('quizQuestions').appendChild(div);
  if (prefill) div.querySelector('.q-correct').value = prefill.correct_option;
}

function escapeAttr(str){
  return (str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;');
}

async function saveQuiz(){
  const rows = Array.from(document.querySelectorAll('.quiz-q-row'));
  if (!rows.length) { alert('Add at least one question first.'); return; }

  const questions = rows.map(row => ({
    question_text: row.querySelector('.q-text').value.trim(),
    option_a: row.querySelector('.q-a').value.trim(),
    option_b: row.querySelector('.q-b').value.trim(),
    option_c: row.querySelector('.q-c').value.trim(),
    option_d: row.querySelector('.q-d').value.trim(),
    correct_option: row.querySelector('.q-correct').value,
  }));

  const payload = {
    action: EDIT_QUIZ_ID ? 'update' : 'create',
    subject_id: document.getElementById('quizSubject').value,
    class_level: document.getElementById('quizClass').value,
    title: document.getElementById('quizTitle').value.trim(),
    questions,
  };
  if (EDIT_QUIZ_ID) payload.id = EDIT_QUIZ_ID;

  const res = await apiPost('admin/quizzes.php', payload);
  const msg = document.getElementById('quizMsg');
  msg.textContent = res.message;
  msg.style.color = res.success ? 'green' : '#a30000';
  if (res.success) {
    await loadStats();
    await loadQuizzesList();
    if (EDIT_QUIZ_ID) setTimeout(cancelQuizEdit, 600);
  }
}

function cancelQuizEdit(){
  EDIT_QUIZ_ID = null;
  document.getElementById('quizBuilderHeading').textContent = 'Create a quiz';
  document.getElementById('quizSaveBtn').textContent = 'Save quiz';
  document.getElementById('quizCancelEditBtn').style.display = 'none';
  document.getElementById('quizTitle').value = '';
  document.getElementById('quizQuestions').innerHTML = '';
  questionCount = 0;
  addQuestionRow();
  document.getElementById('quizMsg').textContent = '';
}

// ---- Manage quizzes: list, edit (loads full quiz into the builder above), delete ----
let ALL_QUIZZES = [];

async function loadQuizzesList(){
  const res = await apiGet('admin/quizzes.php');
  if (!res.success) return;
  ALL_QUIZZES = res.quizzes;
  renderQuizzesList();
}

function renderQuizzesList(){
  const tbody = document.getElementById('quizzesListBody');
  if (!ALL_QUIZZES.length) { tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No quizzes created yet.</td></tr>'; return; }
  tbody.innerHTML = ALL_QUIZZES.map(q => `
    <tr>
      <td>${q.title}</td>
      <td>${q.subject_name}</td>
      <td>${q.class_level}</td>
      <td>${q.question_count}</td>
      <td>${q.attempt_count}</td>
      <td style="white-space:nowrap;">
        <button class="icon-btn" onclick="editQuiz(${q.id})">✏️</button>
        <button class="icon-btn" onclick="deleteQuiz(${q.id}, '${q.title.replace(/'/g,"\\'")}')">🗑️</button>
      </td>
    </tr>`).join('');
}

async function editQuiz(quizId){
  const res = await apiGet(`admin/quizzes.php?quiz_id=${quizId}`);
  if (!res.success) { alert(res.message); return; }

  EDIT_QUIZ_ID = quizId;
  document.getElementById('quizBuilderHeading').textContent = `Editing: ${res.quiz.title}`;
  document.getElementById('quizSaveBtn').textContent = 'Save changes';
  document.getElementById('quizCancelEditBtn').style.display = 'inline-block';
  document.getElementById('quizSubject').value = res.quiz.subject_id;
  document.getElementById('quizClass').value = res.quiz.class_level;
  document.getElementById('quizTitle').value = res.quiz.title;

  document.getElementById('quizQuestions').innerHTML = '';
  questionCount = 0;
  res.questions.forEach(q => addQuestionRow(q));

  document.getElementById('quizMsg').textContent = '';
  jumpToPanel('quizbuilder', 'quizTitle');
}

async function deleteQuiz(id, title){
  if (!confirm(`Delete quiz "${title}"? This also removes any recorded student attempts for it. This cannot be undone.`)) return;
  const res = await apiPost('admin/quizzes.php', { action: 'delete', id });
  if (res.success) {
    await loadQuizzesList();
    await loadStats();
    if (EDIT_QUIZ_ID === id) cancelQuizEdit();
  } else {
    alert(res.message);
  }
}

// ---- Manage published content (videos + lessons): list, edit, delete ----
let ALL_VIDEOS = [];
let ALL_LESSONS = [];

async function loadContentLists(){
  const [vRes, lRes] = await Promise.all([
    apiGet('admin/videos.php'),
    apiGet('admin/lessons.php'),
  ]);
  if (vRes.success) { ALL_VIDEOS = vRes.videos; renderVideosList(); }
  if (lRes.success) { ALL_LESSONS = lRes.lessons; renderLessonsList(); }
}

function renderVideosList(){
  const tbody = document.getElementById('videosListBody');
  if (!ALL_VIDEOS.length) { tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No videos published yet.</td></tr>'; return; }
  tbody.innerHTML = ALL_VIDEOS.map(v => `
    <tr>
      <td>${v.title}</td><td>${v.subject_name}</td><td>${v.class_level}</td>
      <td>
        <button class="icon-btn" onclick='openEditModal("video", ${JSON.stringify(v)})'>✏️</button>
        <button class="icon-btn" onclick="deleteVideo(${v.id}, '${v.title.replace(/'/g,"\\'")}')">🗑️</button>
      </td>
    </tr>`).join('');
}

function renderLessonsList(){
  const tbody = document.getElementById('lessonsListBody');
  if (!ALL_LESSONS.length) { tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No study material published yet.</td></tr>'; return; }
  tbody.innerHTML = ALL_LESSONS.map(l => `
    <tr>
      <td>${l.title}</td><td>${l.subject_name}</td><td>${l.class_level}</td>
      <td>
        <button class="icon-btn" onclick='openEditModal("lesson", ${JSON.stringify(l)})'>✏️</button>
        <button class="icon-btn" onclick="deleteLesson(${l.id}, '${l.title.replace(/'/g,"\\'")}')">🗑️</button>
      </td>
    </tr>`).join('');
}

async function deleteVideo(id, title){
  if (!confirm(`Delete video "${title}"? This cannot be undone.`)) return;
  const res = await apiPost('admin/videos.php', { action: 'delete', id });
  if (res.success) await loadContentLists(); else alert(res.message);
}

async function deleteLesson(id, title){
  if (!confirm(`Delete study material "${title}"? This cannot be undone.`)) return;
  const res = await apiPost('admin/lessons.php', { action: 'delete', id });
  if (res.success) await loadContentLists(); else alert(res.message);
}

let EDIT_TARGET = null; // { type: 'video'|'lesson', id }

function openEditModal(type, item){
  EDIT_TARGET = { type, id: item.id };
  document.getElementById('editModalTitle').textContent = type === 'video' ? 'Edit video details' : 'Edit study material';
  document.getElementById('editSubject').innerHTML = SUBJECTS.map(s => `<option value="${s.id}" ${s.id === item.subject_id ? 'selected' : ''}>${s.name}</option>`).join('');
  document.getElementById('editClass').value = item.class_level;
  document.getElementById('editTitle').value = item.title;
  document.getElementById('editDescWrap').style.display = type === 'lesson' ? 'block' : 'none';
  document.getElementById('editUrlWrap').style.display = type === 'lesson' ? 'block' : 'none';
  if (type === 'lesson') {
    document.getElementById('editDesc').value = item.description || '';
    document.getElementById('editUrl').value = item.content_url || '';
  }
  document.getElementById('editMsg').textContent = '';
  document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal(){
  document.getElementById('editModal').style.display = 'none';
  EDIT_TARGET = null;
}

async function saveEdit(){
  if (!EDIT_TARGET) return;
  const payload = {
    action: 'update',
    id: EDIT_TARGET.id,
    subject_id: document.getElementById('editSubject').value,
    class_level: document.getElementById('editClass').value,
    title: document.getElementById('editTitle').value.trim(),
  };
  const endpoint = EDIT_TARGET.type === 'video' ? 'admin/videos.php' : 'admin/lessons.php';
  if (EDIT_TARGET.type === 'lesson') {
    payload.description = document.getElementById('editDesc').value.trim();
    payload.content_url = document.getElementById('editUrl').value.trim();
  }
  const res = await apiPost(endpoint, payload);
  const msg = document.getElementById('editMsg');
  msg.textContent = res.message;
  msg.style.color = res.success ? 'green' : '#a30000';
  if (res.success) { await loadContentLists(); setTimeout(closeEditModal, 500); }
}
