<?php
$footerLang = isset($lang) && is_string($lang) && $lang !== '' ? $lang : 'it';
$footerYear = date('Y');
?>

<link rel="stylesheet" href="/assets/global/footer.css?v=2.3">

<footer class="modern-footer fadeup" id="siteFooter">
    <div class="footer-content">
        <div class="footer-section footer-brand-section">
            <a href="/<?= htmlspecialchars($footerLang, ENT_QUOTES, 'UTF-8') ?>/home" class="footer-brand" aria-label="Cripsum home">
                <img src="/img/amongus.jpg" alt="Cripsum™" class="footer-logo" loading="lazy">
                <span>
                    <strong class="footer-title">Cripsum™</strong>
                    <small>Il sito da fichi</small>
                </span>
            </a>

            <p class="footer-description">
                Profili, chat, lootbox, meme e minchiate. Tutto nello stesso sito.
            </p>
        </div>

        <div class="footer-section">
            <h6 class="footer-subtitle">Link utili</h6>
            <ul class="footer-links">
                <li><a href="/<?= htmlspecialchars($footerLang, ENT_QUOTES, 'UTF-8') ?>/privacy" class="footer-link">Privacy</a></li>
                <li><a href="/<?= htmlspecialchars($footerLang, ENT_QUOTES, 'UTF-8') ?>/tos" class="footer-link">Termini</a></li>
                <li><a href="/<?= htmlspecialchars($footerLang, ENT_QUOTES, 'UTF-8') ?>/supporto" class="footer-link">Supporto</a></li>
                <li><a href="/<?= htmlspecialchars($footerLang, ENT_QUOTES, 'UTF-8') ?>/chat-policy" class="footer-link">Regolamento chat</a></li>
            </ul>
        </div>

        <div class="footer-section">
            <h6 class="footer-subtitle">Social</h6>
            <div class="footer-social" aria-label="Social Cripsum">
                <a href="https://www.tiktok.com/@cripsum" class="footer-social-link" title="TikTok" aria-label="TikTok" target="_blank" rel="noopener">
                    <i class="fab fa-tiktok"></i>
                </a>
                <a href="https://www.instagram.com/cripsum/" class="footer-social-link" title="Instagram" aria-label="Instagram" target="_blank" rel="noopener">
                    <i class="fab fa-instagram"></i>
                </a>
                <a href="https://discord.gg/XdheJHVURw" class="footer-social-link" title="Discord" aria-label="Discord" target="_blank" rel="noopener">
                    <i class="fab fa-discord"></i>
                </a>
                <a href="https://t.me/cripsum" class="footer-social-link" title="Telegram" aria-label="Telegram" target="_blank" rel="noopener">
                    <i class="fab fa-telegram"></i>
                </a>
            </div>

            <button type="button" class="footer-top-button" data-footer-back-top>
                <i class="fas fa-arrow-up"></i>
                <span>Torna su</span>
            </button>
        </div>
    </div>

    <div class="footer-bottom">
        <p class="footer-copyright">
            © 2021-<?= htmlspecialchars($footerYear, ENT_QUOTES, 'UTF-8') ?> Cripsum™. Tutti i diritti riservati.
        </p>
    </div>
</footer>

<script src="/assets/global/footer.js?v=2.2-classic" defer></script>
