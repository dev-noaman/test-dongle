<?php
/**
 * View Router
 *
 * Routes to the appropriate view based on $_REQUEST['view'] parameter.
 * Loads module CSS and JS assets.
 */

// Get current view (default: dashboard)
$view = $_REQUEST['view'] ?? 'dashboard';

// Sanitize view name - only allow alphanumeric and underscore
$view = preg_replace('/[^a-z0-9_]/', '', strtolower($view));

// List of valid views
$validViews = [
    'dashboard',
    'sms_inbox',
    'sms_outbox',
    'sms_send',
    'ussd',
    'dongles',
    'reports',
    'logs',
];

// Default to dashboard if invalid view requested
if (!in_array($view, $validViews)) {
    $view = 'dashboard';
}

// Module asset base path (relative to FreePBX admin root)
$moduleBase = 'modules/donglemanager/assets';

// Base URL for navigation links (preserves current host and query base)
$navBase = 'config.php?display=donglemanager';

// Nav items: id => [label, icon]
$navItems = [
    'dashboard'  => [_('Dashboard'), 'fa-tachometer-alt'],
    'sms_inbox'  => [_('SMS Inbox'), 'fa-inbox'],
    'sms_outbox' => [_('SMS Outbox'), 'fa-paper-plane'],
    'sms_send'   => [_('Send SMS'), 'fa-paper-plane'],
    'ussd'       => [_('USSD'), 'fa-mobile-alt'],
    'dongles'    => [_('Dongles'), 'fa-broadcast-tower'],
    'reports'    => [_('Reports'), 'fa-chart-bar'],
    'logs'       => [_('Logs'), 'fa-list-alt'],
];

// Check if this is an AJAX request for view content only
$isAjaxView = isset($_REQUEST['ajax_view']) && $_REQUEST['ajax_view'] === '1';

// For AJAX view requests, only output the view content (no assets/navbar)
if ($isAjaxView) {
    $viewFile = __DIR__ . '/' . $view . '.php';
    if (file_exists($viewFile)) {
        include $viewFile;
    } else {
        echo '<div class="alert alert-danger">View not found: ' . htmlspecialchars($view) . '</div>';
    }
    return; // Stop here for AJAX requests
}
?>

<!-- Dongle Manager Module Assets -->
<!-- Preload critical fonts for faster rendering -->
<link rel="preload" href="<?php echo $moduleBase; ?>/vendor/fontawesome/webfonts/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?php echo $moduleBase; ?>/vendor/fontawesome/webfonts/fa-regular-400.woff2" as="font" type="font/woff2" crossorigin>

<!-- Stylesheets (non-blocking) -->
<link rel="stylesheet" href="<?php echo $moduleBase; ?>/css/donglemanager.css">
<link rel="stylesheet" href="<?php echo $moduleBase; ?>/vendor/fontawesome/css/all.min.css">

<!-- Scripts - donglemanager.js loads immediately (needed by inline scripts), Chart.js deferred -->
<script src="<?php echo $moduleBase; ?>/js/donglemanager.js"></script>
<script src="<?php echo $moduleBase; ?>/vendor/chart.umd.min.js" defer></script>

<?php
// Output CSRF token for JavaScript
$csrfToken = $this->getCsrfToken();
echo '<script>var DM_CSRF_TOKEN = ' . json_encode($csrfToken) . '; var DM_CURRENT_VIEW = ' . json_encode($view) . ';</script>';
?>

<!-- Horizontal Navigation Bar -->
<nav class="dm-navbar navbar navbar-default" role="navigation">
    <div class="container-fluid">
        <ul class="nav navbar-nav dm-navbar-nav" id="dm-main-nav">
            <?php foreach ($navItems as $navId => $item): ?>
            <li class="<?php echo $view === $navId ? 'active' : ''; ?>" data-view="<?php echo $navId; ?>">
                <a href="<?php echo $navBase . '&view=' . $navId; ?>" data-view="<?php echo $navId; ?>">
                    <i class="fa <?php echo htmlspecialchars($item[1]); ?>"></i>
                    <?php echo htmlspecialchars($item[0]); ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="dm-nav-loading" id="dm-nav-loading" style="display:none;">
            <i class="fa fa-spinner fa-spin"></i> Loading...
        </div>
    </div>
