(function () {
  const MAX_LEN = 8;
  let dni = '';

  const dots = document.querySelectorAll('#pcOpDisplay .pc-op-dot');
  const msgEl = document.getElementById('pcOpMsg');
  const cardEl = document.querySelector('.pc-op-card');
  const btnIngresar = document.getElementById('pcOpIngresar');
  const btnBorrar = document.getElementById('pcOpBorrar');
  const btnLimpiar = document.getElementById('pcOpLimpiar');

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

  document.querySelectorAll('.pc-op-key[data-key]').forEach(btn => {
    btn.addEventListener('click', () => {
      if (dni.length >= MAX_LEN) return;
      showMsg('');
      dni += btn.dataset.key;
      render();
      if (dni.length === MAX_LEN) ingresar();
    });
  });

  btnBorrar.addEventListener('click', () => { dni = dni.slice(0, -1); render(); showMsg(''); });
  btnLimpiar.addEventListener('click', () => { dni = ''; render(); showMsg(''); });
  btnIngresar.addEventListener('click', ingresar);

  function ingresar() {
    if (dni.length !== MAX_LEN) {
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
          window.location.href = 'panel.php';
        } else {
          showMsg(data.error || 'No se pudo ingresar.');
          shake();
          dni = '';
          render();
          btnIngresar.disabled = false;
        }
      })
      .catch(() => {
        showMsg('Error de conexión. Intenta nuevamente.');
        shake();
        btnIngresar.disabled = false;
      });
  }

  render();
})();