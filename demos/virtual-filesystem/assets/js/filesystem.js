// Reload both the table fragment and the raw XML textarea in parallel
async function reloadView() {
  const progress = document.querySelector('progress');
  progress.style.visibility = 'visible';
  try {
    const [tableRes, xmlRes] = await Promise.all([
      fetch('/api/table'),
      fetch('/api/xml'),
    ]);
    if (tableRes.ok) {
      const html = await tableRes.text();
      const container = document.getElementById('table-container');
      if (container) container.innerHTML = html;
    }
    if (xmlRes.ok) {
      const xml = await xmlRes.text();
      const codeEl = document.getElementById('raw-xml-display');
      if (codeEl) {
        codeEl.textContent = xml;
        if (window.hljs) hljs.highlightElement(codeEl);
      }
    }
  } finally {
    setTimeout(() => { progress.style.visibility = 'hidden'; }, 300);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  // Initial syntax highlight
  if (window.hljs) hljs.highlightAll();

  // Open modals — row action buttons pre-fill the hidden parentId field
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-target]');
    if (!btn || e.target.closest('dialog')) return;
    const parentId = btn.dataset.parentId ?? '';
    const parentName = btn.dataset.parentName ?? '';
    const targetId = btn.dataset.target;
    if (targetId === 'dialog-add-folder') {
      document.getElementById('folderParentId').value = parentId;
    } else if (targetId === 'dialog-upload-file') {
      document.getElementById('uploadParentId').value = parentId;
      const label = document.getElementById('uploadTargetLabel');
      if (label) {
        label.innerHTML = 'Uploading to: <em>' + (parentName || 'Root') + '</em>';
      }
    }
    toggleModal({ preventDefault: () => {}, currentTarget: btn });
  });

  // Close modals: X button + Cancel
  document.querySelectorAll('dialog [rel=prev], dialog button.secondary').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const modal = e.currentTarget.closest('dialog');
      if (modal) closeModal(modal);
    });
  });

  // Save: New Folder
  const folderDialog = document.getElementById('dialog-add-folder');
  folderDialog.querySelector('footer button:not(.secondary)').addEventListener('click', async (e) => {
    e.preventDefault();
    const name = folderDialog.querySelector('[name=name]').value.trim();
    if (!name) return;
    const body = new FormData();
    body.append('parentId', folderDialog.querySelector('[name=parentId]').value);
    body.append('name', name);
    try {
      const res = await fetch('/api/folder/add', { method: 'POST', body });
      if (!res.ok) { console.error('folder/add failed:', res.status, await res.text()); return; }
      folderDialog.querySelector('[name=name]').value = '';
      closeModal(folderDialog);
      await reloadView();
    } catch (err) {
      console.error('folder/add error:', err);
    }
  });

  // Save: Upload File
  const uploadDialog = document.getElementById('dialog-upload-file');
  uploadDialog.querySelector('footer button:not(.secondary)').addEventListener('click', async (e) => {
    e.preventDefault();
    const fileInput = uploadDialog.querySelector('#uploadFile');
    const file = fileInput.files[0];
    if (!file) return;

    const MAX_BYTES = 500 * 1024;
    if (file.size > MAX_BYTES) {
      alert(`File is too large (${(file.size / 1024).toFixed(1)} KB). Maximum allowed size is 500 KB.`);
      fileInput.value = '';
      return;
    }

    // Normalize filename: allow letters, digits, dots, dashes, underscores
    const name = file.name.replace(/[^a-zA-Z0-9._-]/g, '_');
    const mimeType = file.type || 'application/octet-stream';
    const size = file.size;

    // Base64-encode for safe XML storage (handles binary files too)
    const buffer = await file.arrayBuffer();
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
    const content = btoa(binary);

    const body = new FormData();
    body.append('parentId', uploadDialog.querySelector('[name=parentId]').value);
    body.append('name', name);
    body.append('mimeType', mimeType);
    body.append('content', content);
    body.append('size', String(size));
    try {
      const res = await fetch('/api/file/add', { method: 'POST', body });
      if (!res.ok) { console.error('file/add failed:', res.status, await res.text()); return; }
      fileInput.value = '';
      closeModal(uploadDialog);
      await reloadView();
    } catch (err) {
      console.error('file/add error:', err);
    }
  });

  // Delete buttons
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-action="delete"]');
    if (!btn) return;
    const { id, type } = btn.dataset;
    const endpoint = type === 'folder' ? '/api/folder/remove' : '/api/file/remove';
    try {
      const body = new FormData();
      body.append('id', id);
      const res = await fetch(endpoint, { method: 'POST', body });
      if (!res.ok) { console.error('delete failed:', res.status, await res.text()); return; }
      await reloadView();
    } catch (err) {
      console.error('delete error:', err);
    }
  });

  // Preview buttons
  const previewDialog = document.getElementById('dialog-preview');
  const previewFilename = document.getElementById('preview-filename');
  const previewContent = document.getElementById('preview-content');

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-action="preview"]');
    if (!btn) return;
    const { id, name, mime } = btn.dataset;

    previewFilename.textContent = name || 'Preview';
    previewContent.innerHTML = '<p style="text-align:center;color:var(--pico-muted-color)">Loading…</p>';
    openModal(previewDialog);

    try {
      const res = await fetch('/api/file/content?id=' + encodeURIComponent(id));
      if (!res.ok) { previewContent.innerHTML = '<p class="preview-unavailable">Failed to load file.</p>'; return; }
      const data = await res.json();
      const mimeType = data.mimeType || '';

      if (mimeType.startsWith('image/')) {
        previewContent.innerHTML = '<img src="data:' + mimeType + ';base64,' + data.content + '" alt="' + (data.name || '') + '"/>';
      } else if (mimeType.startsWith('text/') || /^application\/(json|xml|javascript|xhtml\+xml)/.test(mimeType)) {
        const decoded = atob(data.content);
        const langMap = {
          'application/json': 'language-json',
          'application/xml': 'language-xml',
          'text/xml': 'language-xml',
          'text/html': 'language-html',
          'text/javascript': 'language-javascript',
          'application/javascript': 'language-javascript',
          'text/css': 'language-css',
        };
        const langClass = langMap[mimeType] || '';
        const pre = document.createElement('pre');
        const code = document.createElement('code');
        if (langClass) code.className = langClass;
        code.textContent = decoded;
        pre.appendChild(code);
        previewContent.innerHTML = '';
        previewContent.appendChild(pre);
        if (window.hljs) hljs.highlightElement(code);
      } else {
        previewContent.innerHTML = '<p class="preview-unavailable">No preview available for <em>' + mimeType + '</em> files.</p>';
      }
    } catch (err) {
      previewContent.innerHTML = '<p class="preview-unavailable">Error loading preview.</p>';
      console.error('preview error:', err);
    }
  });
});

