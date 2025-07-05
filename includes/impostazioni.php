           <div class="modal fade" id="impostazioniModal" tabindex="-1" aria-labelledby="impostazioniModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content bgimpostazioni">
                        <div class="modal-header">
                            <h5 class="modal-title testobianco" id="impostazioniModalLabel">Impostazioni</h5>
                            <!--<button type="button" class="btn-close tastobianco" data-bs-dismiss="modal" aria-label="Close" onclick="close_disclaimer(1)" style="color: #ffffff"></button>-->
                        </div>
                        <div class="modal-body text-center">
                            <div data-mdb-input-init class="form-outline selezione selezione-lingua">
                                <label class="form-label testobianco" for="registerName">Selezione Lingua</label>
                                <select class="animate slideIn" style="max-width: 30%; margin-left: 10px">
                                    <option selected id="1">🇮🇹 Ita</option>
                                    <option id="2">🇬🇧 Eng</option>
                                </select>
                            </div>

                            <!--TODO: cambiare emoji lingua con degli svg fatti bene (porco dio)-->

                            <div data-mdb-input-init class="form-outline selezione selezione-tema" style="margin-top: 30px">
                                <label class="form-label testobianco" for="registerName">Selezione Tema</label>
                                <select class="animate slideIn listatemi" style="max-width: 30%; margin-left: 15px">
                                    <option selected value="1">Scuro</option>
                                    <option value="2">Chiaro</option>
                                    <option value="3">OG</option>
                                </select>
                                <br />
                                <button type="button" class="btn btn-secondary bottone mt-4" data-bs-dismiss="modal" onclick="cancellaCookies()">Cancella cookies</button>
                                <br />
                                <button type="button" class="btn btn-secondary bottone mt-4" data-bs-dismiss="modal" onclick="cancellaDati()">Cancella dati</button>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary bottone" data-bs-dismiss="modal" onclick="salvaImpostazioni()">Salva Preferenze</button>
                        </div>
                    </div>
                </div>
            </div>