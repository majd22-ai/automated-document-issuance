(() => {
  const maxBytes = 2048 * 1024;
  const minSide = 600;

  function encodePng(canvas) {
    return new Promise((resolve, reject) => {
      canvas.toBlob(blob => blob?.type === 'image/png'
        ? resolve(blob) : reject(new Error('PNG conversion failed')), 'image/png');
    });
  }

  async function loadPng(url) {
    const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
    if (!response.ok) throw new Error('Portrait unavailable');
    const image = await response.blob();
    const bitmap = await createImageBitmap(image);
    try {
      if (image.type === 'image/png' && image.size <= maxBytes
          && Math.min(bitmap.width, bitmap.height) >= minSide) return image;

      const canvas = document.createElement('canvas');
      const context = canvas.getContext('2d', { alpha: false });
      const longest = Math.max(bitmap.width, bitmap.height);
      for (const edge of [Math.max(minSide, Math.min(1800, longest)), 1000, 720, minSide]) {
        const scale = edge / longest;
        const width = Math.round(bitmap.width * scale);
        const height = Math.round(bitmap.height * scale);
        canvas.width = Math.max(minSide, width);
        canvas.height = Math.max(minSide, height);
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.imageSmoothingQuality = 'high';
        context.drawImage(bitmap, Math.floor((canvas.width - width) / 2),
          Math.floor((canvas.height - height) / 2), Math.max(1, width), Math.max(1, height));
        const png = await encodePng(canvas);
        if (png.size <= maxBytes) return png;
      }
      throw new Error('PNG exceeds upload limit');
    } finally {
      bitmap.close();
    }
  }

  function downloadPng(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 30000);
  }

  document.addEventListener('click', async event => {
    const button = event.target.closest('button[data-copy-portrait], button[data-download-portrait]');
    if (!button || button.disabled) return;
    const label = button.querySelector('span');
    const originalLabel = label.textContent.trim();
    const download = button.hasAttribute('data-download-portrait');
    button.disabled = true;
    label.textContent = 'جارٍ تجهيز الصورة…';
    try {
      if (download) {
        const png = await loadPng(button.dataset.downloadPortrait);
        downloadPng(png, button.dataset.filename);
      } else {
        if (!navigator.clipboard?.write || typeof ClipboardItem !== 'function') throw new Error('Clipboard unavailable');
        await navigator.clipboard.write([new ClipboardItem({ 'image/png': loadPng(button.dataset.copyPortrait) })]);
      }
      button.dataset.copyState = 'success';
      label.textContent = download ? '✓ بدأ التنزيل' : '✓ تم النسخ';
    } catch {
      label.textContent = download ? 'تعذّر تنزيل الصورة' : 'تعذّر النسخ، استخدم تنزيل الصورة';
    }
    setTimeout(() => {
      delete button.dataset.copyState;
      label.textContent = originalLabel;
      button.disabled = false;
    }, 2500);
  });
})();
