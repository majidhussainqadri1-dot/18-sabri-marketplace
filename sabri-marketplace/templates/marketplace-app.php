<?php defined('ABSPATH') || exit; $smp_shell_active = SMP_Integrations::shell_active(); ?>
<div id="smp-marketplace-root" class="smp-marketplace-root<?php echo $smp_shell_active ? ' smp-shell-integrated' : ''; ?>" data-version="<?php echo esc_attr(SMP_VERSION); ?>">
    <noscript><div class="smp-alert">JavaScript is required to use Marketplace.</div></noscript>
    <header class="smp-topbar" aria-label="Marketplace tools">
        <?php if (!$smp_shell_active): ?>
            <a class="smp-brand" href="<?php echo esc_url(SMP_Activator::marketplace_url()); ?>" aria-label="Marketplace home"><span class="smp-brand-mark">M</span><span><strong>Marketplace</strong><small>Direct buyer–seller deals</small></span></a>
        <?php else: ?>
            <div class="smp-context-title"><strong>Marketplace</strong><small>Direct buyer–seller deals</small></div>
        <?php endif; ?>
        <form id="smp-search-form" class="smp-search-form" role="search"><label class="screen-reader-text" for="smp-search-input">Search Marketplace</label><select id="smp-search-category" aria-label="Category"><option value="">All categories</option></select><input id="smp-search-input" type="search" placeholder="Search products, services, stores and brands" autocomplete="off"><button type="submit">Search</button></form>
        <div class="smp-top-actions">
            <button class="smp-icon-button" data-view="saved" title="Saved listings" aria-label="Saved listings">♡<span id="smp-wishlist-count" class="smp-count" hidden></span></button>
            <button class="smp-icon-button" data-view="chats" title="Chats" aria-label="Marketplace chats">💬<span id="smp-chat-count" class="smp-count" hidden></span></button>
            <?php if (!$smp_shell_active): ?><a class="smp-icon-button" href="<?php echo esc_url(SMP_Integrations::notifications_url()); ?>" title="Notifications" aria-label="Unified notifications">🔔</a><?php endif; ?>
            <?php if (is_user_logged_in()): ?>
                <button class="smp-account-button" data-view="account"><img src="<?php echo esc_url(get_avatar_url(get_current_user_id(), ['size'=>80])); ?>" alt=""><span>Account</span></button>
            <?php else: ?>
                <a class="smp-login-button" href="<?php echo esc_url(wp_login_url(SMP_Activator::marketplace_url())); ?>">Log In</a>
            <?php endif; ?>
        </div>
    </header>
    <nav class="smp-nav" aria-label="Marketplace sections">
        <button class="is-active" data-view="home">Marketplace Home</button><button data-view="categories">Categories</button><button data-view="services">Services</button><button data-view="used">Used Products</button><button data-view="wholesale">Wholesale</button><button data-view="sell">Sell</button><button data-view="chats">My Chats</button><button data-view="saved">Saved</button><button data-view="store">My Store</button><button data-view="help">Help</button>
    </nav>
    <main class="smp-main">
        <section id="smp-hero" class="smp-hero">
            <div><span class="smp-kicker">DIRECT-DEAL MARKETPLACE</span><h1>Find the product, speak directly, and agree the deal together.</h1><p>Buyers and sellers communicate through internal chat, phone and WhatsApp. Price, payment, inspection, pickup and delivery are decided directly by the parties.</p><div class="smp-hero-actions"><button class="smp-button smp-button-primary" data-view="categories">Browse Listings</button><button class="smp-button smp-button-light" data-view="sell">Start Selling</button></div></div>
            <div class="smp-hero-panel"><div><strong>Internal chat</strong><span>Text, photos, files and voice notes</span></div><div><strong>Phone & WhatsApp</strong><span>Verified central contact channels</span></div><div><strong>Direct agreement</strong><span>Offer, pickup and delivery discussion</span></div><div><strong>Safety controls</strong><span>Verification, block, report and moderation</span></div></div>
        </section>
        <section class="smp-direct-notice"><strong>Important:</strong> <span id="smp-direct-disclaimer">The platform connects buyers and sellers; it does not receive or hold transaction funds.</span></section>
        <section id="smp-view" class="smp-view" aria-live="polite"><div class="smp-loading"><span></span><p>Loading Marketplace…</p></div></section>
    </main>
    <nav class="smp-mobile-nav" aria-label="Marketplace mobile navigation"><button data-view="home">⌂<span>Home</span></button><button data-view="categories">▦<span>Categories</span></button><button data-view="sell" class="smp-mobile-sell">＋<span>Sell</span></button><button data-view="chats">💬<span>Chats</span></button><button data-view="account">●<span>Account</span></button></nav>
    <div id="smp-modal" class="smp-modal" hidden><div class="smp-modal-backdrop" data-close-modal></div><section class="smp-modal-card" role="dialog" aria-modal="true" aria-labelledby="smp-modal-title" tabindex="-1"><header><h2 id="smp-modal-title">Marketplace</h2><button class="smp-icon-button" data-close-modal aria-label="Close dialog">×</button></header><div id="smp-modal-body" class="smp-modal-body"></div></section></div>
    <div id="smp-toast-container" class="smp-toast-container" aria-live="assertive"></div>
</div>