// Block Enter and Tab inside editable spans
document.addEventListener('keydown', (e) => {
  const span = e.target.closest('[contenteditable][data-id]');
  if (!span) return;
  // prevent slash and double dots to avoid confusion with path navigation
  if (e.key === '/' || (e.key === '.' && e.target.textContent.includes('..'))) e.preventDefault();
  // prevent enter, tab
  if (['Enter', 'Tab'].includes(e.key)) e.preventDefault();
});

document.addEventListener('keyup', (e) => {
  const span = e.target.closest('[contenteditable][data-id]');
  if (!span) return;

  // Ignore navigation / modifier / control keys
  if (e.key.length > 1 && e.key !== 'Backspace' && e.key !== 'Delete') return;

  clearTimeout(span._renameTimer);

  span._renameTimer = setTimeout(async () => {
    const id   = span.dataset.id;
    const type = span.dataset.type;
    const name = span.textContent.trim();

    if (!name || name === '..' || name.includes('/') || name.includes('\\')) return;

    const endpoint = type === 'folder' ? '/api/folder/rename' : '/api/file/rename';
    const body = new FormData();
    body.append('id', id);
    body.append('name', name);

    const res = await fetch(endpoint, { method: 'POST', body });
    if (!res.ok) return;

    await reloadView();
  }, 500);
});