</nav>

<!-- View Content Container -->
<div id="dm-view-container">
<?php
// Include the view file
$viewFile = __DIR__ . '/' . $view . '.php';
if (file_exists($viewFile)) {
    include $viewFile;
} else {
    echo '<div class="alert alert-danger">View not found: ' . htmlspecialchars($view) . '</div>';
}
?>
</div>

<!-- AJAX Navigation Script -->
<script>
(function($) {
    'use strict';

    // Current view tracking
    var currentView = DM_CURRENT_VIEW || 'dashboard';
    var isLoading = false;

    // Initialize AJAX navigation
    $(document).ready(function() {
        // Pre-fetch dongle list into cache
        DM.fetchDongles(function(dongles) {
            DM.populateDongleSelectors(dongles);
        });

        // Use event delegation for nav links
        $('#dm-main-nav').on('click', 'a[data-view]', function(e) {
            var targetView = $(this).data('view');

            // Skip if already on this view or currently loading
            if (targetView === currentView || isLoading) {
                e.preventDefault();
                return false;
            }

            // Load view via AJAX
            loadView(targetView);
            e.preventDefault();
            return false;
        });

        // Handle browser back/forward buttons
        $(window).on('popstate', function(e) {
            if (e.originalEvent.state && e.originalEvent.state.view) {
                loadView(e.originalEvent.state.view, false);
            }
        });

        // Push initial state to history
        if (history.replaceState) {
            history.replaceState({ view: currentView }, document.title);
        }
    });

    /**
     * Load a view via AJAX
     *
     * @param {string} view - View name to load
     * @param {boolean} pushState - Whether to push to browser history
     */
    function loadView(view, pushState = true) {
        if (isLoading) return;
        isLoading = true;

        // Show loading indicator
        $('#dm-nav-loading').show();

        // Update active nav item immediately for visual feedback
        $('#dm-main-nav li').removeClass('active');
        $('#dm-main-nav li[data-view="' + view + '"]').addClass('active');

        // Stop any running auto-refresh intervals
        if (typeof DM.stopAllAutoRefresh === 'function') {
            DM.stopAllAutoRefresh();
        }

        // Fetch view content
        $.ajax({
            url: 'config.php',
            type: 'GET',
            data: {
                display: 'donglemanager',
                view: view,
                ajax_view: 1
            },
            dataType: 'html',
            timeout: 30000
        })
        .done(function(html) {
            // Update view container
            $('#dm-view-container').html(html);

            // Update current view
            currentView = view;
            DM_CURRENT_VIEW = view;

            // Update URL and history
            if (pushState && history.pushState) {
                var newUrl = 'config.php?display=donglemanager&view=' + view;
                history.pushState({ view: view }, document.title, newUrl);
            }

            // Initialize page-specific functionality
            if (typeof DM.initPage === 'function') {
                DM.initPage(view);
            }

            // Initialize dashboard if that's the view
            if (view === 'dashboard' && typeof DM.initDashboard === 'function') {
                DM.initDashboard();
            }

            // Populate dongle selectors in the newly loaded view
            if (DM.dongleCache !== null) {
                DM.populateDongleSelectors(DM.dongleCache);
            } else {
                DM.fetchDongles(function(dongles) {
                    DM.populateDongleSelectors(dongles);
                });
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Failed to load view:', view, error);
            DM.toast('Failed to load page: ' + (error || status), 'error');
        })
        .always(function() {
            isLoading = false;
            $('#dm-nav-loading').hide();
        });
    }

    // Expose loadView function globally for external use
    DM.loadView = loadView;

})(jQuery);
</script>
