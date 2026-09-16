(function () {
      var fileBase = window.__DOCS__.fileBase || '/docs/files/';
      var currentDoc = '';
      var drawioSeq = 0;
      var sidebarStorageKey = 'md-docs-sidebar-collapsed';
      var sidebarMenuTitle = (window.__DOCS__.title || '文档预览');
      var tocSpy = null;
      var tocSpyItems = [];
      var tocSpySuspended = false;
      var tocSpySuspendTimer = null;
      var tocSpyRaf = null;
      var tocScrollOffset = 32;

      function setSidebarCollapsed(collapsed, persist) {
        var layout = document.getElementById('docs-layout');
        var toggle = document.getElementById('sidebar-toggle');
        if (!layout || !toggle) {
          return;
        }
        layout.classList.toggle('sidebar-collapsed', collapsed);
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggle.title = collapsed ? '展开目录' : '收起目录';
        toggle.setAttribute('aria-label', toggle.title);
        if (persist !== false) {
          try {
            localStorage.setItem(sidebarStorageKey, collapsed ? '1' : '0');
          } catch (err) {}
        }
      }

      function initSidebarToggle() {
        var toggle = document.getElementById('sidebar-toggle');
        if (!toggle) {
          return;
        }
        var collapsed = false;
        try {
          collapsed = localStorage.getItem(sidebarStorageKey) === '1';
        } catch (err) {}
        setSidebarCollapsed(collapsed, false);
        toggle.addEventListener('click', function () {
          var layout = document.getElementById('docs-layout');
          setSidebarCollapsed(layout.classList.contains('sidebar-collapsed') === false);
        });
      }

      var MERMAID_INLINE_CONFIG = {
        startOnLoad: false,
        theme: 'default',
        securityLevel: 'loose',
        flowchart: { useMaxWidth: false, htmlLabels: true },
        mindmap: { useMaxWidth: false }
      };

      var MERMAID_LIGHTBOX_CONFIG = {
        startOnLoad: false,
        theme: 'default',
        securityLevel: 'loose',
        flowchart: { useMaxWidth: false, htmlLabels: false },
        mindmap: { useMaxWidth: false }
      };

      mermaid.initialize(MERMAID_INLINE_CONFIG);

      var MM_COLORS = ['blue', 'purple', 'green', 'orange', 'teal', 'gray'];

      function decodeHtml(text) {
        var el = document.createElement('textarea');
        el.innerHTML = text;
        return el.value;
      }

      function transformMermaidBlocks(html) {
        return html.replace(
          /<pre><code class="language-mermaid">([\s\S]*?)<\/code><\/pre>/gi,
          function (match, code) {
            var text = decodeHtml(code);
            if (/^\s*mindmap\b/m.test(text)) {
              return '<div class="kd-mindmap-host"><script type="text/kd-mindmap">' +
                escapeHtml(text) + '<\/script></div>';
            }
            return '<div class="mermaid">' + text + '</div>';
          }
        );
      }

      marked.setOptions({ gfm: true, breaks: false });

      function escapeHtml(text) {
        return text
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;');
      }

      function headingSlug(text) {
        return String(text || '')
          .trim()
          .replace(/[、，。：:；;！!？?（）()\[\]【】《》<>「」『』"'""'']/g, '')
          .replace(/\s+/g, '');
      }

      function addHeadingIds(html) {
        var wrap = document.createElement('div');
        wrap.innerHTML = html;
        wrap.querySelectorAll('h1,h2,h3,h4,h5,h6').forEach(function (el) {
          if (!el.id) {
            var slug = headingSlug(el.textContent);
            if (slug) {
              el.id = slug;
            }
          }
        });
        return wrap.innerHTML;
      }

      function docDir(path) {
        var idx = path.lastIndexOf('/');
        return idx >= 0 ? path.slice(0, idx + 1) : '';
      }

      function resolveDrawioUrl(docPath, relPath) {
        var base = docDir(docPath);
        var parts = (base + relPath).split('/');
        var stack = [];
        parts.forEach(function (part) {
          if (!part || part === '.') return;
          if (part === '..') { stack.pop(); return; }
          stack.push(part);
        });
        return fileBase + stack.map(encodeURIComponent).join('/').replace(/%2F/g, '/');
      }

      function transformDrawioBlocks(html, docPath) {
        return html.replace(
          /<blockquote>([\s\S]*?)<code>(diagrams\/[^<]+\.drawio)<\/code>([\s\S]*?)<\/blockquote>/gi,
          function (match, before, relPath, after) {
            var id = 'drawio-' + (++drawioSeq);
            var url = resolveDrawioUrl(docPath, relPath);
            var caption = (after || '').replace(/<[^>]+>/g, '').trim();
            var captionHtml = caption
              ? '<div class="drawio-caption">' + escapeHtml(caption) + '</div>'
              : '';
            return '<div id="' + id + '" class="drawio-viewer" data-drawio-url="' + url + '"></div>' + captionHtml;
          }
        );
      }

      function mountDrawioViewers(root) {
        var nodes = root.querySelectorAll('.drawio-viewer[data-drawio-url]');
        if (!nodes.length) {
          return Promise.resolve();
        }
        var tasks = Array.prototype.map.call(nodes, function (node) {
          if (node.getAttribute('data-mounted') === '1') {
            return Promise.resolve();
          }
          node.setAttribute('data-mounted', '1');
          var url = node.getAttribute('data-drawio-url');
          return fetch(url)
            .then(function (res) {
              if (!res.ok) {
                throw new Error('HTTP ' + res.status);
              }
              return res.text();
            })
            .then(function (xml) {
              node.className = 'mxgraph drawio-viewer';
              node.setAttribute('data-mxgraph', JSON.stringify({
                highlight: '#3f51b5',
                nav: true,
                resize: true,
                toolbar: 'zoom layers',
                xml: xml
              }));
            })
            .catch(function (err) {
              node.innerHTML = '<div class="doc-error">流程图加载失败：' + escapeHtml(err.message) + '</div>';
            });
        });
        return Promise.all(tasks).then(function () {
          if (window.GraphViewer && typeof window.GraphViewer.processElements === 'function') {
            window.GraphViewer.processElements();
          }
        });
      }

      function extractMindmapNodeText(raw) {
        return raw
          .replace(/^root\s*\(\((.+)\)\)\s*$/i, '$1')
          .replace(/^\(\((.+)\)\)$/, '$1')
          .replace(/^root\s*\((.+)\)\s*$/i, '$1')
          .trim();
      }

      function parseMindmapSource(text) {
        var lines = text.replace(/\r\n/g, '\n').split('\n');
        if (!lines.length || lines[0].trim() !== 'mindmap') {
          return null;
        }
        var root = { text: '', children: [] };
        var stack = [{ node: root, level: -1 }];
        for (var i = 1; i < lines.length; i++) {
          var line = lines[i];
          if (!line.trim()) {
            continue;
          }
          var m = line.match(/^(\s*)(.+)$/);
          if (!m) {
            continue;
          }
          var level = Math.floor(m[1].length / 2) - 1;
          if (level < 0) {
            continue;
          }
          var nodeText = extractMindmapNodeText(m[2].trim());
          var node = { text: nodeText, children: [] };
          while (stack.length > 1 && stack[stack.length - 1].level >= level) {
            stack.pop();
          }
          var parent = stack[stack.length - 1].node;
          if (level === 0) {
            root.text = nodeText;
          } else {
            parent.children.push(node);
          }
          stack.push({ node: level === 0 ? root : node, level: level });
        }
        return root.text ? root : null;
      }

      function renderMindmapSubtree(children) {
        if (!children.length) {
          return '';
        }
        return children.map(function (child) {
          if (!child.children.length) {
            return '<div class="kd-mm-item">' + escapeHtml(child.text) + '</div>';
          }
          return '<div class="kd-mm-group">' +
            '<div class="kd-mm-group-title">' + escapeHtml(child.text) + '</div>' +
            '<div class="kd-mm-content">' + renderMindmapSubtree(child.children) + '</div>' +
            '</div>';
        }).join('');
      }

      function renderMindmapHtml(tree) {
        var branches = tree.children.map(function (branch, idx) {
          var color = MM_COLORS[idx % MM_COLORS.length];
          return '<div class="kd-mm-branch kd-mm-c-' + color + '">' +
            '<div class="kd-mm-label">' + escapeHtml(branch.text) + '</div>' +
            '<div class="kd-mm-content">' + renderMindmapSubtree(branch.children) + '</div>' +
            '</div>';
        }).join('');
        return '<div class="kd-mindmap">' +
          '<div class="kd-mm-layout">' +
          '<div class="kd-mm-root-col"><div class="kd-mm-root">' + escapeHtml(tree.text) + '</div></div>' +
          '<div class="kd-mm-branches-col">' + branches + '</div>' +
          '</div></div>';
      }

      function mountMindmaps(root) {
        root.querySelectorAll('.kd-mindmap-host').forEach(function (host) {
          var script = host.querySelector('script[type="text/kd-mindmap"]');
          if (!script) {
            return;
          }
          var tree = parseMindmapSource(script.textContent);
          if (tree) {
            host.outerHTML = renderMindmapHtml(tree);
          }
        });
      }

      function renderMermaid(root) {
        var blocks = root.querySelectorAll('.mermaid');
        if (!blocks.length) return Promise.resolve();
        blocks.forEach(function (block) {
          if (!block.getAttribute('data-mermaid-src')) {
            block.setAttribute('data-mermaid-src', block.textContent.trim());
          }
        });
        return mermaid.run({ nodes: blocks });
      }

      var EXPAND_ICON_SVG = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h6v6h-2V5h-4V3zM9 3v2H5v4H3V3h6zm12 12v6h-6v-2h4v-4h2zM9 21H3v-6h2v4h4v2z"/></svg>';

      var diagramLightbox = {
        overlay: null,
        stage: null,
        inner: null,
        scale: 1,
        baseScale: 1,
        panX: 0,
        panY: 0,
        dragging: false,
        dragStartX: 0,
        dragStartY: 0,
        panStartX: 0,
        panStartY: 0,
        sourceNode: null,
        contentNode: null,
        contentType: '',
        naturalWidth: 0,
        naturalHeight: 0
      };

      function getSvgNaturalSize(svg) {
        var viewBox = svg.getAttribute('viewBox');
        if (viewBox) {
          var parts = viewBox.trim().split(/[\s,]+/);
          if (parts.length === 4) {
            return {
              width: parseFloat(parts[2]) || 1,
              height: parseFloat(parts[3]) || 1
            };
          }
        }
        var width = parseFloat(svg.getAttribute('width'));
        var height = parseFloat(svg.getAttribute('height'));
        if (!width || !height) {
          var rect = svg.getBoundingClientRect();
          width = rect.width || 1;
          height = rect.height || 1;
        }
        return { width: width, height: height };
      }

      function getStageFitScale(width, height) {
        if (!diagramLightbox.stage || !width || !height) {
          return 1;
        }
        var rect = diagramLightbox.stage.getBoundingClientRect();
        if (!rect.width || !rect.height) {
          return 1;
        }
        var scale = Math.min(rect.width * 0.9 / width, rect.height * 0.9 / height);
        if (!scale || !isFinite(scale) || scale <= 0) {
          return 1;
        }
        return scale;
      }

      function updateLightboxFitScale() {
        diagramLightbox.baseScale = getStageFitScale(
          diagramLightbox.naturalWidth,
          diagramLightbox.naturalHeight
        );
        applyDiagramTransform();
      }

      function applyDiagramTransform() {
        if (!diagramLightbox.inner) {
          return;
        }
        var content = diagramLightbox.contentNode;
        if (content) {
          var displayScale = diagramLightbox.baseScale * diagramLightbox.scale;
          if (!displayScale || !isFinite(displayScale) || displayScale <= 0) {
            displayScale = 1;
          }
          content.style.width = diagramLightbox.naturalWidth + 'px';
          content.style.zoom = displayScale;
          if (diagramLightbox.contentType === 'mermaid') {
            var svg = content.querySelector('svg');
            if (svg && diagramLightbox.naturalWidth) {
              svg.style.width = diagramLightbox.naturalWidth + 'px';
              svg.style.height = diagramLightbox.naturalHeight + 'px';
              svg.style.maxWidth = 'none';
            }
          }
        }
        diagramLightbox.inner.style.transform =
          'translate(calc(-50% + ' + diagramLightbox.panX + 'px), calc(-50% + ' + diagramLightbox.panY + 'px))';
        var resetBtn = document.getElementById('diagram-zoom-reset');
        if (resetBtn) {
          resetBtn.textContent = Math.round(diagramLightbox.scale * 100) + '%';
        }
      }

      function resetDiagramTransform() {
        diagramLightbox.scale = 1;
        diagramLightbox.panX = 0;
        diagramLightbox.panY = 0;
        applyDiagramTransform();
      }

      function closeDiagramLightbox() {
        if (!diagramLightbox.overlay || diagramLightbox.overlay.hidden) {
          return;
        }
        diagramLightbox.inner.innerHTML = '';
        diagramLightbox.overlay.hidden = true;
        document.body.style.overflow = '';
        diagramLightbox.sourceNode = null;
        diagramLightbox.contentNode = null;
        diagramLightbox.contentType = '';
        diagramLightbox.naturalWidth = 0;
        diagramLightbox.naturalHeight = 0;
        diagramLightbox.baseScale = 1;
        diagramLightbox.dragging = false;
        diagramLightbox.stage.classList.remove('is-dragging');
      }

      function fixMermaidForeignObjects(svg) {
        if (!svg) {
          return;
        }
        svg.querySelectorAll('foreignObject').forEach(function (fo) {
          fo.setAttribute('overflow', 'visible');
          var div = fo.querySelector('div');
          if (!div) {
            return;
          }
          div.style.overflow = 'visible';
          div.style.display = 'flex';
          div.style.alignItems = 'center';
          div.style.justifyContent = 'center';
          div.style.boxSizing = 'border-box';
          div.style.lineHeight = '1.4';
          div.style.height = '100%';
          div.style.width = '100%';
        });
      }

      function measureMermaidContentSize(wrap) {
        var svg = wrap ? wrap.querySelector('svg') : null;
        if (!svg) {
          return { width: 1, height: 1 };
        }
        var viewSize = getSvgNaturalSize(svg);
        svg.style.width = viewSize.width + 'px';
        svg.style.height = viewSize.height + 'px';
        svg.style.maxWidth = 'none';
        var rect = svg.getBoundingClientRect();
        return {
          width: rect.width || viewSize.width,
          height: rect.height || viewSize.height
        };
      }

      function openMermaidLightbox(node) {
        var source = node.getAttribute('data-mermaid-src');
        if (!source) {
          return;
        }
        diagramLightbox.sourceNode = node;
        diagramLightbox.contentType = 'mermaid';
        diagramLightbox.scale = 1;
        diagramLightbox.panX = 0;
        diagramLightbox.panY = 0;
        diagramLightbox.baseScale = 1;
        diagramLightbox.naturalWidth = 0;
        diagramLightbox.naturalHeight = 0;
        diagramLightbox.contentNode = null;

        var shell = document.createElement('div');
        shell.className = 'kd-diagram-lightbox-content';
        shell.innerHTML = '<div class="doc-loading" style="color:#ccc;padding:48px 64px">流程图加载中…</div>';
        diagramLightbox.inner.appendChild(shell);
        diagramLightbox.overlay.hidden = false;
        document.body.style.overflow = 'hidden';

        var renderId = 'kd-lb-' + Date.now();
        mermaid.initialize(MERMAID_LIGHTBOX_CONFIG);
        mermaid.render(renderId, source).then(function (result) {
          mermaid.initialize(MERMAID_INLINE_CONFIG);
          if (diagramLightbox.overlay.hidden || diagramLightbox.sourceNode !== node) {
            return;
          }
          var wrap = document.createElement('div');
          wrap.className = 'mermaid kd-diagram-lightbox-mermaid';
          wrap.innerHTML = result.svg;
          if (typeof result.bindFunctions === 'function') {
            result.bindFunctions(wrap);
          }
          fixMermaidForeignObjects(wrap.querySelector('svg'));
          shell.innerHTML = '';
          shell.appendChild(wrap);
          diagramLightbox.contentNode = wrap;
          var size = measureMermaidContentSize(wrap);
          diagramLightbox.naturalWidth = size.width;
          diagramLightbox.naturalHeight = size.height;
          requestAnimationFrame(function () {
            updateLightboxFitScale();
          });
        }).catch(function () {
          mermaid.initialize(MERMAID_INLINE_CONFIG);
          if (!diagramLightbox.overlay.hidden && diagramLightbox.sourceNode === node) {
            shell.innerHTML = '<div class="doc-error" style="padding:48px 64px">流程图加载失败</div>';
          }
        });
      }

      function buildLightboxMindmapContent(node) {
        var clone = node.cloneNode(true);
        clone.classList.remove('kd-diagram-zoomable');
        clone.removeAttribute('data-zoom-bound');
        var expandBtn = clone.querySelector('.kd-diagram-expand-btn');
        if (expandBtn) {
          expandBtn.parentNode.removeChild(expandBtn);
        }
        var rect = node.getBoundingClientRect();
        diagramLightbox.naturalWidth = rect.width || clone.scrollWidth || 1;
        diagramLightbox.naturalHeight = rect.height || clone.scrollHeight || 1;
        diagramLightbox.contentType = 'mindmap';
        diagramLightbox.baseScale = 1;
        return clone;
      }

      function openDiagramLightbox(node) {
        if (!diagramLightbox.overlay || !node) {
          return;
        }
        closeDiagramLightbox();
        if (node.classList.contains('mermaid')) {
          openMermaidLightbox(node);
          return;
        }
        var content = null;
        if (node.classList.contains('kd-mindmap')) {
          content = buildLightboxMindmapContent(node);
        }
        if (!content) {
          return;
        }
        diagramLightbox.sourceNode = node;
        diagramLightbox.contentNode = content;
        var shell = document.createElement('div');
        shell.className = 'kd-diagram-lightbox-content';
        shell.appendChild(content);
        diagramLightbox.inner.appendChild(shell);
        diagramLightbox.scale = 1;
        diagramLightbox.panX = 0;
        diagramLightbox.panY = 0;
        diagramLightbox.overlay.hidden = false;
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(function () {
          updateLightboxFitScale();
        });
      }

      function ensureExpandButton(node) {
        if (!node || node.querySelector('.kd-diagram-expand-btn')) {
          return;
        }
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'kd-diagram-expand-btn';
        btn.title = '放大查看';
        btn.setAttribute('aria-label', '放大查看');
        btn.innerHTML = EXPAND_ICON_SVG;
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          openDiagramLightbox(node);
        });
        node.appendChild(btn);
      }

      function bindDiagramZoomable(node) {
        if (!node || node.getAttribute('data-zoom-bound') === '1') {
          return;
        }
        node.setAttribute('data-zoom-bound', '1');
        node.classList.add('kd-diagram-zoomable');
        ensureExpandButton(node);
        node.addEventListener('dblclick', function (e) {
          if (e.target.closest('.kd-diagram-expand-btn')) {
            return;
          }
          e.preventDefault();
          openDiagramLightbox(node);
        });
      }

      function initDiagramZoom(root) {
        root.querySelectorAll('.mermaid, .kd-mindmap').forEach(bindDiagramZoomable);
      }

      function initDiagramLightbox() {
        diagramLightbox.overlay = document.getElementById('diagram-lightbox');
        diagramLightbox.stage = document.getElementById('diagram-lightbox-stage');
        diagramLightbox.inner = document.getElementById('diagram-lightbox-inner');
        if (!diagramLightbox.overlay || !diagramLightbox.stage || !diagramLightbox.inner) {
          return;
        }

        document.getElementById('diagram-zoom-close').addEventListener('click', closeDiagramLightbox);
        document.getElementById('diagram-zoom-in').addEventListener('click', function () {
          diagramLightbox.scale = Math.min(5, diagramLightbox.scale + 0.25);
          applyDiagramTransform();
        });
        document.getElementById('diagram-zoom-out').addEventListener('click', function () {
          diagramLightbox.scale = Math.max(0.25, diagramLightbox.scale - 0.25);
          applyDiagramTransform();
        });
        document.getElementById('diagram-zoom-reset').addEventListener('click', resetDiagramTransform);

        diagramLightbox.stage.addEventListener('wheel', function (e) {
          if (diagramLightbox.overlay.hidden) {
            return;
          }
          e.preventDefault();
          var delta = e.deltaY > 0 ? -0.15 : 0.15;
          diagramLightbox.scale = Math.min(5, Math.max(0.25, diagramLightbox.scale + delta));
          applyDiagramTransform();
        }, { passive: false });

        diagramLightbox.stage.addEventListener('mousedown', function (e) {
          if (diagramLightbox.overlay.hidden || e.button !== 0) {
            return;
          }
          diagramLightbox.dragging = true;
          diagramLightbox.dragStartX = e.clientX;
          diagramLightbox.dragStartY = e.clientY;
          diagramLightbox.panStartX = diagramLightbox.panX;
          diagramLightbox.panStartY = diagramLightbox.panY;
          diagramLightbox.stage.classList.add('is-dragging');
        });

        window.addEventListener('mousemove', function (e) {
          if (!diagramLightbox.dragging) {
            return;
          }
          diagramLightbox.panX = diagramLightbox.panStartX + (e.clientX - diagramLightbox.dragStartX);
          diagramLightbox.panY = diagramLightbox.panStartY + (e.clientY - diagramLightbox.dragStartY);
          applyDiagramTransform();
        });

        window.addEventListener('mouseup', function () {
          if (!diagramLightbox.dragging) {
            return;
          }
          diagramLightbox.dragging = false;
          diagramLightbox.stage.classList.remove('is-dragging');
        });

        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && diagramLightbox.overlay && !diagramLightbox.overlay.hidden) {
            closeDiagramLightbox();
          }
        });

        diagramLightbox.overlay.addEventListener('click', function (e) {
          if (e.target === diagramLightbox.overlay) {
            closeDiagramLightbox();
          }
        });
      }

      function setActiveNav(path) {
        document.querySelectorAll('#sidebar-doc-list a').forEach(function (link) {
          link.classList.toggle('active', link.getAttribute('data-path') === path);
        });
      }

      function getDocTitle(path) {
        var links = document.querySelectorAll('#sidebar-doc-list a[data-path]');
        for (var i = 0; i < links.length; i++) {
          if (links[i].getAttribute('data-path') === path) {
            return links[i].getAttribute('data-title') || links[i].textContent.trim();
          }
        }
        return path.replace(/\.md$/i, '').split('/').pop();
      }

      function showWelcome() {
        var welcome = document.getElementById('doc-welcome');
        var content = document.getElementById('doc-content');
        if (welcome) {
          welcome.hidden = false;
        }
        if (content) {
          content.hidden = true;
        }
      }

      function showDocContent() {
        var welcome = document.getElementById('doc-welcome');
        var content = document.getElementById('doc-content');
        if (welcome) {
          welcome.hidden = true;
        }
        if (content) {
          content.hidden = false;
        }
      }

      function setSidebarMode(mode) {
        var docList = document.getElementById('sidebar-doc-list');
        var tocNav = document.getElementById('sidebar-toc');
        var backBtn = document.getElementById('sidebar-back');
        var titleEl = document.getElementById('sidebar-title');
        var isDoc = mode === 'doc';
        if (docList) {
          docList.hidden = isDoc;
        }
        if (tocNav) {
          tocNav.hidden = !isDoc;
        }
        if (backBtn) {
          backBtn.hidden = !isDoc;
        }
        if (titleEl) {
          titleEl.textContent = isDoc && currentDoc ? getDocTitle(currentDoc) : sidebarMenuTitle;
        }
      }

      function clearTocObserver() {
        if (tocSpy) {
          tocSpy();
          tocSpy = null;
        }
        tocSpyItems = [];
        if (tocSpyRaf) {
          cancelAnimationFrame(tocSpyRaf);
          tocSpyRaf = null;
        }
        if (tocSpySuspendTimer) {
          clearTimeout(tocSpySuspendTimer);
          tocSpySuspendTimer = null;
        }
        tocSpySuspended = false;
      }

      function suspendTocSpy(ms) {
        tocSpySuspended = true;
        if (tocSpySuspendTimer) {
          clearTimeout(tocSpySuspendTimer);
        }
        tocSpySuspendTimer = setTimeout(function () {
          tocSpySuspended = false;
          tocSpySuspendTimer = null;
          updateActiveTocFromScroll();
        }, ms || 900);
      }

      function pickActiveTocHeading(items) {
        if (!items.length) {
          return '';
        }
        var activeId = items[0].id;
        for (var i = 0; i < items.length; i++) {
          var el = document.getElementById(items[i].id);
          if (!el) {
            continue;
          }
          if (el.getBoundingClientRect().top <= tocScrollOffset) {
            activeId = items[i].id;
          } else {
            break;
          }
        }
        return activeId;
      }

      function updateActiveTocFromScroll() {
        if (tocSpySuspended || !tocSpyItems.length) {
          return;
        }
        var activeId = pickActiveTocHeading(tocSpyItems);
        if (activeId) {
          setActiveToc(activeId);
        }
      }

      function setActiveToc(anchor) {
        var links = document.querySelectorAll('#sidebar-toc a[data-anchor]');
        links.forEach(function (link) {
          link.classList.toggle('active', link.getAttribute('data-anchor') === anchor);
        });
      }

      function buildTocFromContent(root) {
        var headings = root.querySelectorAll('h1,h2,h3,h4,h5,h6');
        var items = [];
        headings.forEach(function (el) {
          if (!el.id) {
            return;
          }
          var level = parseInt(el.tagName.slice(1), 10);
          items.push({
            id: el.id,
            text: el.textContent.trim(),
            level: level
          });
        });
        return items;
      }

      function renderToc(items) {
        var tocNav = document.getElementById('sidebar-toc');
        if (!tocNav) {
          return;
        }
        if (!items.length) {
          tocNav.innerHTML = '<div class="sidebar-group-title" style="padding-top:16px">暂无目录</div>';
          return;
        }
        tocNav.innerHTML = items.map(function (item) {
          return '<a href="#' + encodeURIComponent(item.id) + '"' +
            ' class="toc-level-' + item.level + '"' +
            ' data-anchor="' + escapeHtml(item.id) + '">' +
            escapeHtml(item.text) + '</a>';
        }).join('');
      }

      function initTocScrollSpy(root, items) {
        clearTocObserver();
        if (!items.length) {
          return;
        }
        tocSpyItems = items;
        function onScroll() {
          if (tocSpySuspended) {
            return;
          }
          if (tocSpyRaf) {
            return;
          }
          tocSpyRaf = requestAnimationFrame(function () {
            tocSpyRaf = null;
            updateActiveTocFromScroll();
          });
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        tocSpy = function () {
          window.removeEventListener('scroll', onScroll);
        };
        updateActiveTocFromScroll();
      }

      function goToMenu() {
        currentDoc = '';
        closeDiagramLightbox();
        clearTocObserver();
        setActiveNav('');
        setSidebarMode('menu');
        showWelcome();
        var tocNav = document.getElementById('sidebar-toc');
        if (tocNav) {
          tocNav.innerHTML = '';
        }
        if (location.hash) {
          history.replaceState(null, '', location.pathname + location.search);
        }
      }

      function isKnownDocPath(path) {
        if (!path) {
          return false;
        }
        var links = document.querySelectorAll('#sidebar-doc-list a[data-path]');
        for (var i = 0; i < links.length; i++) {
          if (links[i].getAttribute('data-path') === path) {
            return true;
          }
        }
        return false;
      }

      function scrollToAnchor(anchor) {
        if (!anchor) {
          return false;
        }
        var el = document.getElementById(anchor);
        if (!el) {
          el = document.getElementById(decodeURIComponent(anchor));
        }
        if (!el) {
          return false;
        }
        suspendTocSpy();
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return true;
      }

      function buildDocHash(docPath, anchor) {
        var hash = encodeURI(docPath);
        if (anchor) {
          hash += '!' + encodeURIComponent(anchor);
        }
        return hash;
      }

      function parseLocationHash() {
        var hash = decodeURIComponent(location.hash.replace(/^#/, ''));
        if (!hash) {
          return { doc: '', anchor: '' };
        }
        var bangIdx = hash.indexOf('!');
        if (bangIdx >= 0) {
          return {
            doc: hash.slice(0, bangIdx),
            anchor: decodeURIComponent(hash.slice(bangIdx + 1))
          };
        }
        if (isKnownDocPath(hash)) {
          return { doc: hash, anchor: '' };
        }
        if (currentDoc) {
          return {
            doc: currentDoc,
            anchor: hash
          };
        }
        if (window.__DOCS__.initialDoc && isKnownDocPath(window.__DOCS__.initialDoc)) {
          return { doc: window.__DOCS__.initialDoc, anchor: hash };
        }
        return { doc: '', anchor: '' };
      }

      function updateDocHash(docPath, anchor, replace) {
        var nextHash = '#' + buildDocHash(docPath, anchor);
        if (location.hash === nextHash) {
          return;
        }
        if (replace) {
          history.replaceState(null, '', nextHash);
        } else {
          location.hash = nextHash;
        }
      }

      function loadDoc(path, anchor) {
        if (!path) {
          goToMenu();
          return Promise.resolve();
        }
        var contentEl = document.getElementById('doc-content');
        var sameDoc = path === currentDoc;
        currentDoc = path;
        setActiveNav(path);
        setSidebarMode('doc');
        showDocContent();
        if (!sameDoc) {
          closeDiagramLightbox();
          clearTocObserver();
          contentEl.innerHTML = '<div class="doc-loading">加载中…</div>';
        }
        var url = fileBase + path.split('/').map(encodeURIComponent).join('/');
        return fetch(url)
          .then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.text();
          })
          .then(function (md) {
            var html = marked.parse(md);
            html = addHeadingIds(html);
            html = transformMermaidBlocks(html);
            html = transformDrawioBlocks(html, path);
            contentEl.innerHTML = html;
            var tocItems = buildTocFromContent(contentEl);
            renderToc(tocItems);
            initTocScrollSpy(contentEl, tocItems);
            mountMindmaps(contentEl);
            return mountDrawioViewers(contentEl).then(function () {
              return renderMermaid(contentEl);
            }).then(function () {
              initDiagramZoom(contentEl);
            });
          })
          .then(function () {
            if (anchor) {
              requestAnimationFrame(function () {
                if (scrollToAnchor(anchor)) {
                  setActiveToc(anchor);
                }
              });
            } else if (document.querySelector('#sidebar-toc a[data-anchor]')) {
              var first = document.querySelector('#sidebar-toc a[data-anchor]');
              setActiveToc(first.getAttribute('data-anchor'));
            }
          })
          .catch(function (err) {
            contentEl.innerHTML = '<div class="doc-error">加载失败：' + escapeHtml(err.message) + '</div>';
            renderToc([]);
          });
      }

      window.addEventListener('hashchange', function () {
        var parsed = parseLocationHash();
        if (!parsed.doc) {
          goToMenu();
          return;
        }
        if (parsed.doc !== currentDoc) {
          loadDoc(parsed.doc, parsed.anchor);
          return;
        }
        if (parsed.anchor) {
          scrollToAnchor(parsed.anchor);
          setActiveToc(parsed.anchor);
        }
      });

      document.getElementById('sidebar-doc-list').addEventListener('click', function (e) {
        var link = e.target.closest('a[data-path]');
        if (!link) return;
        e.preventDefault();
        var path = link.getAttribute('data-path');
        if (path === currentDoc) {
          return;
        }
        location.hash = encodeURI(path);
      });

      document.getElementById('sidebar-toc').addEventListener('click', function (e) {
        var link = e.target.closest('a[data-anchor]');
        if (!link) return;
        e.preventDefault();
        var anchor = link.getAttribute('data-anchor');
        if (!anchor || !currentDoc) {
          return;
        }
        updateDocHash(currentDoc, anchor, true);
        scrollToAnchor(anchor);
        setActiveToc(anchor);
      });

      document.getElementById('sidebar-back').addEventListener('click', function () {
        goToMenu();
      });

      document.getElementById('doc-content').addEventListener('click', function (e) {
        var link = e.target.closest('a[href^="#"]');
        if (!link) return;
        var anchor = decodeURIComponent(link.getAttribute('href').slice(1));
        if (!anchor || isKnownDocPath(anchor)) {
          return;
        }
        e.preventDefault();
        if (currentDoc) {
          updateDocHash(currentDoc, anchor, true);
        }
        scrollToAnchor(anchor);
        setActiveToc(anchor);
      });

      initSidebarToggle();
      initDiagramLightbox();

      var initial = parseLocationHash();
      if (initial.doc) {
        loadDoc(initial.doc, initial.anchor);
      } else {
        goToMenu();
      }
    })();