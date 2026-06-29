(function () {
    'use strict';

    const DEFAULTS = {
        cloudCanvasId: 'word-cloud-canvas',
        graphViewportId: 'tag-network-viewport',
        graphControlsId: 'tag-network-controls',
        maxCount: 0,
        wordList: [],
        nodes: [],
        edges: [],
        renderOnLoad: true,
        renderVisibleOnly: true,
        tagUrl: 'tag_view.php?tag_id='
    };

    function waitForLibrary(isReady, callback, attempts = 20) {
        if (isReady()) {
            callback();
            return;
        }

        if (attempts <= 0) {
            return;
        }

        setTimeout(() => waitForLibrary(isReady, callback, attempts - 1), 150);
    }

    function isUsableElement(element, visibleOnly) {
        return !!element && (!visibleOnly || !!element.offsetParent);
    }

    function tagTypeSolidColor(category) {
        const normalized = String(category || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
        if (normalized === 'tema') return '#006392';
        if (normalized === 'metodo') return '#944F00';
        if (normalized === 'fonte') return '#5DCF00';
        return '#464B51';
    }

    function themedWordColor(word) {
        const explicitColor = Array.isArray(word) ? word[4] : '';
        const category = Array.isArray(word) ? word[3] : '';
        return explicitColor || tagTypeSolidColor(category);
    }

    function hexToRgba(hex, alpha, isDark) {
        const normalized = String(hex || '').replace('#', '').trim();
        if (!/^[0-9a-f]{6}$/i.test(normalized)) {
            return isDark ? 'rgba(255, 255, 255, 0.16)' : 'rgba(15, 23, 42, 0.08)';
        }

        const value = parseInt(normalized, 16);
        const r = (value >> 16) & 255;
        const g = (value >> 8) & 255;
        const b = value & 255;
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    function renderWordCloud(config) {
        const canvas = document.getElementById(config.cloudCanvasId);
        if (!isUsableElement(canvas, config.renderVisibleOnly)) return;
        if (typeof window.WordCloud !== 'function') return;

        const rect = canvas.getBoundingClientRect();
        canvas.width = canvas.offsetWidth || rect.width || 800;
        canvas.height = 420;

        WordCloud(canvas, {
            list: config.wordList || [],
            gridSize: 8,
            weightFactor: function (size) {
                if (config.maxCount <= 0) return 22;
                return 18 + (size / config.maxCount) * 26;
            },
            fontFamily: 'Outfit, sans-serif',
            color: themedWordColor,
            rotateRatio: 0,
            backgroundColor: 'transparent',
            click: function(item) {
                if (item && item[2]) {
                    window.location.href = config.tagUrl + item[2];
                }
            },
            hover: function(item) {
                canvas.style.cursor = item ? 'pointer' : 'default';
            }
        });
    }

    function renderTagNetwork(config) {
        const container = document.getElementById(config.graphViewportId);
        if (!isUsableElement(container, config.renderVisibleOnly)) return;
        if (!window.vis || !window.vis.Network || !window.vis.DataSet) return;

        const rawNodes = config.nodes || [];
        const rawEdges = config.edges || [];
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const labelColor = isDark ? '#f8fafc' : '#111827';
        const mutedEdgeColor = isDark ? 'rgba(148, 163, 184, 0.13)' : 'rgba(71, 85, 105, 0.11)';
        const categoryFallback = isDark
            ? { bg: '#273444', text: '#e5e7eb', border: '#6b7280' }
            : { bg: '#f8f9fa', text: '#464B51', border: '#adb5bd' };
        const categoryStyles = new Map();

        rawNodes.forEach((node) => {
            const category = node.category || 'Sem tipo';
            if (!categoryStyles.has(category)) {
                categoryStyles.set(category, {
                    bg: node.colorSolid ? hexToRgba(node.colorSolid, isDark ? 0.32 : 0.16, isDark) : (node.colorBg || categoryFallback.bg),
                    text: node.colorSolid || categoryFallback.text,
                    border: node.colorSolid || node.colorBorder || categoryFallback.border
                });
            }
        });

        const filterState = window.tagNetworkFilterState || { categories: {}, edges: {} };
        window.tagNetworkFilterState = filterState;
        Array.from(categoryStyles.keys()).forEach((category) => {
            if (typeof filterState.categories[category] === 'undefined') {
                filterState.categories[category] = true;
            }
        });
        ['hierarchy', 'cooccurrence'].forEach((type) => {
            if (typeof filterState.edges[type] === 'undefined') {
                filterState.edges[type] = true;
            }
        });

        const controls = document.getElementById(config.graphControlsId);
        if (controls) {
            controls.innerHTML = '';
            const addSwitch = (kind, value, label, checked) => {
                const id = `graph-filter-${kind}-${String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/gi, '-').toLowerCase()}`;
                const wrapper = document.createElement('div');
                wrapper.className = 'form-check form-switch';
                const input = document.createElement('input');
                input.className = 'form-check-input tag-network-filter';
                input.type = 'checkbox';
                input.role = 'switch';
                input.id = id;
                input.dataset.filterKind = kind;
                input.dataset.filterValue = value;
                input.checked = checked;
                const labelEl = document.createElement('label');
                labelEl.className = 'form-check-label';
                labelEl.htmlFor = id;
                labelEl.textContent = label;
                wrapper.append(input, labelEl);
                controls.appendChild(wrapper);
            };

            Array.from(categoryStyles.keys())
                .sort((a, b) => a.localeCompare(b, 'pt-BR'))
                .forEach((category) => addSwitch('category', category, category, filterState.categories[category] !== false));

            addSwitch('edge', 'hierarchy', 'Hierarquia', filterState.edges.hierarchy !== false);
            addSwitch('edge', 'cooccurrence', 'Artigos', filterState.edges.cooccurrence !== false);
        }

        const styleNode = (node) => {
            const style = categoryStyles.get(node.category || 'Sem tipo') || categoryFallback;
            return {
                ...node,
                font: {
                    color: labelColor,
                    size: 14,
                    face: 'Outfit, sans-serif',
                    multi: false
                },
                color: {
                    background: style.bg,
                    border: style.border,
                    highlight: {
                        background: hexToRgba(style.text, isDark ? 0.34 : 0.18, isDark),
                        border: style.text
                    },
                    hover: {
                        background: hexToRgba(style.text, isDark ? 0.26 : 0.13, isDark),
                        border: style.text
                    }
                },
                shape: 'box',
                margin: { top: 8, right: 10, bottom: 8, left: 10 },
                borderWidth: 1.5,
                shadow: { enabled: false }
            };
        };

        const styleEdge = (edge) => {
            const isHierarchy = edge.type === 'hierarchy';
            return {
                ...edge,
                color: {
                    color: isHierarchy
                        ? (isDark ? 'rgba(248, 250, 252, 0.2)' : 'rgba(15, 23, 42, 0.16)')
                        : mutedEdgeColor,
                    highlight: isHierarchy
                        ? (isDark ? 'rgba(226, 232, 240, 0.32)' : 'rgba(51, 65, 85, 0.26)')
                        : (isDark ? 'rgba(226, 232, 240, 0.5)' : 'rgba(51, 65, 85, 0.42)'),
                    hover: isHierarchy
                        ? (isDark ? 'rgba(226, 232, 240, 0.32)' : 'rgba(51, 65, 85, 0.26)')
                        : (isDark ? 'rgba(226, 232, 240, 0.5)' : 'rgba(51, 65, 85, 0.42)')
                },
                dashes: isHierarchy ? [3, 7] : false,
                width: isHierarchy ? 0.8 : (edge.value ? Math.min(3, 0.7 + (edge.value * 0.35)) : 0.8)
            };
        };

        const data = {
            nodes: new vis.DataSet(),
            edges: new vis.DataSet()
        };
        const options = {
            layout: { improvedLayout: false },
            nodes: {
                borderWidth: 2,
                chosen: {
                    node: function(values) {
                        values.borderWidth = 3;
                    }
                }
            },
            edges: {
                selectionWidth: 1.5,
                smooth: { enabled: false }
            },
            physics: {
                barnesHut: {
                    gravitationalConstant: -1100,
                    centralGravity: 0.08,
                    springLength: 115,
                    springConstant: 0.018,
                    damping: 0.94,
                    avoidOverlap: 0.45
                },
                minVelocity: 0.75,
                maxVelocity: 10,
                solver: 'barnesHut',
                stabilization: {
                    enabled: true,
                    iterations: 90,
                    updateInterval: 25,
                    fit: true
                }
            },
            interaction: {
                hover: true,
                dragNodes: true,
                zoomView: true,
                dragView: true
            }
        };

        container.innerHTML = '';
        const network = new vis.Network(container, data, options);
        let frameGuardTimer = null;
        let frameGuardStopTimer = null;

        const fitGraphInFrame = () => {
            if (!isUsableElement(container, config.renderVisibleOnly) || data.nodes.length === 0) {
                return;
            }
            network.fit({ animation: false });
        };

        const stopFrameGuard = () => {
            if (frameGuardTimer) {
                clearInterval(frameGuardTimer);
                frameGuardTimer = null;
            }
            if (frameGuardStopTimer) {
                clearTimeout(frameGuardStopTimer);
                frameGuardStopTimer = null;
            }
        };

        const startFrameGuard = (duration = 10000) => {
            stopFrameGuard();
            fitGraphInFrame();
            frameGuardTimer = setInterval(fitGraphInFrame, 650);
            frameGuardStopTimer = setTimeout(stopFrameGuard, duration);
        };

        const applyGraphFilters = (fit = true) => {
            const enabledCategories = new Set(Object.entries(filterState.categories)
                .filter(([, enabled]) => enabled !== false)
                .map(([category]) => category));
            const enabledEdges = new Set(Object.entries(filterState.edges)
                .filter(([, enabled]) => enabled !== false)
                .map(([type]) => type));
            const visibleNodeIds = new Set(rawNodes
                .filter((node) => enabledCategories.has(node.category || 'Sem tipo'))
                .map((node) => node.id));
            const filteredEdges = rawEdges.filter((edge) => {
                const edgeType = edge.type || 'cooccurrence';
                return enabledEdges.has(edgeType) && visibleNodeIds.has(edge.from) && visibleNodeIds.has(edge.to);
            });
            const connectedNodeIds = new Set();
            filteredEdges.forEach((edge) => {
                connectedNodeIds.add(edge.from);
                connectedNodeIds.add(edge.to);
            });
            const filteredNodes = rawNodes.filter((node) => visibleNodeIds.has(node.id) && connectedNodeIds.has(node.id));

            data.nodes.clear();
            data.edges.clear();
            data.nodes.add(filteredNodes.map(styleNode));
            data.edges.add(filteredEdges.map(styleEdge));

            if (fit && filteredNodes.length > 0) {
                network.fit({ animation: { duration: 180, easingFunction: 'easeInOutQuad' } });
            }
        };

        applyGraphFilters(false);
        startFrameGuard();

        document.querySelectorAll('.tag-network-filter').forEach((input) => {
            input.addEventListener('change', () => {
                const kind = input.dataset.filterKind;
                const value = input.dataset.filterValue;
                if (kind === 'category') {
                    filterState.categories[value] = input.checked;
                } else if (kind === 'edge') {
                    filterState.edges[value] = input.checked;
                }
                applyGraphFilters(true);
                startFrameGuard(3500);
            });
        });

        network.once("stabilizationIterationsDone", function() {
            network.fit({ animation: { duration: 250, easingFunction: 'easeInOutQuad' } });
            startFrameGuard(10000);
            setTimeout(() => {
                stopFrameGuard();
                network.fit({ animation: false });
                network.setOptions({ physics: false });
            }, 10000);
        });

        network.on("click", function (params) {
            if (params.nodes.length > 0) {
                window.location.href = config.tagUrl + params.nodes[0];
            }
        });

        network.on("hoverNode", function () {
            container.style.cursor = 'pointer';
        });
        network.on("blurNode", function () {
            container.style.cursor = 'default';
        });
    }

    function init(userConfig = {}) {
        const config = { ...DEFAULTS, ...userConfig };
        const renderAll = () => {
            waitForLibrary(() => typeof window.WordCloud === 'function', () => renderWordCloud(config));
            waitForLibrary(() => window.vis && window.vis.Network && window.vis.DataSet, () => renderTagNetwork(config));
        };

        if (config.renderOnLoad) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', renderAll);
            } else {
                renderAll();
            }
        }

        document.querySelectorAll('[data-bs-toggle="tab"]').forEach((tabButton) => {
            tabButton.addEventListener('shown.bs.tab', renderAll);
        });

        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'data-bs-theme') {
                    renderAll();
                }
            });
        });
        observer.observe(document.documentElement, { attributes: true });

        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(renderAll, 300);
        });
    }

    window.FicharioTagVisualizations = {
        init,
        renderTagNetwork,
        renderWordCloud
    };

    if (window.FicharioTagVisualizationsConfig) {
        init(window.FicharioTagVisualizationsConfig);
    }
}());
