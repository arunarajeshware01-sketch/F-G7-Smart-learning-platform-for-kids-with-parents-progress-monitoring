// ============================================================
// Smart Learning Platform — frontend API helper
// Edit API_BASE if your backend folder name/path is different.
// Default assumes: htdocs/smart-learning/backend/
// ============================================================
const API_BASE = "http://localhost/smart-learning/backend/api";
const BACKEND_ROOT = API_BASE.replace(/\/api$/, ""); // e.g. http://localhost/smart-learning/backend

// video_url/content_url from the database are stored relative to /backend/
// (e.g. "uploads/videos/xyz.mp4"). This turns that into a real playable URL.
function fileUrl(relativePath) {
  if (!relativePath) return "";
  if (/^https?:\/\//i.test(relativePath)) return relativePath; // already a full URL
  return `${BACKEND_ROOT}/${relativePath}`;
}

async function apiPost(endpoint, data) {
  const res = await fetch(`${API_BASE}/${endpoint}`, {
    method: "POST",
    credentials: "include", // send PHP session cookie
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data),
  });
  return res.json();
}

async function apiGet(endpoint) {
  const res = await fetch(`${API_BASE}/${endpoint}`, {
    method: "GET",
    credentials: "include",
  });
  return res.json();
}

// Multipart upload (e.g. admin video upload)
async function apiUpload(endpoint, formData) {
  const res = await fetch(`${API_BASE}/${endpoint}`, {
    method: "POST",
    credentials: "include",
    body: formData,
  });
  return res.json();
}

// Redirects to login.html if no session, or if logged in under the wrong role.
// Call this at the top of every dashboard page:
//   guardPage('student');
async function guardPage(requiredRole) {
  const result = await apiGet("session.php");
  if (!result.logged_in || result.role !== requiredRole) {
    window.location.href = "login.html";
    return null;
  }
  return result;
}

async function logout() {
  await apiPost("logout.php", {});
  window.location.href = "login.html";
}
