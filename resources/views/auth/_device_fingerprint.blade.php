{{-- Device fingerprint snippet (Phase 6). Included on signup + login.
     Collects browser/OS/screen/WebGL/timezone/hardwareConcurrency params,
     SHA-256 hashes them, and posts `device_hash` with the form. The server
     enforces one account per device and banned-device blocks. If JS is
     disabled the form still submits — the server treats a missing hash as
     "no fingerprint" rather than failing the request. --}}
<input type="hidden" name="device_hash" id="device_hash" value="">
<script>
(function () {
    function webglRenderer() {
        try {
            var canvas = document.createElement('canvas');
            var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (!gl) return 'none';
            var dbg = gl.getExtension('WEBGL_debug_renderer_info');
            return dbg ? gl.getParameter(dbg.UNMASKED_RENDERER_WEBGL) : 'masked';
        } catch (e) { return 'error'; }
    }
    async function fingerprint() {
        var parts = [
            navigator.userAgent || '',
            navigator.platform || '',
            navigator.language || '',
            screen.width + 'x' + screen.height + 'x' + screen.colorDepth,
            Intl.DateTimeFormat().resolvedOptions().timeZone || '',
            String(navigator.hardwareConcurrency || ''),
            String(navigator.deviceMemory || ''),
            webglRenderer()
        ];
        var data = new TextEncoder().encode(parts.join('|'));
        var digest = await crypto.subtle.digest('SHA-256', data);
        return Array.from(new Uint8Array(digest))
            .map(function (b) { return b.toString(16).padStart(2, '0'); })
            .join('');
    }
    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll('form[method="POST"]');
        fingerprint().then(function (hash) {
            forms.forEach(function (form) {
                var input = form.querySelector('#device_hash');
                if (input) input.value = hash;
            });
        }).catch(function () { /* no fingerprint — server allows it */ });
    });
})();
</script>
