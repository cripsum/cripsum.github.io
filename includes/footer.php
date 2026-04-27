<?php
$footerLang = isset($lang) && is_string($lang) && $lang !== '' ? $lang : 'it';
$footerYear = date('Y');

$footerLinks = [
    ['href' => "/{$footerLang}/home", 'label' => 'Home'],
    ['href' => "/{$footerLang}/bio", 'label' => 'Bio'],
    ['href' => "/{$footerLang}/global-chat", 'label' => 'Chat Globale'],
    ['href' => "/{$footerLang}/shitpost", 'label' => 'Shitpost'],
    ['href' => "/{$footerLang}/lootbox", 'label' => 'Lootbox'],
];

$footerLegalLinks = [
    ['href' => "/{$footerLang}/privacy", 'label' => 'Privacy'],
    ['href' => "/{$footerLang}/tos", 'label' => 'Termini'],
    ['href' => "/{$footerLang}/supporto", 'label' => 'Supporto'],
    ['href' => "/{$footerLang}/chat-policy", 'label' => 'Regole chat'],
];

$footerSocialLinks = [
    ['href' => 'https://www.tiktok.com/@cripsum', 'label' => 'TikTok', 'icon' => 'fab fa-tiktok'],
    ['href' => 'https://www.instagram.com/cripsum/', 'label' => 'Instagram', 'icon' => 'fab fa-instagram'],
    ['href' => 'https://discord.gg/XdheJHVURw', 'label' => 'Discord', 'icon' => 'fab fa-discord'],
    ['href' => 'https://t.me/cripsum', 'label' => 'Telegram', 'icon' => 'fab fa-telegram'],
];
?>

<link rel="stylesheet" href="/assets/global/footer.css?v=2.1-neutral">

<footer class="cripsum-footer" id="siteFooter">
    <div class="cripsum-footer__glow" aria-hidden="true"></div>

    <div class="cripsum-footer__inner">
        <section class="cripsum-footer__brand">
            <a href="/<?= htmlspecialchars($footerLang, ENT_QUOTES, 'UTF-8') ?>/home" class="cripsum-footer__logo" aria-label="Cripsum home">
                <span class="cripsum-footer__mark">C</span>
                <span>
                    <strong>Cripsum™</strong>
                    <small>Cripsum</small>
                </span>
            </a>

            <p>Un angolo del sito per profili, chat, lootbox, meme e roba varia.</p>
        </section>

        <nav class="cripsum-footer__section" aria-label="Link principali">
            <h2>Sito</h2>
            <ul>
                <?php foreach ($footerLinks as $item): ?>
                    <li>
                        <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <nav class="cripsum-footer__section" aria-label="Link legali e supporto">
            <h2>Info</h2>
            <ul>
                <?php foreach ($footerLegalLinks as $item): ?>
                    <li>
                        <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <section class="cripsum-footer__section">
            <h2>Social</h2>
            <div class="cripsum-footer__socials" aria-label="Social Cripsum">
                <?php foreach ($footerSocialLinks as $item): ?>
                    <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
                       target="_blank"
                       rel="noopener"
                       aria-label="<?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>"
                       title="<?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>">
                        <i class="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="cripsum-footer__bottom">
        <p>© 2021-<?= htmlspecialchars($footerYear, ENT_QUOTES, 'UTF-8') ?> Cripsum™. Tutti i diritti riservati.</p>

        <button type="button" class="cripsum-footer__top" data-cripsum-back-top aria-label="Torna su">
            <i class="fas fa-arrow-up"></i>
        </button>
    </div>
</footer>

<script src="/assets/global/footer.js?v=2.1-neutral" defer></script>
