<?php
$monitorSource = file_get_contents(__DIR__ . '/monitor.php');

if ($monitorSource === false) {
    http_response_code(500);
    echo 'Unable to load the monitor visual styles.';
    exit;
}

preg_match_all('/<style>(.*?)<\/style>/s', $monitorSource, $styleMatches);
$monitorStyles = $styleMatches[1][1] ?? '';

if ($monitorStyles === '') {
    http_response_code(500);
    echo 'Monitor visual styles were not found.';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Five-Bin Monitor Demo · SmartBin</title>
    <style>
<?php echo $monitorStyles; ?>

        .demo-banner {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            padding: 12px 16px;
            border: 1px solid #b9d8c0;
            border-radius: 12px;
            background: #eef8f0;
            color: #22663a;
            font-size: 13.5px;
            font-weight: 800;
            letter-spacing: .04em;
        }

        .demo-banner::before {
            content: "";
            width: 8px;
            height: 8px;
            flex: none;
            border-radius: 50%;
            background: #2f9b58;
        }

        .bin.is-selected {
            box-shadow: 0 0 0 3px var(--color), var(--shadow-lg);
            transform: translateY(-3px);
        }

        body {
            cursor: none;
        }

        .bins,
        .bins * {
            cursor: none !important;
            pointer-events: none;
        }

        @media (hover: hover) {
            .bin:hover {
                box-shadow: var(--shadow-sm);
                transform: none;
            }

            .bin.is-selected:hover {
                box-shadow: 0 0 0 3px var(--color), var(--shadow-lg);
                transform: translateY(-3px);
            }
        }

        .demo-controls {
            display: grid;
            gap: 12px;
            padding: clamp(18px, 2vw, 26px);
            background: #f7faf7;
            border-top: 1px solid var(--line);
        }

        .demo-controls__title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 0;
            color: var(--ink-2);
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .demo-controls__title::after {
            content: "Press 1–5 on your keyboard";
            color: var(--brand);
            font-size: 12px;
            letter-spacing: .02em;
            text-transform: none;
        }

        .demo-history {
            max-height: 220px;
            overflow: auto;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .demo-history li {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--line);
            color: var(--ink-2);
            font-size: 15px;
            font-weight: 650;
        }

        .demo-history li:last-child {
            border-bottom: 0;
        }

        .demo-history time {
            color: var(--muted);
            font-size: 13px;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .demo-history__empty {
            color: var(--muted);
            font-style: italic;
            font-weight: 400 !important;
        }

        .demo-history-heading {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 9px 16px;
            border: 1px solid var(--line);
            border-bottom: 0;
            border-radius: 12px 12px 0 0;
            background: #edf5ef;
            color: var(--muted);
            font-size: 10px;
            font-weight: 850;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        @media (min-width: 861px) {
            body {
                height: 100vh;
                overflow: hidden;
                padding: 10px 20px;
            }

            .shell {
                height: 100%;
                display: grid;
                grid-template-rows: auto auto auto minmax(0, 1fr) auto;
                gap: 10px;
            }

            .topbar,
            .demo-banner,
            .camera,
            .pagefoot {
                margin-top: 0;
                margin-bottom: 0;
            }

            .topbar {
                min-height: 54px;
            }

            .brand__mark {
                width: 44px;
                height: 44px;
            }

            .brand h1 {
                font-size: clamp(21px, 2.2vw, 30px);
            }

            .demo-banner {
                padding: 9px 14px;
            }

            .bins {
                min-height: 0;
            }

            .bin {
                padding: 13px 12px 11px;
            }

            .bin__name {
                margin-top: 7px;
                min-height: 34px;
                font-size: 15px;
            }

            .bin__value {
                margin-top: 5px;
                font-size: 32px;
            }

            .meter {
                margin-top: 7px;
            }

            .bin__foot {
                margin-top: 7px;
                padding-top: 7px;
            }

            .camera {
                min-height: 0;
                display: grid;
                grid-template-rows: auto auto minmax(0, 1fr);
            }

            .camera__head {
                padding: 10px 16px;
            }

            .camera__results {
                padding-top: 10px;
                padding-bottom: 10px;
            }

            .demo-controls {
                min-height: 0;
                grid-template-rows: auto minmax(0, 1fr);
                padding: 14px 18px;
            }

            .demo-history {
                max-height: none;
                min-height: 0;
            }

            .pagefoot {
                padding-bottom: 0;
            }
        }

    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <div class="brand">
                <div class="brand__mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 7h18"/>
                        <path d="M8 7V5a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/>
                        <path d="M6 7l1 12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-12"/>
                        <path d="M10 11v6M14 11v6"/>
                    </svg>
                </div>
                <div>
                    <h1>CT-ABS MONITORING SCREEN</h1>
                    <p>Live waste-separation status, left to right</p>
                </div>
            </div>
            <div class="clock">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7v5l3 2"/>
                </svg>
                <div>
                    <span class="clock__label">Updated</span>
                    <time id="demo-clock">--</time>
                </div>
            </div>
        </header>

        <div class="demo-banner" role="status">
            DEMO MODE –  CLASSIFICATION
        </div>

        <section class="bins" aria-label="Five waste bins">
            <article class="bin" data-category="plastic_bottle" style="--color: #3386b8; --fill: 0%">
                <div class="bin__top">
                    <span class="bin__pos">Position 1</span>
                    <span class="bin__icon" aria-hidden="true">PET</span>
                </div>
                <h2 class="bin__name">Plastic Bottle (PET)</h2>
                <div class="bin__value">0<small>%</small></div>
                <div class="meter" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"><span></span></div>
                <div class="bin__foot">
                    <span class="pill available"><i aria-hidden="true"></i>Available</span>
                    <span class="bin__meta">Demo bin</span>
                </div>
            </article>

            <article class="bin" data-category="shredded_thin_plastic" style="--color: #d18a32; --fill: 0%">
                <div class="bin__top">
                    <span class="bin__pos">Position 2</span>
                    <span class="bin__icon" aria-hidden="true">TP</span>
                </div>
                <h2 class="bin__name">Shredded Thin Plastic (TP)</h2>
                <div class="bin__value">0<small>%</small></div>
                <div class="meter" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"><span></span></div>
                <div class="bin__foot">
                    <span class="pill available"><i aria-hidden="true"></i>Available</span>
                    <span class="bin__meta">Demo bin</span>
                </div>
            </article>

            <article class="bin" data-category="metal_waste" style="--color: #657486; --fill: 0%">
                <div class="bin__top">
                    <span class="bin__pos">Position 3</span>
                    <span class="bin__icon" aria-hidden="true">MET</span>
                </div>
                <h2 class="bin__name">Metal Waste (MET)</h2>
                <div class="bin__value">0<small>%</small></div>
                <div class="meter" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"><span></span></div>
                <div class="bin__foot">
                    <span class="pill available"><i aria-hidden="true"></i>Available</span>
                    <span class="bin__meta">Demo bin</span>
                </div>
            </article>

            <article class="bin" data-category="shredded_biodegradable" style="--color: #3e9b61; --fill: 0%">
                <div class="bin__top">
                    <span class="bin__pos">Position 4</span>
                    <span class="bin__icon" aria-hidden="true">BIO</span>
                </div>
                <h2 class="bin__name">Shredded Biodegradable (BIO)</h2>
                <div class="bin__value">0<small>%</small></div>
                <div class="meter" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"><span></span></div>
                <div class="bin__foot">
                    <span class="pill available"><i aria-hidden="true"></i>Available</span>
                    <span class="bin__meta">Demo bin</span>
                </div>
            </article>

            <article class="bin" data-category="reject" style="--color: #c94b4b; --fill: 0%">
                <div class="bin__top">
                    <span class="bin__pos">Position 5</span>
                    <span class="bin__icon" aria-hidden="true">!</span>
                </div>
                <h2 class="bin__name">Reject Waste</h2>
                <div class="bin__value">0<small>%</small></div>
                <div class="meter" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"><span></span></div>
                <div class="bin__foot">
                    <span class="pill available"><i aria-hidden="true"></i>Available</span>
                    <span class="bin__meta">Demo bin</span>
                </div>
            </article>
        </section>

        <section class="camera" aria-labelledby="demo-heading">
            <div class="camera__head">
                <div class="camera__title">
                    <span class="glyph" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16v16H4z"/>
                            <path d="M8 12h8M12 8v8"/>
                        </svg>
                    </span>
                    <div>
                        <h2 id="demo-heading">Automated Classification Demo</h2>
                        <p>Keyboard-controlled demonstration</p>
                    </div>
                </div>
                <span class="live"><i aria-hidden="true"></i>Demo</span>
            </div>

            <div class="camera__results" aria-label="Latest manual classification">
                <div class="camera__result">
                    <span class="camera__result-label">Demo status</span>
                    <span class="camera__result-value camera__state is-online">Manual input</span>
                </div>
                <div class="camera__result">
                    <span class="camera__result-label">Detection</span>
                    <span id="demo-detection" class="camera__result-value camera__state">No selection</span>
                </div>
                <div class="camera__result camera__result--prediction">
                    <span class="camera__result-label">Latest detection</span>
                    <span id="demo-category" class="camera__result-value">Waiting for selection</span>
                    <span id="demo-key" class="camera__confidence">Waiting for phone button</span>
                </div>
                <div class="camera__result">
                    <span class="camera__result-label">Detection time</span>
                    <span id="demo-time" class="camera__result-value">No detection yet</span>
                </div>
            </div>

            <div class="demo-controls">
                <h3 class="demo-controls__title">Classification history</h3>
                <div class="demo-history-heading" aria-hidden="true">
                    <span>Category</span>
                    <span>Detection time</span>
                </div>
                <ol id="demo-history" class="demo-history" aria-label="Manual detection history">
                    <li class="demo-history__empty">No manual selections yet.</li>
                </ol>
            </div>
        </section>

        <footer class="pagefoot">
            <div class="legend" aria-label="Bin order, left to right">
                <span style="--c: #3386b8"><i aria-hidden="true"></i><b>1</b>Plastic Bottle (PET)</span>
                <span style="--c: #d18a32"><i aria-hidden="true"></i><b>2</b>Shredded Thin Plastic (TP)</span>
                <span style="--c: #657486"><i aria-hidden="true"></i><b>3</b>Metal Waste (MET)</span>
                <span style="--c: #3e9b61"><i aria-hidden="true"></i><b>4</b>Shredded Biodegradable (BIO)</span>
                <span style="--c: #c94b4b"><i aria-hidden="true"></i><b>5</b>Reject Waste</span>
            </div>
            <div>Manual selections are shared between these demo pages in this browser.</div>
        </footer>
    </div>

    <script>
        (function () {
            var categories = {
                plastic_bottle: 'Plastic Bottle (PET)',
                shredded_thin_plastic: 'Shredded Thin Plastic (TP)',
                metal_waste: 'Metal Waste (MET)',
                shredded_biodegradable: 'Shredded Biodegradable (BIO)',
                reject: 'Reject Waste'
            };
            var selectedCategory = '';
            var history = [];
            var historyElement = document.getElementById('demo-history');
            var sharedRelayUrl = 'testsync.php';
            var lastSharedSelection = '';
            var relayInitialized = false;

            function updateClock() {
                document.getElementById('demo-clock').textContent =
                    new Intl.DateTimeFormat('en-US', {
                        month: 'short',
                        day: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: true
                    }).format(new Date()) + ' PHT';
            }

            function renderHistory() {
                if (!history.length) {
                    historyElement.innerHTML =
                        '<li class="demo-history__empty">No manual selections yet.</li>';
                    return;
                }

                historyElement.innerHTML = history.map(function (entry) {
                    return '<li><span>' + entry.category + '</span><time>' +
                        entry.time + '</time></li>';
                }).join('');
            }

            function selectCategory(category, selectedAt) {
                if (!categories[category]) return;

                var now = new Date();
                var time = new Intl.DateTimeFormat('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                }).format(selectedAt ? new Date(selectedAt) : now) + ' PHT';

                selectedCategory = category;
                history.unshift({
                    category: categories[category],
                    time: time
                });
                history = history.slice(0, 20);

                document.querySelectorAll('.bin').forEach(function (bin) {
                    bin.classList.toggle(
                        'is-selected',
                        bin.dataset.category === selectedCategory
                    );
                });

                document.getElementById('demo-detection').textContent =
                    'Selected manually';
                document.getElementById('demo-detection').classList.add('is-detecting');
                document.getElementById('demo-category').textContent =
                    categories[category];
                document.getElementById('demo-key').textContent =
                    'Received from phone button · Manual demo';
                document.getElementById('demo-time').textContent = time;
                renderHistory();
            }

            function applySharedSelectionData(sharedSelection) {
                if (!sharedSelection || !categories[sharedSelection.category]) {
                    return;
                }

                var sharedSelectionData = JSON.stringify(sharedSelection);
                if (sharedSelectionData === lastSharedSelection) {
                    return;
                }

                lastSharedSelection = sharedSelectionData;
                selectCategory(
                    sharedSelection.category,
                    sharedSelection.selectedAt
                );
            }

            function pollSharedRelay() {
                fetch(sharedRelayUrl, {cache: 'no-store'})
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Demo relay returned HTTP ' + response.status);
                        }
                        return response.json();
                    })
                    .then(function (response) {
                        if (!response.success) {
                            throw new Error(response.error || 'Demo relay returned an error.');
                        }
                        if (response.state) {
                            var relaySelectionData = JSON.stringify(response.state);
                            if (!relayInitialized) {
                                lastSharedSelection = relaySelectionData;
                                relayInitialized = true;
                                return;
                            }
                            applySharedSelectionData(response.state);
                        } else {
                            relayInitialized = true;
                        }
                    })
                    .catch(function (error) {
                        console.error('Unable to read demo relay:', error);
                    });
            }

            pollSharedRelay();
            setInterval(pollSharedRelay, 1000);

            updateClock();
            setInterval(updateClock, 1000);
        }());
    </script>
</body>
</html>
