/*
 * Validaciones inmediatas en el navegador (Bootstrap + Constraint Validation API).
 * Importante: todas las reglas se repiten en PHP; esto solo mejora la experiencia de uso.
 *
 * Uso: <form class="needs-validation"> con campos que tengan atributos HTML5
 * (required, pattern, min, maxlength...) y un <div class="invalid-feedback"></div>
 * a continuación. El atributo data-msg define el mensaje de error del campo.
 * Un formulario con data-movimiento valida además que una salida no supere el stock.
 */
(function () {
  'use strict';

  function mensaje(campo) {
    return campo.dataset.msg || campo.validationMessage || 'Valor inválido.';
  }

  function validarCampo(campo) {
    if (campo.type === 'hidden' || campo.disabled || campo.readOnly) { return true; }
    var ok = campo.checkValidity();
    campo.classList.toggle('is-invalid', !ok);
    campo.classList.toggle('is-valid', ok);
    var feedback = campo.parentElement.querySelector('.invalid-feedback');
    if (feedback) { feedback.textContent = ok ? '' : mensaje(campo); }
    return ok;
  }

  // Regla extra de movimientos: una salida no puede dejar el stock bajo cero
  function validarStockSalida(form) {
    var producto = form.querySelector('[name="id_producto"]');
    var tipo = form.querySelector('[name="tipo"]');
    var cantidad = form.querySelector('[name="cantidad"]');
    if (!producto || !tipo || !cantidad) { return; }

    var opcion = producto.options[producto.selectedIndex];
    var stock = opcion && opcion.dataset.stock !== undefined ? parseInt(opcion.dataset.stock, 10) : null;
    var pedido = parseInt(cantidad.value, 10);

    if (tipo.value === 'salida' && stock !== null && !isNaN(pedido) && pedido > stock) {
      cantidad.setCustomValidity('Stock insuficiente: hay ' + stock + ' unidad(es) disponibles.');
      cantidad.dataset.msg = 'Stock insuficiente: hay ' + stock + ' unidad(es) disponibles.';
    } else {
      cantidad.setCustomValidity('');
      cantidad.dataset.msg = 'Ingresa un entero mayor a 0.';
    }
  }

  document.querySelectorAll('form.needs-validation').forEach(function (form) {
    form.noValidate = true; // se usa la validación propia, con mensajes de Bootstrap
    var campos = form.querySelectorAll('input, select, textarea');
    var esMovimiento = form.hasAttribute('data-movimiento');

    campos.forEach(function (campo) {
      var evento = (campo.tagName === 'SELECT' || campo.type === 'checkbox') ? 'change' : 'input';
      campo.addEventListener(evento, function () {
        if (esMovimiento) { validarStockSalida(form); }
        if (campo.classList.contains('is-invalid') || campo.classList.contains('is-valid')) {
          validarCampo(campo);
        }
        if (esMovimiento) {
          var cant = form.querySelector('[name="cantidad"]');
          if (cant.value !== '') { validarCampo(cant); }
        }
      });
    });

    form.addEventListener('submit', function (ev) {
      if (esMovimiento) { validarStockSalida(form); }
      var primeroInvalido = null;
      campos.forEach(function (campo) {
        if (!validarCampo(campo) && !primeroInvalido) { primeroInvalido = campo; }
      });
      if (primeroInvalido) {
        ev.preventDefault();
        ev.stopPropagation();
        primeroInvalido.focus();
      }
    });
  });
})();
