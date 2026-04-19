// Shared XML reload
async function reloadXml() {
  const progress = document.querySelector('progress');
  progress.style.visibility = 'visible';
  const xmlRes = await fetch('/api/xml');
  if (!xmlRes.ok) { progress.style.visibility = 'hidden'; return; }
  const xml = await xmlRes.text();
  const textarea = document.querySelector('textarea');
  if (textarea) textarea.value = xml;
  setTimeout(() => { progress.style.visibility = 'hidden'; }, 500);
}

document.addEventListener('DOMContentLoaded', () => {
  // Open modals — row action buttons pre-fill the hidden parentId field
  document.querySelectorAll('[data-target]').forEach(btn => btn.addEventListener('click', (e) => {
    const parentId = btn.dataset.parentId ?? '';
    const targetId = btn.dataset.target;
    if (targetId === 'dialog-add-folder') {
      document.getElementById('folderParentId').value = parentId;
    } else if (targetId === 'dialog-upload-file') {
      document.getElementById('uploadParentId').value = parentId;
    }
    toggleModal(e);
  }));

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
      await reloadXml();
    } catch (err) {
      console.error('folder/add error:', err);
    }
  });

  // Save: Upload File
  const uploadDialog = document.getElementById('dialog-upload-file');
  uploadDialog.querySelector('footer button:not(.secondary)').addEventListener('click', async (e) => {
    e.preventDefault();
    const nameInput = uploadDialog.querySelector('[name=name]');
    const fileInput = uploadDialog.querySelector('#uploadFile');
    const name = nameInput.value.trim();
    const file = fileInput.files[0];
    if (!name || !file) return;
    const content = await file.text();
    const body = new FormData();
    body.append('parentId', uploadDialog.querySelector('[name=parentId]').value);
    body.append('name', name);
    body.append('mimeType', file.type || 'text/plain');
    body.append('content', content);
    try {
      const res = await fetch('/api/file/add', { method: 'POST', body });
      if (!res.ok) { console.error('file/add failed:', res.status, await res.text()); return; }
      nameInput.value = '';
      fileInput.value = '';
      closeModal(uploadDialog);
      await reloadXml();
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
      await reloadXml();
    } catch (err) {
      console.error('delete error:', err);
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

    await reloadXml();
  }, 500);
});
