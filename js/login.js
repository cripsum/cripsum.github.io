import { Input, Tab, Ripple, initMDB } from "mdb-ui-kit";

initMDB({ Input, Tab, Ripple });

$(document).ready(function () {
    // Quando si fa clic su una scheda
    $('.nav-link').on('click', function (e) {
      e.preventDefault(); // Impedisce l'azione predefinita del collegamento

      // Rimuovi la classe 'active' da tutte le schede
      $('.nav-link').removeClass('active');

      // Aggiungi la classe 'active' alla scheda cliccata
      $(this).addClass('active');

      // Ottieni l'ID della scheda corrispondente
      var tabId = $(this).attr('href');

      // Nascondi tutte le schede
      $('.tab-pane').removeClass('show active');

      // Mostra la scheda corrispondente
      $(tabId).addClass('show active');
    });
  });