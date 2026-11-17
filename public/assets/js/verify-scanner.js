(() => {
  const start = document.getElementById('start-barcode-scan');
  const stop = document.getElementById('stop-barcode-scan');
  const video = document.getElementById('barcode-camera');
  const status = document.getElementById('barcode-scan-status');
  if (!start || !video || !status) return;
  let stream = null, active = false;
  const halt = () => {
    active = false;
    if (stream) stream.getTracks().forEach(track => track.stop());
    stream = null;
    video.pause(); video.srcObject = null; video.hidden = true;
    start.hidden = false; if (stop) stop.hidden = true;
  };
  if (stop) stop.addEventListener('click', () => { halt(); status.textContent = 'Camera stopped. You can still enter a code manually.'; });
  start.addEventListener('click', async () => {
    if (!('BarcodeDetector' in window)) {
      status.textContent = 'This browser does not support built-in barcode scanning. Use the manual verification field below.';
      return;
    }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      status.textContent = 'Camera access is unavailable. Use the manual verification field below.';
      return;
    }
    try {
      const detector = new BarcodeDetector({ formats: ['code_128'] });
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
      video.srcObject = stream; video.hidden = false; await video.play();
      active = true; start.hidden = true; if (stop) stop.hidden = false;
      status.textContent = 'Point your camera at the result barcode.';
      const scan = async () => {
        if (!active) return;
        try {
          const codes = await detector.detect(video);
          if (codes.length && codes[0].rawValue) {
            const value = codes[0].rawValue;
            const match = value.match(/(?:\/verify\/)?([a-f0-9]{32})$/i);
            if (match) { halt(); window.location.assign(new URL(value, window.location.href).href); return; }
            status.textContent = 'Barcode detected, but it is not a GRADELY verification code.';
          }
        } catch (_) {}
        if (active) requestAnimationFrame(scan);
      };
      requestAnimationFrame(scan);
    } catch (_) {
      halt();
      status.textContent = 'Camera permission was denied or the camera is unavailable. Enter the verification code manually.';
    }
  });
  window.addEventListener('pagehide', halt, { once: true });
})();