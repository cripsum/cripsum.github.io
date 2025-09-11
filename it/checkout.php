<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <?php include '../includes/head-import.php'; ?>
        <title>Cripsum™ - checkout</title>
    </head>
    <body>
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/impostazioni.php'; ?>
        
        <div style="max-width: 1920px; margin: auto; padding-top: 7rem; padding-bottom: 4rem;" class="testobianco">
            <div class="containerCheckout">
                <div class="py-5 text-center">
                    <h2 class="fs-1 fadeup" style="font-weight: bold">Checkout</h2>
                </div>
                <div class="order-md-1">
                    <h4 class="mb-3 fadeup">Indirizzo di pagamento</h4>
                        <div class="row">
                            <div class="col-md-6 mb-3 fadeup">
                                <label for="firstName">Nome</label>
                                <input type="text" class="form-control" id="firstName" placeholder="" value="" required="" />
                                <div class="invalid-feedback">è richiesto un nome valido.</div>
                            </div>
                            <div class="col-md-6 mb-3 fadeup">
                                <label for="lastName">Cognome</label>
                                <input type="text" class="form-control" id="lastName" placeholder="" value="" required="" />
                                <div class="invalid-feedback">è richiesto un cognome valido.</div>
                            </div>
                        </div>
                        <div class="mb-3 fadeup">
                            <label for="username">Username</label>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" id="username" placeholder="Username" aria-label="Username" required="" aria-describedby="basic-addon1">
                              </div>
                                <div class="invalid-feedback" style="width: 100%">è richiesto l'username.</div>
                            </div>
                        </div>
                        <div class="mb-3 fadeup">
                            <label for="email">Email <span class="">(opzionale)</span></label>
                            <input type="email" class="form-control" id="email" placeholder="email@esempio.com" />
                            <div class="invalid-feedback">Inserisci una email per ricevere tutti gli aggiornamenti sulla spedizione.</div>
                        </div>
                        <div class="mb-3 fadeup">
                            <label for="address">Indirizzo</label>
                            <input type="text" class="form-control" id="address" placeholder="via esempio, 1234" required="" />
                            <div class="invalid-feedback">Inserisci il tuo indirizzo di spedizione.</div>
                        </div>
                        <div class="mb-3 fadeup">
                            <label for="address2">Indirizzo 2 <span class="">(Opzionale)</span></label>
                            <input type="text" class="form-control" id="address2" placeholder="Appartamento o stanza" />
                        </div>
                        <div class="row">
                            <div class="col-md-5 mb-3 fadeup">
                                <label for="country">Stato</label>
                                <input type="text" class="form-control" id="address2" placeholder="" />
                                <div class="invalid-feedback">Insersci uno stato valido.</div>
                            </div>
                            <div class="col-md-4 mb-3 fadeup">
                                <label for="state">Regione</label>
                                <input type="text" class="form-control" id="zip" placeholder="" required="" />
                                <div class="invalid-feedback">Inserisci una regione valida.</div>
                            </div>
                            <div class="col-md-3 mb-3 fadeup">
                                <label for="zip">Codice postale</label>
                                <input type="text" class="form-control" id="zip" placeholder="" required="" />
                                <div class="invalid-feedback">è rischiesto il codice postale.</div>
                            </div>
                        </div>
                        <hr class="mb-4 fadeuphr" />
                        <div class="custom-control custom-checkbox fadeup">
                            <input type="checkbox" class="" id="same-address" />
                            <label class="form-check-label" for="same-address">l'indirizzo di spedizione è uguale all'indirizzo di pagamento</label>
                        </div>
                        <div class="custom-control custom-checkbox fadeup">
                            <input type="checkbox" class="" id="save-info" />
                            <label class="form-check-label" for="save-info">Ricorda le informazioni per i prossimi acquisti</label>
                        </div>
                        <hr class="mb-4 fadeuphr" />
                        <h4 class="mb-3 fadeup">Pagamento</h4>
                        <div class="d-block my-3 fadeup">
                            <div class="custom-control custom-radio">
                                <input id="credit" name="paymentMethod" type="radio" class="" checked="" required="" />
                                <label class="form-check-label" for="credit">Carta di credito</label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input id="debit" name="paymentMethod" type="radio" class="" required="" />
                                <label class="form-check-label" for="debit">Carta di debito</label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input id="paypal" name="paymentMethod" type="radio" class="" required="" />
                                <label class="form-check-label" for="paypal">PayPal</label>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3 fadeup">
                                <label for="cc-name">Nome sulla carta</label>
                                <input type="text" class="form-control" id="cc-name" placeholder="" required="" />
                                <div class="invalid-feedback">è richiesto il nome sulla carta</div>
                            </div>
                            <div class="col-md-6 mb-3 fadeup">
                                <label for="cc-number">Numero della carta</label>
                                <input type="text" class="form-control" id="cc-number" placeholder="" required="" />
                                <div class="invalid-feedback">è richiesto il numero della carta</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3 fadeup">
                                <label for="cc-expiration">Scadenza</label>
                                <input type="text" class="form-control" id="cc-expiration" placeholder="" required="" />
                                <div class="invalid-feedback">è richiesta la data di scadenza</div>
                            </div>
                            <div class="col-md-3 mb-3 fadeup">
                                <label for="cc-cvv">CVV</label>
                                <input type="text" class="form-control" id="cc-cvv" placeholder="" required="" />
                                <div class="invalid-feedback">codice di sicurezza richiesto</div>
                            </div>
                        </div>
                        <hr class="mb-4 fadeuphr" />
                        <a href="confirm"><button class="btn btn-secondary bottone2 btn-lg btn-block fadeup" style="width: 100%; margin: auto;">Invia e paga</button></a>
                </div>
            </div>
        </div>
        <?php include '../includes/footer.php'; ?>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/modeChanger.js"></script>
    </body>
</html>
