                function cancellaCookies() {
                    if (confirm("Sei sicuro di voler cancellare tutti i cookies? Funzioni come il login, gli achievement e le preferenze potrebbero essere influenzate. Questa azione non può essere annullata.")) {
                        document.cookie.split(";").forEach(function (cookie) {
                            document.cookie = cookie.split("=")[0] + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/";
                        });
                        alert("I cookies sono stati cancellati.");
                        location.reload();
                    }
                }
                function cancellaDati() {
                    localStorage.clear();
                    sessionStorage.clear();
                    document.cookie.split(";").forEach(function (cookie) {
                        document.cookie = cookie.split("=")[0] + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/";
                    });
                    alert("Tutti i dati sono stati cancellati.");
                    location.reload();
                }

                function salvaImpostazioni() {
                    controllaLingua();
                    //controllaTema();
                }