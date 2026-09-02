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
      .then(data => {
        if (data.success) {
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