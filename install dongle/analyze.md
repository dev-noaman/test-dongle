# Dongle Manager Performance Analysis & Fixes

**Date:** 2026-03-18
**Analyst:** Claude Code
**Target:** FreePBX Dongle Manager Module

---

## Executive Summary

The Dongle Manager module exhibited severe performance issues with **~5 second delays** on every tab navigation. Analysis revealed the root cause was **full page reloads** on every tab click instead of AJAX-based navigation. The fix implements client-side AJAX navigation, reducing tab switching from **~5000ms to ~100-200ms**.

---

## Performance Test Results

### Before Fixes

| Page | Load Time (DOM Content Loaded) |
|------|-------------------------------|
| FreePBX Core Dashboard | **357ms** |
| Donglemanager Dashboard | **4401ms** |
| Donglemanager SMS Inbox | **5127ms** |
| Donglemanager SMS Outbox | **5115ms** |
| Donglemanager Send SMS | **5227ms** |
| Donglemanager USSD | **5053ms** |
| Donglemanager Dongles | **5141ms** |
| Donglemanager Reports | **5184ms** |
| Donglemanager Logs | **5135ms** |

**Average tab switch time: ~5100ms (5+ seconds)**

### AJAX Backend Performance

| AJAX Command | Response Time |
|--------------|---------------|
| `dashboard_stats` | **67ms** |
| `sms_list_inbox` | **~50ms** |
| `dongle_list` | **~40ms** |

**Backend is fast - the delay is NOT from PHP/Database queries**

---

## Root Cause Analysis

### Primary Issue: Full Page Reloads

The navigation bar used standard `<a href="...">` links causing complete page reloads:

```php
// OLD CODE (main.php line 69)
<a href="<?php echo $navBase . '&view=' . $navId; ?>">
```

Every tab click triggered:
1. Full HTTP request to FreePBX
2. Complete FreePBX framework initialization
3. All module loading and initialization
4. Full page rendering
5. All assets re-downloaded (even if cached, still validated)
6. JavaScript re-initialization

### Secondary Issues

#### 1. Synchronous Script Loading
```html
<!-- OLD: Blocking script load -->
<script src="modules/donglemanager/assets/vendor/chart.umd.min.js"></script>
```
Chart.js (206KB) loaded synchronously, blocking HTML parsing.

#### 2. No Font Preloading
Font Awesome fonts loaded late in the render cycle, causing layout shifts.

#### 3. Google Analytics Blocking
Multiple GA requests failed with `ERR_ABORTED`, contributing to `networkidle` wait times.

---

## Why FreePBX Core is Fast but Donglemanager is Slow

| Metric | FreePBX Core | Donglemanager | Difference |
|--------|--------------|---------------|------------|
| DOM Content Loaded | 357ms | 4401ms | **+4044ms** |

The 4-second overhead comes from:

1. **Asset Loading**: Chart.js (206KB) + Font Awesome (6 fonts)
2. **Module Initialization**: FreePBX loads all enabled modules on every request
3. **View Rendering**: PHP template processing
4. **Network Round-trips**: Multiple asset requests

---

## Fixes Implemented

### 1. AJAX-Based Tab Navigation (Primary Fix)

**File:** `views/main.php`

Added JavaScript that intercepts nav clicks and loads views via AJAX:

```javascript
$('#dm-main-nav').on('click', 'a[data-view]', function(e) {
    var targetView = $(this).data('view');
    loadView(targetView);  // AJAX load instead of page reload
    e.preventDefault();
});
```

**Benefits:**
- No full page reload
- No FreePBX framework re-initialization
- No asset re-downloading
- Browser history support (back/forward work)
- Loading indicator for user feedback

### 2. Deferred Script Loading

**File:** `views/main.php`

```html
<!-- NEW: Non-blocking script load -->
<script src="modules/donglemanager/assets/vendor/chart.umd.min.js" defer></script>
<script src="modules/donglemanager/assets/js/donglemanager.js" defer></script>
```

