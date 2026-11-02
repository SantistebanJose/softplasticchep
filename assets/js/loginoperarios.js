(function () {
  const MAX_LEN_DNI = 8;
  const MAX_LEN_PIN = 4;
  let dni = '';
  let pin = '';
  let nombreOperario = '';

  // ---- Paso 1: DNI (igual que antes) ----
  const dots = document.querySelectorAll('#pcOpDisplay .pc-op-dot');
  const msgEl = document.getElementById('pcOpMsg');
  const cardEl = document.getElementById('pcOpCardDni');
  const btnIngresar = document.getElementById('pcOpIngresar');
  const btnBorrar = document.getElementById('pcOpBorrar');
  const btnLimpiar = document.getElementById('pcOpLimpiar');

  // ---- Paso 2: PIN (modal nuevo) ----
  const pinOverlay = document.getElementById('pcOpPinOverlay');
  const pinModal = document.getElementById('pcOpPinModal');
  const pinBoxes = document.querySelectorAll('#pcOpPinBoxes .pc-op-pin-box');
  const pinMsgEl = document.getElementById('pcOpMsgPin');
  const pinNombreEl = document.getElementById('pcOpPinNombre');
  const btnIngresarPin = document.getElementById('pcOpIngresarPin');
  const btnCancelarPin = document.getElementById('pcOpCancelarPin');
  const btnPinBorrar = document.getElementById('pcOpPinBorrar');
  const btnPinLimpiar = document.getElementById('pcOpPinLimpiar');
  const keypadPin = document.getElementById('pcOpKeypadPin');

  function render() {
    dots.forEach((dot, i) => dot.classList.toggle('pc-op-dot-filled', i < dni.length));
  }

  function showMsg(text) {
    msgEl.textContent = text;
    msgEl.classList.toggle('pc-op-msg-visible', !!text);
  }

  function shake() {
    cardEl.classList.add('pc-op-shake');
    setTimeout(() => cardEl.classList.remove('pc-op-shake'), 350);
  }

  // Solo conecta los botones del teclado del DNI (el del PIN se conecta aparte)
  document.querySelectorAll('.pc-op-keypad .pc-op-key[data-key]').forEach(btn => {
    if (btn.closest('#pcOpKeypadPin')) return;
    btn.addEventListener('click', () => {
      if (dni.length >= MAX_LEN_DNI) return;
      showMsg('');
      dni += btn.dataset.key;
      render();
      if (dni.length === MAX_LEN_DNI) validarDni();
    });
  });

  btnBorrar.addEventListener('click', () => { dni = dni.slice(0, -1); render(); showMsg(''); });
  btnLimpiar.addEventListener('click', () => { dni = ''; render(); showMsg(''); });
  btnIngresar.addEventListener('click', validarDni);

  function validarDni() {
    if (dni.length !== MAX_LEN_DNI) {
      showMsg('Ingresa los 8 dígitos de tu DNI.');
      shake();
      return;
    }

    btnIngresar.disabled = true;

    fetch('ajax_login_operario.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'dni=' + encodeURIComponent(dni)
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          nombreOperario = data.nombre || '';
          abrirModalPin();
        } else {
          showMsg(data.error || 'No se pudo ingresar.');
          shake();
          dni = '';
          render();
        }
      })
      .catch(() => {
        showMsg('Error de conexión. Intenta nuevamente.');
        shake();
      })
      .finally(() => { btnIngresar.disabled = false; });
  }

  // ---------------------------------------------------------------------
  // Paso 2: PIN
  // ---------------------------------------------------------------------

  function renderPin() {
    pinBoxes.forEach((box, i) => {
      box.textContent = pin[i] || '';
      box.classList.toggle('pc-op-pin-box-filled', i < pin.length);
      box.classList.toggle('pc-op-pin-box-active', i === pin.length);
    });
  }

  function showMsgPin(text) {
    pinMsgEl.textContent = text;
    pinMsgEl.classList.toggle('pc-op-msg-visible', !!text);
  }

  function shakePin() {
    pinModal.classList.add('pc-op-shake');
    setTimeout(() => pinModal.classList.remove('pc-op-shake'), 350);
  }

  function obtenerUbicacionGPS() {
    if (!navigator.geolocation) return Promise.resolve(null);
    return new Promise(resolve => {
      navigator.geolocation.getCurrentPosition(
        posicion => resolve({
          lat: posicion.coords.latitude,
          lon: posicion.coords.longitude,
          precision_m: posicion.coords.accuracy
        }),
        () => resolve(null),
        { enableHighAccuracy: true, timeout: 6000, maximumAge: 0 }
      );
    });
  }

  async function registrarContextoSesion() {
    // Llamar geolocation antes de otros awaits para que el navegador pueda
    // asociar la solicitud al gesto reciente del usuario.
    const gpsPendiente = obtenerUbicacionGPS();
    let deviceId = 'N/A';
    let deviceNombre = 'N/A';
    let modelo = navigator.userAgent || 'N/A';
    try {
      deviceId = DeviceTracking.getDeviceId();
      deviceNombre = DeviceTracking.getDeviceNombre();
      modelo = await DeviceTracking.getDeviceModelo();
    } catch (e) { /* continuar con el agente del navegador si falla el almacenamiento local */ }
    const gps = await gpsPendiente;
    const datos = new URLSearchParams({
      device_id: deviceId,
      device_nombre: deviceNombre,
      device_modelo: modelo,
      gps_lat: gps ? String(gps.lat) : '',
      gps_lon: gps ? String(gps.lon) : '',
      gps_precision_m: gps ? String(gps.precision_m) : ''
    });
    const respuesta = await fetch('ajax_registrar_contexto_sesion.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: datos
    });
    if (!respuesta.ok) throw new Error('No se pudo registrar el dispositivo.');
  }

  function abrirModalPin() {
    pin = '';
    pinNombreEl.textContent = nombreOperario;
    renderPin();
    showMsgPin('');
    pinOverlay.style.display = 'flex';
  }

  function cerrarModalPin() {
    pinOverlay.style.display = 'none';
    pin = '';
    dni = '';
    render();
    showMsg('');
  }

  keypadPin.querySelectorAll('.pc-op-key[data-key]').forEach(btn => {
    btn.addEventListener('click', () => {
      if (pin.length >= MAX_LEN_PIN) return;
      showMsgPin('');
      pin += btn.dataset.key;
      renderPin();
      if (pin.length === MAX_LEN_PIN) validarPin();
    });
  });

  btnPinBorrar.addEventListener('click', () => { pin = pin.slice(0, -1); renderPin(); showMsgPin(''); });
  btnPinLimpiar.addEventListener('click', () => { pin = ''; renderPin(); showMsgPin(''); });
  btnIngresarPin.addEventListener('click', validarPin);
  btnCancelarPin.addEventListener('click', cerrarModalPin);

  function validarPin() {
    if (pin.length !== MAX_LEN_PIN) {
      showMsgPin('Ingresa los 4 dígitos de tu PIN.');
      shakePin();
      return;
    }

    btnIngresarPin.disabled = true;

    fetch('ajax_verificar_pin_operario.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'dni=' + encodeURIComponent(dni) + '&pin=' + encodeURIComponent(pin)
    })
      .then(res => res.json())
      .then(async data => {
        if (data.success) {
          btnIngresarPin.disabled = true;
          showMsgPin('Registrando el dispositivo…');
          try { await registrarContextoSesion(); } catch (e) { /* el GPS es opcional; no impedir el ingreso */ }
          window.location.href = 'panel.php';
        } else {
          showMsgPin(data.error || 'PIN incorrecto.');
          shakePin();
          pin = '';
          renderPin();
        }
      })
      .catch(() => {
        showMsgPin('Error de conexión. Intenta nuevamente.');
        shakePin();
      })
      .finally(() => { btnIngresarPin.disabled = false; });
  }

  render();
})();
