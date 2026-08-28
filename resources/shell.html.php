<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>viewer.css">
</head>
<body>
  <div class="layout" id="docs-layout">
    <div class="sidebar-panel">
      <button type="button"
              class="sidebar-toggle"
              id="sidebar-toggle"
              title="收起目录"
              aria-label="收起目录"
              aria-expanded="true"
              aria-controls="sidebar-doc-list sidebar-toc">
        <svg class="sidebar-toggle-icon" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
        </svg>
      </button>
      <aside class="sidebar">
        <div class="sidebar-header">
          <div class="sidebar-header-row">
            <button type="button"
                    class="sidebar-back"
                    id="sidebar-back"
                    title="返回文档列表"
                    aria-label="返回文档列表"
                    hidden>
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
              </svg>
            </button>
            <span class="sidebar-header-title" id="sidebar-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>
        <div class="sidebar-body" id="sidebar-body">
          <nav class="sidebar-nav sidebar-view" id="sidebar-doc-list">
        <?php
        $lastGroup = null;
        foreach ($docTree as $item):
            $group = $item['group'] ?? '';
            if ($group !== $lastGroup):
                $lastGroup = $group;
                $groupLabel = $group !== '' ? $group : '根目录';
        ?>
        <div class="sidebar-group-title"><?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <a href="#<?= rawurlencode($item['path']) ?>"
           data-path="<?= htmlspecialchars($item['path'], ENT_QUOTES, 'UTF-8') ?>"
           data-title="<?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>">
          <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
        </a>
        <?php endforeach; ?>
          </nav>
          <nav class="sidebar-nav sidebar-toc-nav sidebar-view" id="sidebar-toc" hidden></nav>
        </div>
      </aside>
    </div>
    <main class="content">
      <div class="doc-welcome" id="doc-welcome">
        <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
        <p>请从左侧列表选择要查看的文档</p>
      </div>
      <article class="markdown-body" id="doc-content" hidden>
        <div class="doc-loading">加载中…</div>
      </article>
    </main>
  </div>

  <div class="kd-diagram-lightbox" id="diagram-lightbox" hidden>
    <div class="kd-diagram-lightbox-toolbar">
      <span class="kd-diagram-lightbox-hint">滚轮缩放 · 拖拽平移 · Esc 关闭</span>
      <div class="kd-diagram-lightbox-actions">
        <button type="button" id="diagram-zoom-out" title="缩小" aria-label="缩小">−</button>
        <button type="button" id="diagram-zoom-reset" title="重置" aria-label="重置">100%</button>
        <button type="button" id="diagram-zoom-in" title="放大" aria-label="放大">+</button>
        <button type="button" id="diagram-zoom-close" title="关闭" aria-label="关闭">关闭</button>
      </div>
    </div>
    <div class="kd-diagram-lightbox-stage" id="diagram-lightbox-stage">
      <div class="kd-diagram-lightbox-inner" id="diagram-lightbox-inner"></div>
    </div>
  </div>

  <script>
    window.__DOCS__ = {
      title: <?= json_encode($title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      fileBase: <?= json_encode($fileBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      initialDoc: <?= json_encode($initialDoc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      features: <?= json_encode($features, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
  </script>
  <script src="<?= htmlspecialchars($cdn->marked, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php if (!empty($features['mermaid'])): ?>
  <script src="<?= htmlspecialchars($cdn->mermaid, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
<?php if (!empty($features['drawio'])): ?>
  <script src="<?= htmlspecialchars($cdn->drawio, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
  <script src="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>viewer.js"></script>
</body>
</html>