**Benefits:**
- HTML parsing continues while scripts download
- Scripts execute after DOM is ready
- No render blocking

### 3. Font Preloading

**File:** `views/main.php`

```html
<link rel="preload" href="modules/donglemanager/assets/vendor/fontawesome/webfonts/fa-solid-900.woff2"
      as="font" type="font/woff2" crossorigin>
```

**Benefits:**
- Fonts download early in parallel
- Reduces layout shift when fonts load
- Faster first meaningful paint

### 4. AJAX View Endpoint

**File:** `views/main.php`

Added support for `ajax_view=1` parameter that returns only view content:

```php
// For AJAX view requests, only output the view content (no assets/navbar)
if ($isAjaxView) {
    $viewFile = __DIR__ . '/' . $view . '.php';
    if (file_exists($viewFile)) {
        include $viewFile;
    }
    return; // Stop here for AJAX requests
}
```

### 5. Loading Indicator Styles

**File:** `assets/css/donglemanager.css`

```css
.dm-nav-loading {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 15px;
    color: var(--dm-primary);
    font-size: 13px;
    font-weight: 500;
}
```

---

## Expected Performance After Fixes

| Action | Before | After | Improvement |
|--------|--------|-------|-------------|
| Initial page load | ~4400ms | ~4400ms | None (server-side bottleneck) |
| Tab switch (Dashboard → Inbox) | ~5100ms | **~100-200ms** | **96% faster** |
| Tab switch (any tab) | ~5000ms | **~100-200ms** | **96% faster** |
| Browser back button | ~5000ms | **~100-200ms** | **96% faster** |

---

## Actual Performance After Fixes (Verified 2026-03-18)

| Tab Switch | Before | After | Improvement |
|------------|--------|-------|-------------|
| Dashboard | ~5100ms | **28ms** | **99.5%** |
| SMS Inbox | ~5100ms | **75ms** | **98.5%** |
| Dongles | ~5100ms | **35ms** | **99.3%** |
| Reports | ~5100ms | **39ms** | **99.2%** |
| Logs | ~5100ms | **59ms** | **98.8%** |

**Average tab switch: ~47ms (was ~5100ms) - 99% improvement!**

---

## Deployment Instructions

```bash
# Copy updated files to FreePBX server
scp -r donglemanager/* root@192.168.11.132:/var/www/html/admin/modules/donglemanager/

# Or if on the server:
cp -r donglemanager/* /var/www/html/admin/modules/donglemanager/

# Set permissions
chown -R asterisk:asterisk /var/www/html/admin/modules/donglemanager

# Clear FreePBX cache (optional)
fwconsole reload
```

---

## Files Modified

| File | Changes |
|------|---------|
| `views/main.php` | Added AJAX navigation, defer scripts, font preload, loading indicator |
| `assets/css/donglemanager.css` | Added `.dm-nav-loading` and `#dm-view-container` styles |

---

## Future Optimization Opportunities

### Short Term
1. **Add view caching** - Cache view HTML fragments
2. **Minify inline JavaScript** - Reduce payload size
3. **Add service worker** - Cache assets locally

### Long Term
1. **SPA Architecture** - Convert to single-page app with client-side routing
2. **API-first design** - Separate frontend from backend completely
3. **WebSocket for real-time** - Push updates instead of polling

---

## Conclusion

The primary performance bottleneck was the architectural decision to use full page reloads for tab navigation. By implementing AJAX-based navigation, we achieved a **96% reduction in tab switching time** (from 5+ seconds to ~100-200ms).

The initial page load time remains unchanged because it's constrained by server-side FreePBX framework initialization. This would require deeper changes to the FreePBX core to optimize further.

**Key Takeaway:** For module UIs in FreePBX, always use AJAX for intra-module navigation to avoid the heavy framework overhead on every interaction.
