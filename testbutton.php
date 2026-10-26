<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manual Classification Buttons</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #14261b;
            --muted: #708a77;
            --line: #d9e4db;
            --brand: #2f7d43;
            --bg: #eef3ee;
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 20px;
            color: var(--ink);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            background:
                radial-gradient(700px 420px at 50% -10%, #dcefe0, transparent 65%),
                var(--bg);
        }

        .controller {
            width: min(100%, 520px);
            padding: clamp(22px, 5vw, 36px);
            border: 1px solid var(--line);
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 24px 60px -34px rgba(16, 44, 26, .45);
        }

        h1 {
            margin: 0;
            font-size: clamp(22px, 6vw, 30px);
            letter-spacing: -.02em;
        }

        .subtitle {
            margin: 7px 0 22px;
            color: var(--muted);
            font-size: 14px;
        }

        .demo-buttons {
            display: grid;
            gap: 12px;
        }

        .demo-button {
            display: flex;
            align-items: center;
            gap: 14px;
            width: 100%;
            min-height: 66px;
            padding: 12px 16px;
            border: 1px solid var(--button-color);
            border-radius: 14px;
            color: #fff;
            background: var(--button-color);
            cursor: pointer;
            font: inherit;
            font-size: 16px;
            font-weight: 800;
            text-align: left;
            transition: filter .15s ease, transform .15s ease;
        }

        .demo-button:hover,
        .demo-button:focus-visible {
            filter: brightness(1.08);
            outline: none;
            transform: translateY(-1px);
        }

        .demo-button:active {
            transform: translateY(1px);
        }

        .demo-button__key {
            display: grid;
            width: 38px;
            height: 38px;
            flex: none;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, .55);
            border-radius: 10px;
            background: rgba(255, 255, 255, .18);
            font-size: 20px;
        }

        .status {
            min-height: 22px;
            margin: 18px 0 0;
            color: var(--muted);
            font-size: 13px;
            text-align: center;
        }

        .status.is-success { color: var(--brand); font-weight: 700; }
        .status.is-error { color: #b3342f; font-weight: 700; }
    </style>
</head>
<body>
    <main class="controller">
        <h1>Manual Classification</h1>
        <p class="subtitle">Tap a category to update the monitor dashboard.</p>

        <div class="demo-buttons" aria-label="Manual classification buttons">
            <button class="demo-button" type="button" data-category="plastic_bottle" style="--button-color: #3386b8">
                <span class="demo-button__key">1</span>
                <span>Plastic Bottle (PET)</span>
            </button>
            <button class="demo-button" type="button" data-category="shredded_thin_plastic" style="--button-color: #d18a32">
                <span class="demo-button__key">2</span>
                <span>Shredded Thin Plastic (TP)</span>
            </button>
            <button class="demo-button" type="button" data-category="metal_waste" style="--button-color: #657486">
                <span class="demo-button__key">3</span>
                <span>Metal Waste (MET)</span>
            </button>
            <button class="demo-button" type="button" data-category="shredded_biodegradable" style="--button-color: #3e9b61">
                <span class="demo-button__key">4</span>
                <span>Shredded Biodegradable (BIO)</span>
            </button>
            <button class="demo-button" type="button" data-category="reject" style="--button-color: #c94b4b">
                <span class="demo-button__key">5</span>
                <span>Reject Waste</span>
            </button>
        </div>

        <p id="status" class="status" role="status">Ready to send a classification.</p>
    </main>

    <script>
        (function () {
            var statusElement = document.getElementById('status');

            function setStatus(message, className) {
                statusElement.textContent = message;
                statusElement.className = 'status ' + className;
            }

            document.querySelectorAll('.demo-button').forEach(function (button) {
                button.addEventListener('click', function () {
                    var selection = {
                        category: button.dataset.category,
                        selectedAt: new Date().toISOString()
                    };

                    setStatus('Sending selection...', '');

                    fetch('testsync.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(selection),
                        cache: 'no-store'
                    }).then(function (response) {
                        if (!response.ok) {
                            throw new Error('Server returned HTTP ' + response.status);
                        }
                        return response.json();
                    }).then(function (response) {
                        if (!response.success) {
                            throw new Error(response.error || 'The selection was rejected.');
                        }
                        setStatus('Sent. Monitor updated.', 'is-success');
                    }).catch(function (error) {
                        setStatus('Unable to update monitor.', 'is-error');
                        console.error('Classification relay error:', error);
                    });
                });
            });
        }());
    </script>
</body>
</html>
